<?php

namespace App\Services\Workflow;

use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepExecution;
use App\Models\WorkflowExecution;
use App\Models\AIPersona;
use App\Models\Company;
use App\Models\Setting;
use App\Models\SettingTwo;
use App\Helpers\Classes\ApiHelper;
use Illuminate\Support\Facades\Log;
use Exception;

class WorkflowStepExecutor
{
    protected Setting $settings;
    protected SettingTwo $settingsTwo;

    public function __construct()
    {
        $this->settings = Setting::getCache();
        $this->settingsTwo = SettingTwo::getCache();
    }

    /**
     * Execute a workflow step
     *
     * Main orchestration method that coordinates step execution
     */
    public function executeStep(WorkflowStepExecution $stepExecution): array
    {
        try {
            // Mark step as started
            $stepExecution->start($this->prepareStepInput($stepExecution));

            // Get the step definition
            $step = $stepExecution->step;
            $execution = $stepExecution->execution;

            // Resolve context variables in prompt
            $resolvedPrompt = $this->resolveContextVariables(
                $step->prompt_template,
                $execution->context,
                $stepExecution->input_data
            );

            // Apply persona and brand voice if configured
            $systemPrompt = $this->buildSystemPrompt($step, $execution);

            // Execute based on step type
            $output = match ($step->type) {
                'text' => $this->executeTextStep($step, $resolvedPrompt, $systemPrompt, $execution),
                'image' => $this->executeImageStep($step, $resolvedPrompt, $execution),
                'video' => $this->executeVideoStep($step, $resolvedPrompt, $execution),
                'audio' => $this->executeAudioStep($step, $resolvedPrompt, $execution),
                'code' => $this->executeCodeStep($step, $resolvedPrompt, $systemPrompt, $execution),
                'decision' => $this->executeDecisionStep($step, $resolvedPrompt, $execution),
                'transform' => $this->executeTransformStep($step, $resolvedPrompt, $execution),
                default => throw new Exception("Unsupported step type: {$step->type}"),
            };

            // Mark step as completed
            $stepExecution->complete($output, $output['tokens_used'] ?? 0);

            // Update execution context with output
            $this->updateExecutionContext($execution, $step, $output);

            return $output;
        } catch (Exception $e) {
            // Handle errors with retry logic
            return $this->handleStepError($stepExecution, $e);
        }
    }

    /**
     * Execute text generation step
     */
    protected function executeTextStep(
        WorkflowStep $step,
        string $prompt,
        string $systemPrompt,
        WorkflowExecution $execution
    ): array {
        // Get entity (AI model) from step configuration
        $entity = $this->getEntityForStep($step);

        // Initialize API key for the engine
        $this->initializeEngineApi($entity->engine());

        // Get entity driver
        $driver = Entity::driver($entity)->forUser($execution->user_id);

        // Prepare messages for chat-based models
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $prompt],
        ];

        // Get configuration from step or use defaults
        $stepConfig = $step->config ?? [];
        if (is_string($stepConfig)) {
            $stepConfig = json_decode($stepConfig, true) ?? [];
        }
        $config = array_merge([
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'top_p' => 1.0,
        ], $stepConfig);

        // Execute text generation
        $response = $this->executeAiRequest($entity, $messages, $config);

        return [
            'type' => 'text',
            'content' => $response['content'],
            'tokens_used' => $response['tokens_used'],
            'model' => $entity->value,
        ];
    }

    /**
     * Execute image generation step
     */
    protected function executeImageStep(
        WorkflowStep $step,
        string $prompt,
        WorkflowExecution $execution
    ): array {
        // Get image generation entity
        $entity = $this->getEntityForStep($step, 'image');

        // Initialize API key
        $this->initializeEngineApi($entity->engine());

        // Get configuration
        $stepConfig = $step->config ?? [];
        if (is_string($stepConfig)) {
            $stepConfig = json_decode($stepConfig, true) ?? [];
        }
        $config = array_merge([
            'size' => '1024x1024',
            'quality' => 'standard',
            'n' => 1,
        ], $stepConfig);

        // Execute image generation
        $driver = Entity::driver($entity)->forUser($execution->user_id);
        $response = $this->executeImageRequest($entity, $prompt, $config);

        return [
            'type' => 'image',
            'url' => $response['url'],
            'image_path' => $response['path'] ?? null,
            'tokens_used' => $response['tokens_used'] ?? 1,
            'model' => $entity->value,
        ];
    }

    /**
     * Execute video generation step
     */
    protected function executeVideoStep(
        WorkflowStep $step,
        string $prompt,
        WorkflowExecution $execution
    ): array {
        // Get video generation entity
        $entity = $this->getEntityForStep($step, 'video');

        $stepConfig = $step->config ?? [];
        if (is_string($stepConfig)) {
            $stepConfig = json_decode($stepConfig, true) ?? [];
        }
        $config = array_merge([
            'duration' => 5,
            'aspect_ratio' => '16:9',
        ], $stepConfig);

        // Video generation placeholder - actual implementation would call the video API
        return [
            'type' => 'video',
            'status' => 'processing',
            'job_id' => uniqid('video_'),
            'message' => 'Video generation started',
            'tokens_used' => 0,
            'model' => $entity->value,
        ];
    }

    /**
     * Execute audio/TTS generation step
     */
    protected function executeAudioStep(
        WorkflowStep $step,
        string $prompt,
        WorkflowExecution $execution
    ): array {
        // Get TTS entity
        $entity = $this->getEntityForStep($step, 'audio');

        $stepConfig = $step->config ?? [];
        if (is_string($stepConfig)) {
            $stepConfig = json_decode($stepConfig, true) ?? [];
        }
        $config = array_merge([
            'voice' => 'alloy',
            'speed' => 1.0,
        ], $stepConfig);

        // TTS placeholder - actual implementation would call TTS API
        return [
            'type' => 'audio',
            'status' => 'processing',
            'job_id' => uniqid('audio_'),
            'message' => 'Audio generation started',
            'tokens_used' => strlen($prompt),
            'model' => $entity->value,
        ];
    }

    /**
     * Execute code/script execution step
     *
     * Executes bash scripts (typically FFmpeg commands) stored in step config
     */
    protected function executeCodeStep(
        WorkflowStep $step,
        string $prompt,
        string $systemPrompt,
        WorkflowExecution $execution
    ): array {
        // Get script from config
        $config = $step->config ?? [];
        if (is_string($config)) {
            $config = json_decode($config, true) ?? [];
        }
        $language = $config['language'] ?? 'bash';
        $script = $config['script'] ?? null;

        if (!$script) {
            throw new Exception("Code step missing script in config");
        }

        // Resolve variables in script (replace {{variable}} with actual values)
        $resolvedScript = $this->resolveContextVariables(
            $script,
            $execution->context,
            $execution->input_data
        );

        Log::info("Executing code step", [
            'step_id' => $step->id,
            'step_name' => $step->name,
            'language' => $language,
            'script_length' => strlen($resolvedScript),
        ]);

        // Execute based on language
        if ($language === 'bash') {
            return $this->executeBashScript($resolvedScript, $step);
        }

        throw new Exception("Unsupported script language: {$language}");
    }

    /**
     * Execute bash script safely
     *
     * @param string $script Bash script to execute
     * @param WorkflowStep $step Workflow step for context
     * @return array Execution result
     */
    protected function executeBashScript(string $script, WorkflowStep $step): array
    {
        $startTime = microtime(true);

        // Create temporary script file in storage directory
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempScript = $tempDir . '/workflow_script_' . uniqid() . '.sh';
        file_put_contents($tempScript, $script);
        chmod($tempScript, 0755);

        try {
            // Execute with timeout
            $config = $step->config ?? [];
            if (is_string($config)) {
                $config = json_decode($config, true) ?? [];
            }
            $timeout = $config['timeout'] ?? 600; // 10 minutes default
            $process = new \Symfony\Component\Process\Process(['bash', $tempScript]);
            $process->setTimeout($timeout);

            $output = '';
            $errorOutput = '';

            $process->run(function ($type, $buffer) use (&$output, &$errorOutput) {
                if ($type === \Symfony\Component\Process\Process::ERR) {
                    $errorOutput .= $buffer;
                    Log::debug('Script stderr', ['output' => $buffer]);
                } else {
                    $output .= $buffer;
                }
            });

            $executionTime = microtime(true) - $startTime;

            if (!$process->isSuccessful()) {
                throw new Exception("Script execution failed: " . $errorOutput);
            }

            // Parse output - typically a file path
            $result = trim($output);

            Log::info("Code step completed successfully", [
                'step_name' => $step->name,
                'execution_time' => round($executionTime, 2),
                'output_length' => strlen($result),
            ]);

            return [
                'type' => 'code',
                'result' => $result,
                'output' => $result,
                'execution_time' => round($executionTime, 2),
                'tokens_used' => 0,
            ];
        } catch (Exception $e) {
            Log::error("Code step execution failed", [
                'step_name' => $step->name,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            // Cleanup temp script
            if (file_exists($tempScript)) {
                @unlink($tempScript);
            }
        }
    }

    /**
     * Execute decision/conditional step
     */
    protected function executeDecisionStep(
        WorkflowStep $step,
        string $prompt,
        WorkflowExecution $execution
    ): array {
        // Evaluate condition script
        $context = $execution->context;

        if ($step->condition_script) {
            $result = $this->evaluateCondition($step->condition_script, $context);

            return [
                'type' => 'decision',
                'result' => $result,
                'next_step' => $result ? 'true_branch' : 'false_branch',
                'tokens_used' => 0,
            ];
        }

        return [
            'type' => 'decision',
            'result' => true,
            'tokens_used' => 0,
        ];
    }

    /**
     * Execute data transformation step
     */
    protected function executeTransformStep(
        WorkflowStep $step,
        string $prompt,
        WorkflowExecution $execution
    ): array {
        // Transform data based on configuration
        $context = $execution->context;
        $transformConfig = $step->config ?? [];
        if (is_string($transformConfig)) {
            $transformConfig = json_decode($transformConfig, true) ?? [];
        }

        $transformed = $this->applyTransformations($context, $transformConfig);

        return [
            'type' => 'transform',
            'data' => $transformed,
            'tokens_used' => 0,
        ];
    }

    /**
     * Resolve context variables in prompt template
     *
     * Replaces {{variable_name}} with actual values from context
     */
    protected function resolveContextVariables(
        string $template,
        array $context,
        ?array $inputData = null
    ): string {
        $resolved = $template;

        // Merge input data into context
        $allData = array_merge($context, $inputData ?? []);

        // Replace {{variable}} patterns
        foreach ($allData as $key => $value) {
            if (is_scalar($value)) {
                $resolved = str_replace("{{" . $key . "}}", (string) $value, $resolved);
            } elseif (is_array($value)) {
                $resolved = str_replace("{{" . $key . "}}", json_encode($value), $resolved);
            }
        }

        return $resolved;
    }

    /**
     * Build system prompt with persona and brand voice
     */
    protected function buildSystemPrompt(
        WorkflowStep $step,
        WorkflowExecution $execution
    ): string {
        $parts = [];

        // Add step-specific system prompt
        if ($step->system_prompt) {
            $parts[] = $step->system_prompt;
        }

        // Add persona instructions if configured
        if ($execution->persona_id) {
            $persona = AIPersona::find($execution->persona_id);
            if ($persona) {
                $parts[] = $persona->buildSystemPrompt();
            }
        }

        // Add brand voice if configured
        if ($execution->brand_id) {
            $company = Company::find($execution->brand_id);
            if ($company) {
                $brandVoice = $this->buildBrandVoicePrompt($company);
                if ($brandVoice) {
                    $parts[] = $brandVoice;
                }
            }
        }

        return implode("\n\n", array_filter($parts));
    }

    /**
     * Build brand voice prompt from company settings
     */
    protected function buildBrandVoicePrompt(Company $company): string
    {
        $parts = [];

        if ($company->tone_of_voice) {
            $parts[] = "Brand tone: " . $company->tone_of_voice;
        }

        if ($company->specific_instructions) {
            $parts[] = "Brand instructions: " . $company->specific_instructions;
        }

        if ($company->target_audience) {
            $parts[] = "Target audience: " . $company->target_audience;
        }

        return implode("\n", $parts);
    }

    /**
     * Prepare input data for step execution
     */
    protected function prepareStepInput(WorkflowStepExecution $stepExecution): array
    {
        $step = $stepExecution->step;
        $execution = $stepExecution->execution;

        // Start with execution input data
        $input = $execution->input_data ?? [];

        // Apply input mapping if configured
        $inputMapping = $step->input_mapping;
        if ($inputMapping && is_array($inputMapping)) {
            foreach ($inputMapping as $targetKey => $sourceKey) {
                $input[$targetKey] = data_get($execution->context, $sourceKey);
            }
        }

        return $input;
    }

    /**
     * Update execution context with step output
     */
    protected function updateExecutionContext(
        WorkflowExecution $execution,
        WorkflowStep $step,
        array $output
    ): void {
        $context = $execution->context ?? [];

        // Store output with step key
        if ($step->output_key) {
            $context[$step->output_key] = $output;
        }

        // Also store by step order for sequential access
        $context["step_{$step->step_order}"] = $output;

        $execution->update(['context' => $context]);
    }

    /**
     * Handle step execution errors with retry logic
     */
    protected function handleStepError(
        WorkflowStepExecution $stepExecution,
        Exception $exception
    ): array {
        Log::error("Workflow step execution failed", [
            'step_execution_id' => $stepExecution->id,
            'step_id' => $stepExecution->step_id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Check if retry is possible
        $step = $stepExecution->step;
        $errorHandling = $step->error_handling ?? [];
        $maxRetries = $errorHandling['max_retries'] ?? 3;

        if ($stepExecution->canRetry() && $stepExecution->retry_count < $maxRetries) {
            // Retry the step
            $stepExecution->retry();
            return $this->executeStep($stepExecution);
        }

        // Mark as failed
        $stepExecution->fail($exception->getMessage());

        throw $exception;
    }

    /**
     * Initialize API key for engine
     */
    protected function initializeEngineApi(EngineEnum $engine): void
    {
        match ($engine) {
            EngineEnum::OPEN_AI => ApiHelper::setOpenAiKey($this->settings),
            EngineEnum::ANTHROPIC => ApiHelper::setAnthropicKey($this->settings),
            EngineEnum::GEMINI => ApiHelper::setGeminiKey($this->settings),
            EngineEnum::X_AI => ApiHelper::setXAiKey($this->settings),
            default => ApiHelper::setOpenAiKey($this->settings),
        };
    }

    /**
     * Execute AI request for text generation
     */
    protected function executeAiRequest(EntityEnum $entity, array $messages, array $config): array
    {
        // This is a simplified implementation
        // In production, you'd use the actual entity driver to make API calls

        // For now, return a placeholder response
        return [
            'content' => "Generated content for: " . $messages[1]['content'],
            'tokens_used' => 100,
        ];
    }

    /**
     * Execute image generation request
     */
    protected function executeImageRequest(EntityEnum $entity, string $prompt, array $config): array
    {
        // Placeholder for image generation
        return [
            'url' => 'https://example.com/generated-image.png',
            'path' => null,
            'tokens_used' => 1,
        ];
    }

    /**
     * Evaluate condition script
     */
    protected function evaluateCondition(string $script, array $context): bool
    {
        // Simple condition evaluation
        // In production, use a safe expression evaluator

        // For now, just check if the script contains "true"
        return str_contains(strtolower($script), 'true');
    }

    /**
     * Apply data transformations
     */
    protected function applyTransformations(array $data, array $config): array
    {
        $transformed = $data;

        // Apply transformations based on config
        foreach ($config as $key => $transformation) {
            // Example transformations: uppercase, lowercase, trim, etc.
            if (isset($data[$key])) {
                $transformed[$key] = match ($transformation) {
                    'uppercase' => strtoupper($data[$key]),
                    'lowercase' => strtolower($data[$key]),
                    'trim' => trim($data[$key]),
                    default => $data[$key],
                };
            }
        }

        return $transformed;
    }

    /**
     * Get entity (AI model) for a workflow step
     *
     * Resolves engine_id to the appropriate EntityEnum based on step type
     *
     * @param WorkflowStep $step The workflow step
     * @param string $type Step type override ('text', 'image', 'video', 'audio')
     * @return EntityEnum The entity to use for this step
     */
    protected function getEntityForStep(WorkflowStep $step, ?string $type = null): EntityEnum
    {
        $stepType = $type ?? $step->type;

        // If step has an engine_id, resolve it to engine key and then to entity
        if ($step->engine_id) {
            try {
                $engineKey = null;

                // Check if engine_id is numeric (database ID) or string (slug)
                if (is_numeric($step->engine_id)) {
                    // Query engines table to get the key
                    $engine = \DB::table('engines')->where('id', $step->engine_id)->first();
                    if ($engine) {
                        $engineKey = $engine->key;
                    }
                } else {
                    // It's already a slug
                    $engineKey = $step->engine_id;
                }

                if ($engineKey) {
                    // Convert engine key to EngineEnum
                    $engine = EngineEnum::fromSlug($engineKey);

                    // Get the default model for this engine based on step type
                    return match ($stepType) {
                        'text' => $engine->getDefaultWordModel($this->settings),
                        'image' => $engine->getDefaultImageModel() ?? EntityEnum::DALL_E_3,
                        'video' => EntityEnum::LUMA_DREAM_MACHINE,
                        'audio' => EntityEnum::TTS_1,
                        default => $engine->getDefaultWordModel($this->settings),
                    };
                }
            } catch (\Exception $e) {
                // If engine lookup or conversion fails, fall through to defaults
                Log::warning("Failed to resolve engine_id in workflow step", [
                    'step_id' => $step->id,
                    'engine_id' => $step->engine_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fall back to system defaults based on step type
        return match ($stepType) {
            'text' => EntityEnum::fromSlug($this->settings->openai_default_model ?? EntityEnum::GPT_4_O->value),
            'image' => EntityEnum::DALL_E_3,
            'video' => EntityEnum::LUMA_DREAM_MACHINE,
            'audio' => EntityEnum::TTS_1,
            default => EntityEnum::fromSlug($this->settings->openai_default_model ?? EntityEnum::GPT_4_O->value),
        };
    }
}

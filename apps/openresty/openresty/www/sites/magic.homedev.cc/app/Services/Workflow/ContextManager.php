<?php

namespace App\Services\Workflow;

use App\Models\WorkflowExecution;
use Illuminate\Support\Arr;

class ContextManager
{
    protected WorkflowExecution $execution;
    protected array $context;

    public function __construct(WorkflowExecution $execution)
    {
        $this->execution = $execution;
        $this->context = $execution->context ?? [];
    }

    /**
     * Set a value in the context
     */
    public function set(string $key, mixed $value): self
    {
        Arr::set($this->context, $key, $value);
        $this->save();
        return $this;
    }

    /**
     * Get a value from the context
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->context, $key, $default);
    }

    /**
     * Check if key exists in context
     */
    public function has(string $key): bool
    {
        return Arr::has($this->context, $key);
    }

    /**
     * Remove a key from context
     */
    public function forget(string $key): self
    {
        Arr::forget($this->context, $key);
        $this->save();
        return $this;
    }

    /**
     * Merge data into context
     */
    public function merge(array $data): self
    {
        $this->context = array_merge($this->context, $data);
        $this->save();
        return $this;
    }

    /**
     * Get all context data
     */
    public function all(): array
    {
        return $this->context;
    }

    /**
     * Clear all context data
     */
    public function clear(): self
    {
        $this->context = [];
        $this->save();
        return $this;
    }

    /**
     * Resolve template with context variables
     *
     * Replaces {{variable}} placeholders with actual values
     */
    public function resolveTemplate(string $template): string
    {
        $resolved = $template;

        // Replace {{variable}} patterns
        preg_match_all('/\{\{([^}]+)\}\}/', $template, $matches);

        foreach ($matches[1] as $variable) {
            $variable = trim($variable);
            $value = $this->get($variable);

            if ($value !== null) {
                if (is_scalar($value)) {
                    $resolved = str_replace("{{" . $variable . "}}", (string) $value, $resolved);
                } elseif (is_array($value)) {
                    $resolved = str_replace("{{" . $variable . "}}", json_encode($value), $resolved);
                }
            }
        }

        return $resolved;
    }

    /**
     * Get output from a previous step
     */
    public function getStepOutput(int $stepOrder): ?array
    {
        return $this->get("step_{$stepOrder}");
    }

    /**
     * Get output by key
     */
    public function getOutput(string $key): mixed
    {
        return $this->get($key);
    }

    /**
     * Store step output
     */
    public function storeStepOutput(int $stepOrder, string $key, array $output): self
    {
        // Store by step order
        $this->set("step_{$stepOrder}", $output);

        // Store by custom key if provided
        if ($key) {
            $this->set($key, $output);
        }

        return $this;
    }

    /**
     * Get execution input data
     */
    public function getInput(string $key = null): mixed
    {
        $inputData = $this->execution->input_data ?? [];

        if ($key === null) {
            return $inputData;
        }

        return Arr::get($inputData, $key);
    }

    /**
     * Get flattened context for template resolution
     */
    public function flatten(): array
    {
        return Arr::dot($this->context);
    }

    /**
     * Get context snapshot
     */
    public function snapshot(): array
    {
        return [
            'execution_id' => $this->execution->id,
            'current_step' => $this->execution->current_step,
            'context' => $this->context,
            'input_data' => $this->execution->input_data,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Restore context from snapshot
     */
    public function restore(array $snapshot): self
    {
        if (isset($snapshot['context'])) {
            $this->context = $snapshot['context'];
            $this->save();
        }

        return $this;
    }

    /**
     * Save context to execution
     */
    protected function save(): void
    {
        $this->execution->update(['context' => $this->context]);
    }

    /**
     * Create a new instance for execution
     */
    public static function for(WorkflowExecution $execution): self
    {
        return new self($execution);
    }

    /**
     * Get context size in bytes
     */
    public function size(): int
    {
        return strlen(json_encode($this->context));
    }

    /**
     * Check if context is too large
     */
    public function isTooLarge(int $maxBytes = 1048576): bool
    {
        return $this->size() > $maxBytes; // Default: 1MB
    }

    /**
     * Prune old step outputs to reduce context size
     */
    public function prune(int $keepLastSteps = 5): self
    {
        $currentStep = $this->execution->current_step;
        $pruneBeforeStep = max(1, $currentStep - $keepLastSteps);

        // Remove old step outputs
        for ($i = 1; $i < $pruneBeforeStep; $i++) {
            $this->forget("step_{$i}");
        }

        return $this;
    }

    /**
     * Get variables available for template resolution
     */
    public function getAvailableVariables(): array
    {
        $variables = [];

        // Flatten context to get all keys
        $flattened = $this->flatten();

        foreach ($flattened as $key => $value) {
            $variables[$key] = [
                'key' => $key,
                'type' => gettype($value),
                'value' => is_scalar($value) ? $value : '[' . gettype($value) . ']',
            ];
        }

        // Add input data variables
        foreach ($this->execution->input_data ?? [] as $key => $value) {
            $variables[$key] = [
                'key' => $key,
                'type' => gettype($value),
                'value' => is_scalar($value) ? $value : '[' . gettype($value) . ']',
                'source' => 'input',
            ];
        }

        return $variables;
    }

    /**
     * Validate template variables exist in context
     */
    public function validateTemplate(string $template): array
    {
        $errors = [];

        preg_match_all('/\{\{([^}]+)\}\}/', $template, $matches);

        foreach ($matches[1] as $variable) {
            $variable = trim($variable);

            if (!$this->has($variable) && !isset($this->execution->input_data[$variable])) {
                $errors[] = "Variable '{$variable}' not found in context";
            }
        }

        return $errors;
    }

    /**
     * Export context for debugging
     */
    public function export(): array
    {
        return [
            'execution' => [
                'id' => $this->execution->id,
                'workflow_id' => $this->execution->workflow_id,
                'status' => $this->execution->status,
                'current_step' => $this->execution->current_step,
                'total_steps' => $this->execution->total_steps,
            ],
            'context' => $this->context,
            'input_data' => $this->execution->input_data,
            'size_bytes' => $this->size(),
            'available_variables' => array_keys($this->flatten()),
        ];
    }
}

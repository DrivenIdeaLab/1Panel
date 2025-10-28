<?php

namespace App\Services\Workflow;

use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowExecution;
use App\Models\AIPersona;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class WorkflowService
{
    /**
     * Create a new workflow with steps
     */
    public function create(array $data, ?int $userId = null): Workflow
    {
        $this->validateWorkflowData($data);

        return DB::transaction(function () use ($data, $userId) {
            // Create workflow
            $workflow = Workflow::create([
                'user_id' => $userId ?? auth()->id(),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'category' => $data['category'] ?? null,
                'visibility' => $data['visibility'] ?? 'private',
                'is_template' => $data['is_template'] ?? false,
                'version' => $data['version'] ?? '1.0',
                'config' => $data['config'] ?? [],
                'metadata' => $data['metadata'] ?? [],
                'status' => $data['status'] ?? 'draft',
            ]);

            // Create steps if provided
            if (isset($data['steps']) && is_array($data['steps'])) {
                foreach ($data['steps'] as $stepData) {
                    $this->addStep($workflow, $stepData);
                }
            }

            return $workflow->load('steps');
        });
    }

    /**
     * Update an existing workflow
     */
    public function update(Workflow $workflow, array $data): Workflow
    {
        $this->validateWorkflowData($data, true);

        return DB::transaction(function () use ($workflow, $data) {
            // Update workflow
            $workflow->update(array_filter([
                'name' => $data['name'] ?? $workflow->name,
                'description' => $data['description'] ?? $workflow->description,
                'category' => $data['category'] ?? $workflow->category,
                'visibility' => $data['visibility'] ?? $workflow->visibility,
                'is_template' => $data['is_template'] ?? $workflow->is_template,
                'version' => $data['version'] ?? $workflow->version,
                'config' => $data['config'] ?? $workflow->config,
                'metadata' => $data['metadata'] ?? $workflow->metadata,
                'status' => $data['status'] ?? $workflow->status,
            ], fn($value) => $value !== null));

            // Update steps if provided
            if (isset($data['steps'])) {
                $this->syncSteps($workflow, $data['steps']);
            }

            return $workflow->fresh(['steps']);
        });
    }

    /**
     * Delete a workflow
     */
    public function delete(Workflow $workflow): bool
    {
        return DB::transaction(function () use ($workflow) {
            // Check for running executions
            $runningExecutions = $workflow->executions()->running()->count();

            if ($runningExecutions > 0) {
                throw new \RuntimeException(
                    "Cannot delete workflow with {$runningExecutions} running execution(s)"
                );
            }

            return $workflow->delete();
        });
    }

    /**
     * Add a step to a workflow
     */
    public function addStep(Workflow $workflow, array $stepData): WorkflowStep
    {
        $this->validateStepData($stepData);

        // Auto-increment step order if not provided
        if (!isset($stepData['step_order'])) {
            $maxOrder = $workflow->steps()->max('step_order') ?? 0;
            $stepData['step_order'] = $maxOrder + 1;
        }

        return $workflow->steps()->create($stepData);
    }

    /**
     * Update a workflow step
     */
    public function updateStep(WorkflowStep $step, array $stepData): WorkflowStep
    {
        $this->validateStepData($stepData, true);

        $step->update(array_filter($stepData, fn($value) => $value !== null));

        return $step->fresh();
    }

    /**
     * Delete a workflow step
     */
    public function deleteStep(WorkflowStep $step): bool
    {
        return DB::transaction(function () use ($step) {
            $workflow = $step->workflow;
            $deleted = $step->delete();

            // Reorder remaining steps
            if ($deleted) {
                $this->reorderSteps($workflow);
            }

            return $deleted;
        });
    }

    /**
     * Reorder workflow steps
     */
    public function reorderSteps(Workflow $workflow, ?array $stepOrder = null): void
    {
        DB::transaction(function () use ($workflow, $stepOrder) {
            if ($stepOrder) {
                // Use provided order
                foreach ($stepOrder as $order => $stepId) {
                    $workflow->steps()
                        ->where('id', $stepId)
                        ->update(['step_order' => $order + 1]);
                }
            } else {
                // Auto-reorder sequentially
                $steps = $workflow->steps()->orderBy('step_order')->get();
                foreach ($steps as $index => $step) {
                    $step->update(['step_order' => $index + 1]);
                }
            }
        });
    }

    /**
     * Clone a workflow (with or without execution history)
     */
    public function clone(
        Workflow $workflow,
        ?int $userId = null,
        bool $cloneName = true
    ): Workflow {
        return DB::transaction(function () use ($workflow, $userId, $cloneName) {
            // Create new workflow
            $newWorkflow = Workflow::create([
                'user_id' => $userId ?? auth()->id(),
                'name' => $cloneName ? $workflow->name . ' (Copy)' : $workflow->name,
                'description' => $workflow->description,
                'category' => $workflow->category,
                'visibility' => 'private', // Always private on clone
                'is_template' => false, // Not a template by default
                'version' => '1.0', // Reset version
                'config' => $workflow->config,
                'metadata' => $workflow->metadata,
                'status' => 'draft', // Start as draft
            ]);

            // Clone steps
            foreach ($workflow->steps as $step) {
                $newWorkflow->steps()->create([
                    'step_order' => $step->step_order,
                    'name' => $step->name,
                    'type' => $step->type,
                    'engine_id' => $step->engine_id,
                    'prompt_template' => $step->prompt_template,
                    'system_prompt' => $step->system_prompt,
                    'config' => $step->config,
                    'input_mapping' => $step->input_mapping,
                    'output_key' => $step->output_key,
                    'condition_script' => $step->condition_script,
                    'error_handling' => $step->error_handling,
                ]);
            }

            return $newWorkflow->load('steps');
        });
    }

    /**
     * Execute a workflow
     */
    public function execute(
        Workflow $workflow,
        array $inputData,
        ?int $personaId = null,
        ?int $brandId = null
    ): WorkflowExecution {
        // Validate workflow is active
        if (!$workflow->isActive()) {
            throw new \RuntimeException('Workflow must be active to execute');
        }

        // Validate workflow has steps
        $totalSteps = $workflow->steps()->count();
        if ($totalSteps === 0) {
            throw new \RuntimeException('Workflow must have at least one step');
        }

        // Create execution
        return WorkflowExecution::create([
            'workflow_id' => $workflow->id,
            'user_id' => auth()->id(),
            'persona_id' => $personaId,
            'brand_id' => $brandId,
            'total_steps' => $totalSteps,
            'input_data' => $inputData,
            'context' => [],
            'status' => 'pending',
        ]);
    }

    /**
     * Cancel a running execution
     */
    public function cancelExecution(WorkflowExecution $execution): WorkflowExecution
    {
        if (!$execution->isRunning()) {
            throw new \RuntimeException('Only running executions can be cancelled');
        }

        $execution->cancel();

        return $execution->fresh();
    }

    /**
     * Get execution progress
     */
    public function getExecutionProgress(WorkflowExecution $execution): array
    {
        return [
            'execution_id' => $execution->id,
            'workflow_name' => $execution->workflow->name,
            'status' => $execution->status,
            'current_step' => $execution->current_step,
            'total_steps' => $execution->total_steps,
            'progress_percentage' => $execution->getProgressPercentage(),
            'started_at' => $execution->started_at?->toIso8601String(),
            'completed_at' => $execution->completed_at?->toIso8601String(),
            'execution_time' => $execution->execution_time,
            'tokens_used' => $execution->tokens_used,
            'cost_estimate' => $execution->cost_estimate,
            'error_message' => $execution->error_message,
        ];
    }

    /**
     * Get user's workflows
     */
    public function getUserWorkflows(
        ?int $userId = null,
        ?string $category = null,
        ?string $status = null
    ): Collection {
        $query = Workflow::query()
            ->forUser($userId ?? auth()->id())
            ->with('steps');

        if ($category) {
            $query->byCategory($category);
        }

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('updated_at', 'desc')->get();
    }

    /**
     * Get public workflow templates
     */
    public function getPublicTemplates(?string $category = null): Collection
    {
        $query = Workflow::query()
            ->public()
            ->templates()
            ->with('steps');

        if ($category) {
            $query->byCategory($category);
        }

        return $query->orderBy('execution_count', 'desc')->get();
    }

    /**
     * Convert workflow to template
     */
    public function convertToTemplate(Workflow $workflow): Workflow
    {
        $workflow->update([
            'is_template' => true,
            'visibility' => 'public',
        ]);

        return $workflow->fresh();
    }

    /**
     * Create workflow from template
     */
    public function createFromTemplate(Workflow $template, ?int $userId = null): Workflow
    {
        if (!$template->is_template) {
            throw new \RuntimeException('Source workflow must be a template');
        }

        return $this->clone($template, $userId, false);
    }

    /**
     * Validate workflow data
     */
    protected function validateWorkflowData(array $data, bool $isUpdate = false): void
    {
        $rules = [
            'name' => $isUpdate ? 'sometimes|string|max:255' : 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'visibility' => 'nullable|in:private,team,public',
            'is_template' => 'nullable|boolean',
            'version' => 'nullable|string|max:20',
            'config' => 'nullable|array',
            'metadata' => 'nullable|array',
            'status' => 'nullable|in:draft,active,archived',
            'steps' => 'nullable|array',
            'steps.*.step_order' => 'nullable|integer|min:1',
            'steps.*.name' => 'required|string|max:255',
            'steps.*.type' => 'required|in:text,image,video,audio,code,decision,transform',
            'steps.*.engine_id' => 'nullable|string|max:100',
            'steps.*.prompt_template' => 'required|string',
            'steps.*.system_prompt' => 'nullable|string',
            'steps.*.config' => 'nullable|array',
            'steps.*.input_mapping' => 'nullable|array',
            'steps.*.output_key' => 'nullable|string|max:100',
            'steps.*.condition_script' => 'nullable|string',
            'steps.*.error_handling' => 'nullable|array',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Validate step data
     */
    protected function validateStepData(array $data, bool $isUpdate = false): void
    {
        $rules = [
            'step_order' => 'nullable|integer|min:1',
            'name' => $isUpdate ? 'sometimes|string|max:255' : 'required|string|max:255',
            'type' => $isUpdate ? 'sometimes|in:text,image,video,audio,code,decision,transform' : 'required|in:text,image,video,audio,code,decision,transform',
            'engine_id' => 'nullable|string|max:100',
            'prompt_template' => $isUpdate ? 'sometimes|string' : 'required|string',
            'system_prompt' => 'nullable|string',
            'config' => 'nullable|array',
            'input_mapping' => 'nullable|array',
            'output_key' => 'nullable|string|max:100',
            'condition_script' => 'nullable|string',
            'error_handling' => 'nullable|array',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Sync steps (delete removed, update existing, create new)
     */
    protected function syncSteps(Workflow $workflow, array $stepsData): void
    {
        $existingStepIds = $workflow->steps()->pluck('id')->toArray();
        $updatedStepIds = [];

        foreach ($stepsData as $stepData) {
            if (isset($stepData['id']) && in_array($stepData['id'], $existingStepIds)) {
                // Update existing step
                $step = $workflow->steps()->find($stepData['id']);
                $this->updateStep($step, $stepData);
                $updatedStepIds[] = $stepData['id'];
            } else {
                // Create new step
                $newStep = $this->addStep($workflow, $stepData);
                $updatedStepIds[] = $newStep->id;
            }
        }

        // Delete removed steps
        $stepsToDelete = array_diff($existingStepIds, $updatedStepIds);
        if (!empty($stepsToDelete)) {
            $workflow->steps()->whereIn('id', $stepsToDelete)->delete();
        }

        // Reorder steps
        $this->reorderSteps($workflow);
    }
}

<?php

namespace App\Services\Workflow;

use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowStepExecution;
use App\Jobs\ProcessWorkflowStepJob;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Workflow Orchestrator
 *
 * Coordinates workflow execution across multiple steps
 */
class WorkflowOrchestrator
{
    protected WorkflowStepExecutor $stepExecutor;

    public function __construct(WorkflowStepExecutor $stepExecutor)
    {
        $this->stepExecutor = $stepExecutor;
    }

    /**
     * Start a new workflow execution
     *
     * @param Workflow $workflow The workflow to execute
     * @param array $inputData Input data for the workflow
     * @param int|null $userId User ID
     * @param int|null $personaId AI Persona ID (optional)
     * @param int|null $brandId Brand/Company ID (optional)
     * @return WorkflowExecution
     */
    public function startWorkflow(
        Workflow $workflow,
        array $inputData,
        ?int $userId = null,
        ?int $personaId = null,
        ?int $brandId = null
    ): WorkflowExecution {
        Log::info('Starting workflow execution', [
            'workflow_id' => $workflow->id,
            'workflow_name' => $workflow->name,
            'user_id' => $userId,
        ]);

        // Validate workflow is active
        if ($workflow->status !== 'active') {
            throw new Exception("Workflow is not active: {$workflow->name}");
        }

        // Get workflow steps
        $steps = $workflow->steps()->orderBy('step_order')->get();

        // Create execution record
        $execution = WorkflowExecution::create([
            'workflow_id' => $workflow->id,
            'user_id' => $userId,
            'persona_id' => $personaId,
            'brand_id' => $brandId,
            'status' => 'pending',
            'current_step' => 0,
            'total_steps' => $steps->count(),
            'input_data' => $inputData,
            'context' => [],
            'started_at' => now(),
        ]);

        foreach ($steps as $step) {
            WorkflowStepExecution::create([
                'execution_id' => $execution->id,
                'step_id' => $step->id,
                'status' => 'pending',
                'input_data' => [],
            ]);
        }

        Log::info('Workflow execution created', [
            'execution_id' => $execution->id,
            'total_steps' => $steps->count(),
        ]);

        return $execution;
    }

    /**
     * Process a workflow execution
     *
     * Executes steps sequentially (for now)
     *
     * @param WorkflowExecution $execution
     * @return void
     */
    public function processWorkflow(WorkflowExecution $execution): void
    {
        Log::info('Processing workflow', [
            'execution_id' => $execution->id,
            'workflow_name' => $execution->workflow->name ?? 'Unknown',
        ]);

        // Get all step executions in order
        $stepExecutions = $execution->stepExecutions()
            ->with('step')
            ->join('workflow_steps', 'workflow_step_executions.step_id', '=', 'workflow_steps.id')
            ->orderBy('workflow_steps.step_order')
            ->select('workflow_step_executions.*')
            ->get();

        $execution->update(['status' => 'running']);

        try {
            foreach ($stepExecutions as $stepExecution) {
                // Execute step synchronously
                $this->executeStep($stepExecution);

                // Check if step failed
                if ($stepExecution->status === 'failed') {
                    throw new Exception("Step failed: {$stepExecution->step->name}");
                }
            }

            // Mark workflow as completed
            $execution->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            Log::info('Workflow completed successfully', [
                'execution_id' => $execution->id,
                'duration' => $execution->completed_at->diffInSeconds($execution->started_at),
            ]);
        } catch (Exception $e) {
            Log::error('Workflow execution failed', [
                'execution_id' => $execution->id,
                'error' => $e->getMessage(),
            ]);

            $execution->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Execute a single workflow step
     *
     * @param WorkflowStepExecution $stepExecution
     * @return array Step result
     */
    protected function executeStep(WorkflowStepExecution $stepExecution): array
    {
        Log::info('Executing workflow step', [
            'step_execution_id' => $stepExecution->id,
            'step_name' => $stepExecution->step->name ?? 'Unknown',
            'step_order' => $stepExecution->step->step_order ?? 0,
        ]);

        try {
            // Execute the step
            $result = $this->stepExecutor->executeStep($stepExecution);

            return $result;
        } catch (Exception $e) {
            Log::error('Step execution failed', [
                'step_execution_id' => $stepExecution->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Queue workflow execution asynchronously
     *
     * @param WorkflowExecution $execution
     * @return void
     */
    public function queueWorkflow(WorkflowExecution $execution): void
    {
        Log::info('Queueing workflow for async execution', [
            'execution_id' => $execution->id,
        ]);

        \App\Jobs\ProcessWorkflowJob::dispatch($execution);
    }

    /**
     * Get workflow execution status
     *
     * @param int $executionId
     * @return array Status information
     */
    public function getExecutionStatus(int $executionId): array
    {
        $execution = WorkflowExecution::with(['stepExecutions.step'])->findOrFail($executionId);

        $stepStatuses = $execution->stepExecutions->map(function ($stepExecution) {
            return [
                'step_order' => $stepExecution->step->step_order ?? 0,
                'step_name' => $stepExecution->step->name ?? 'Unknown',
                'status' => $stepExecution->status,
                'started_at' => $stepExecution->started_at,
                'completed_at' => $stepExecution->completed_at,
                'error_message' => $stepExecution->error_message,
            ];
        })->sortBy('step_order')->values();

        return [
            'execution_id' => $execution->id,
            'workflow_id' => $execution->workflow_id,
            'workflow_name' => $execution->workflow->name ?? 'Unknown',
            'status' => $execution->status,
            'started_at' => $execution->started_at,
            'completed_at' => $execution->completed_at,
            'error_message' => $execution->error_message,
            'steps' => $stepStatuses,
            'progress' => [
                'total' => $stepStatuses->count(),
                'completed' => $stepStatuses->where('status', 'completed')->count(),
                'failed' => $stepStatuses->where('status', 'failed')->count(),
                'running' => $stepStatuses->where('status', 'running')->count(),
                'pending' => $stepStatuses->where('status', 'pending')->count(),
            ],
        ];
    }
}

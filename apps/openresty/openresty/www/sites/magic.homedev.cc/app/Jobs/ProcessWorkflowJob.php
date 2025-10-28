<?php

namespace App\Jobs;

use App\Models\WorkflowExecution;
use App\Services\Workflow\WorkflowOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Process Workflow Job
 *
 * Handles async execution of workflow steps
 */
class ProcessWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600; // 10 minutes
    public int $maxExceptions = 2;
    public bool $failOnTimeout = true;

    protected WorkflowExecution $execution;

    /**
     * Create a new job instance.
     */
    public function __construct(WorkflowExecution $execution)
    {
        $this->execution = $execution;

        // Set queue based on workflow type
        $this->onQueue($this->determineQueue());
    }

    /**
     * Execute the job.
     */
    public function handle(WorkflowOrchestrator $orchestrator): void
    {
        Log::info('Processing workflow job', [
            'execution_id' => $this->execution->id,
            'workflow_id' => $this->execution->workflow_id,
            'workflow_name' => $this->execution->workflow->name ?? 'Unknown',
        ]);

        try {
            // Update status to running
            $this->execution->update(['status' => 'running']);

            // Process the workflow
            $orchestrator->processWorkflow($this->execution);

            Log::info('Workflow job completed successfully', [
                'execution_id' => $this->execution->id,
                'duration' => $this->execution->duration,
            ]);
        } catch (Exception $e) {
            Log::error('Workflow job failed', [
                'execution_id' => $this->execution->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update execution status to failed
            $this->execution->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            // Re-throw to trigger retry logic
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('Workflow job failed permanently', [
            'execution_id' => $this->execution->id,
            'error' => $exception->getMessage(),
        ]);

        $this->execution->update([
            'status' => 'failed',
            'error_message' => 'Job failed after ' . $this->tries . ' attempts: ' . $exception->getMessage(),
        ]);
    }

    /**
     * Determine which queue to use based on workflow characteristics
     */
    protected function determineQueue(): string
    {
        $workflow = $this->execution->workflow;

        if (!$workflow) {
            return 'default';
        }

        // Check if workflow involves video processing
        if ($workflow->steps()->where('type', 'video')->exists() ||
            $workflow->steps()->where('type', 'code')->exists()) {
            return 'video';
        }

        // Otherwise use workflows queue
        return 'workflows';
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'workflow:' . $this->execution->workflow_id,
            'execution:' . $this->execution->id,
            'user:' . $this->execution->user_id,
        ];
    }
}

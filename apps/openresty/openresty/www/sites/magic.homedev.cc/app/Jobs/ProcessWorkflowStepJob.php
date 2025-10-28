<?php

namespace App\Jobs;

use App\Models\WorkflowStepExecution;
use App\Services\Workflow\WorkflowStepExecutor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Process Workflow Step Job
 *
 * Handles async execution of individual workflow steps
 */
class ProcessWorkflowStepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 1800; // 30 minutes for video processing
    public int $maxExceptions = 2;

    protected WorkflowStepExecution $stepExecution;

    /**
     * Create a new job instance.
     */
    public function __construct(WorkflowStepExecution $stepExecution)
    {
        $this->stepExecution = $stepExecution;

        // Set queue and timeout based on step type
        $this->configureForStepType();
    }

    /**
     * Execute the job.
     */
    public function handle(WorkflowStepExecutor $executor): void
    {
        Log::info('Processing workflow step', [
            'step_execution_id' => $this->stepExecution->id,
            'step_name' => $this->stepExecution->step->name ?? 'Unknown',
            'step_type' => $this->stepExecution->step->type ?? 'Unknown',
        ]);

        try {
            // Execute the step
            $result = $executor->executeStep($this->stepExecution);

            Log::info('Workflow step completed', [
                'step_execution_id' => $this->stepExecution->id,
                'result_type' => $result['type'] ?? 'unknown',
            ]);
        } catch (Exception $e) {
            Log::error('Workflow step failed', [
                'step_execution_id' => $this->stepExecution->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('Workflow step failed permanently', [
            'step_execution_id' => $this->stepExecution->id,
            'error' => $exception->getMessage(),
        ]);

        $this->stepExecution->fail($exception->getMessage());
    }

    /**
     * Configure job based on step type
     */
    protected function configureForStepType(): void
    {
        $step = $this->stepExecution->step;

        if (!$step) {
            return;
        }

        switch ($step->type) {
            case 'video':
                $this->onQueue('video');
                $this->timeout = 1800; // 30 minutes
                $this->tries = 1; // Video generation is expensive, don't retry
                break;

            case 'code':
                $this->onQueue('video'); // FFmpeg on video queue
                $this->timeout = 900; // 15 minutes
                $this->tries = 2;
                break;

            case 'audio':
                $this->onQueue('workflows');
                $this->timeout = 600; // 10 minutes
                break;

            case 'image':
                $this->onQueue('workflows');
                $this->timeout = 300; // 5 minutes
                break;

            default:
                $this->onQueue('workflows');
                $this->timeout = 300;
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'workflow-step',
            'step:' . $this->stepExecution->step_id,
            'execution:' . $this->stepExecution->execution_id,
            'type:' . ($this->stepExecution->step->type ?? 'unknown'),
        ];
    }
}

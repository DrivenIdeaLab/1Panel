<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class WorkflowStepExecution extends Model
{
    use HasFactory;

    protected $fillable = [
        'execution_id',
        'step_id',
        'status',
        'input_data',
        'output_data',
        'tokens_used',
        'execution_time',
        'error_message',
        'retry_count',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'input_data' => 'array',
        'output_data' => 'array',
        'tokens_used' => 'integer',
        'execution_time' => 'integer',
        'retry_count' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'pending',
        'retry_count' => 0,
    ];

    // Relationships
    public function execution(): BelongsTo
    {
        return $this->belongsTo(WorkflowExecution::class, 'execution_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'step_id');
    }

    // Scopes
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', 'running');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeSkipped(Builder $query): Builder
    {
        return $query->where('status', 'skipped');
    }

    public function scopeForExecution(Builder $query, int $executionId): Builder
    {
        return $query->where('execution_id', $executionId);
    }

    // Helper methods
    public function start(array $inputData): void
    {
        $this->update([
            'status' => 'running',
            'input_data' => $inputData,
            'started_at' => now(),
        ]);
    }

    public function complete(array $outputData, int $tokensUsed = 0): void
    {
        $executionTime = $this->started_at ? now()->diffInMilliseconds($this->started_at) : 0;

        $this->update([
            'status' => 'completed',
            'output_data' => $outputData,
            'tokens_used' => $tokensUsed,
            'execution_time' => $executionTime,
            'completed_at' => now(),
        ]);

        // Update parent execution token usage
        if ($tokensUsed > 0 && $this->step->engine_id) {
            $this->execution->addTokenUsage($this->step->engine_id, $tokensUsed);
        }
    }

    public function fail(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    public function skip(): void
    {
        $this->update([
            'status' => 'skipped',
            'completed_at' => now(),
        ]);
    }

    public function retry(): void
    {
        $this->update([
            'status' => 'pending',
            'retry_count' => $this->retry_count + 1,
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isSkipped(): bool
    {
        return $this->status === 'skipped';
    }

    public function canRetry(): bool
    {
        if (!$this->isFailed()) {
            return false;
        }

        $errorHandling = $this->step->error_handling ?? [];
        $maxRetries = $errorHandling['max_retries'] ?? 0;

        return $this->retry_count < $maxRetries;
    }
}

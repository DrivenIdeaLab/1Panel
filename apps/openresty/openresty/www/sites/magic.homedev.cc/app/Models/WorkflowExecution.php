<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class WorkflowExecution extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'workflow_id',
        'user_id',
        'persona_id',
        'brand_id',
        'status',
        'current_step',
        'total_steps',
        'context',
        'input_data',
        'output_data',
        'error_message',
        'started_at',
        'completed_at',
        'execution_time',
        'tokens_used',
        'cost_estimate',
    ];

    protected $casts = [
        'context' => 'array',
        'input_data' => 'array',
        'output_data' => 'array',
        'tokens_used' => 'array',
        'current_step' => 'integer',
        'total_steps' => 'integer',
        'execution_time' => 'integer',
        'cost_estimate' => 'decimal:4',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'pending',
        'current_step' => 0,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($execution) {
            if (empty($execution->uuid)) {
                $execution->uuid = (string) Str::uuid();
            }
        });
    }

    // Relationships
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(AIPersona::class, 'persona_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'brand_id');
    }

    public function stepExecutions(): HasMany
    {
        return $this->hasMany(WorkflowStepExecution::class, 'execution_id');
    }

    public function mediaJobs(): HasMany
    {
        return $this->hasMany(MediaProcessingJob::class, 'execution_id');
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

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForWorkflow(Builder $query, int $workflowId): Builder
    {
        return $query->where('workflow_id', $workflowId);
    }

    // Helper methods
    public function start(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    public function complete(array $outputData = []): void
    {
        $executionTime = $this->started_at ? now()->diffInSeconds($this->started_at) : 0;

        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'execution_time' => $executionTime,
            'output_data' => $outputData,
        ]);

        // Update workflow statistics
        $this->workflow->incrementExecutionCount();
        $this->workflow->updateAverageExecutionTime($executionTime);
    }

    public function fail(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }

    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled',
            'completed_at' => now(),
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

    public function updateProgress(int $currentStep): void
    {
        $this->update(['current_step' => $currentStep]);
    }

    public function getProgressPercentage(): float
    {
        if ($this->total_steps === 0) {
            return 0;
        }

        return round(($this->current_step / $this->total_steps) * 100, 2);
    }

    public function addTokenUsage(string $engine, int $tokens): void
    {
        $tokensUsed = $this->tokens_used ?? [];
        $tokensUsed[$engine] = ($tokensUsed[$engine] ?? 0) + $tokens;
        $this->update(['tokens_used' => $tokensUsed]);
    }
}

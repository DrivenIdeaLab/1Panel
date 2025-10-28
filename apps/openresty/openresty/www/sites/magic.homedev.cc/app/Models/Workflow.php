<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Workflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'name',
        'description',
        'category',
        'visibility',
        'is_template',
        'version',
        'config',
        'metadata',
        'execution_count',
        'avg_execution_time',
        'status',
    ];

    protected $casts = [
        'config' => 'array',
        'metadata' => 'array',
        'is_template' => 'boolean',
        'execution_count' => 'integer',
        'avg_execution_time' => 'integer',
    ];

    protected $attributes = [
        'visibility' => 'private',
        'is_template' => false,
        'status' => 'draft',
        'version' => '1.0',
        'execution_count' => 0,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($workflow) {
            if (empty($workflow->uuid)) {
                $workflow->uuid = (string) Str::uuid();
            }
        });
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('step_order');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(WorkflowExecution::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('visibility', 'public');
    }

    public function scopeTemplates(Builder $query): Builder
    {
        return $query->where('is_template', true);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }

    public function incrementExecutionCount(): void
    {
        $this->increment('execution_count');
    }

    public function updateAverageExecutionTime(int $executionTime): void
    {
        $totalExecutions = $this->execution_count;
        if ($totalExecutions > 0) {
            $currentAvg = $this->avg_execution_time ?? 0;
            $newAvg = (($currentAvg * ($totalExecutions - 1)) + $executionTime) / $totalExecutions;
            $this->update(['avg_execution_time' => round($newAvg)]);
        }
    }
}

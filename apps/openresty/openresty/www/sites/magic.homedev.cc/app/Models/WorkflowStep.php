<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class WorkflowStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'step_order',
        'name',
        'type',
        'engine_id',
        'prompt_template',
        'system_prompt',
        'config',
        'input_mapping',
        'output_key',
        'condition_script',
        'error_handling',
    ];

    protected $casts = [
        'config' => 'array',
        'input_mapping' => 'array',
        'error_handling' => 'array',
        'step_order' => 'integer',
    ];

    // Relationships
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(WorkflowStepExecution::class, 'step_id');
    }

    // Scopes
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('step_order');
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeForWorkflow(Builder $query, int $workflowId): Builder
    {
        return $query->where('workflow_id', $workflowId);
    }

    // Helper methods
    public function isTextStep(): bool
    {
        return $this->type === 'text';
    }

    public function isImageStep(): bool
    {
        return $this->type === 'image';
    }

    public function isVideoStep(): bool
    {
        return $this->type === 'video';
    }

    public function isAudioStep(): bool
    {
        return $this->type === 'audio';
    }

    public function isDecisionStep(): bool
    {
        return $this->type === 'decision';
    }

    public function hasCondition(): bool
    {
        return !empty($this->condition_script);
    }

    public function getInputVariables(): array
    {
        return $this->input_mapping ?? [];
    }

    public function renderPrompt(array $context): string
    {
        $prompt = $this->prompt_template;

        foreach ($context as $key => $value) {
            $prompt = str_replace("{{" . $key . "}}", $value, $prompt);
        }

        return $prompt;
    }
}

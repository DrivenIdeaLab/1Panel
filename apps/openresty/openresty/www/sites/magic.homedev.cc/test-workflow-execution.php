<?php

/**
 * Test Workflow Execution Script
 *
 * Demonstrates the complete FFmpeg production system
 * Tests: Product Description Generator (Workflow 5)
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Workflow;
use App\Models\User;
use App\Services\Workflow\WorkflowOrchestrator;
use Illuminate\Support\Facades\Log;

echo "\n";
echo "==============================================\n";
echo "  MagicAI Workflow Execution Test\n";
echo "==============================================\n\n";

try {
    // Get orchestrator
    $orchestrator = app(WorkflowOrchestrator::class);

    // Get first user (or create test user)
    $user = User::first();
    if (!$user) {
        echo "❌ No user found. Please create a user first.\n";
        exit(1);
    }

    echo "👤 User: {$user->name} (ID: {$user->id})\n\n";

    // Test 1: Simple Text Workflow
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "TEST 1: Product Description Generator\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    $workflow = Workflow::find(5);

    if (!$workflow) {
        echo "❌ Workflow 5 (Product Description Generator) not found\n";
        exit(1);
    }

    echo "📋 Workflow: {$workflow->name}\n";
    echo "📊 Steps: {$workflow->steps()->count()} steps\n";
    echo "📝 Status: {$workflow->status}\n\n";

    // Show workflow steps
    echo "Workflow Steps:\n";
    foreach ($workflow->steps()->orderBy('step_order')->get() as $step) {
        $engineName = $step->engine_id ? "Engine #{$step->engine_id}" : "No Engine";
        echo "  {$step->step_order}. {$step->name} ({$step->type}) - {$engineName}\n";
    }
    echo "\n";

    // Prepare input data
    $inputData = [
        'product_name' => 'AI-Powered Smart Watch Pro',
        'features' => 'Heart rate monitoring, GPS tracking, Sleep analysis, 7-day battery life, Waterproof (5ATM), Voice assistant, Fitness tracking',
        'target_audience' => 'Health-conscious professionals and fitness enthusiasts',
        'tone' => 'Professional yet friendly, highlighting innovation and lifestyle benefits',
    ];

    echo "📥 Input Data:\n";
    echo "  Product: {$inputData['product_name']}\n";
    echo "  Features: {$inputData['features']}\n";
    echo "  Audience: {$inputData['target_audience']}\n\n";

    // Start workflow execution
    echo "🚀 Starting workflow execution...\n";
    $execution = $orchestrator->startWorkflow(
        $workflow,
        $inputData,
        $user->id
    );

    echo "✅ Workflow execution created!\n";
    echo "   Execution ID: {$execution->id}\n";
    echo "   Status: {$execution->status}\n";
    echo "   Started at: {$execution->started_at}\n\n";

    // Process workflow synchronously (for demo purposes)
    echo "⚙️  Processing workflow synchronously...\n";
    echo "   (This will take 2-3 minutes)\n\n";

    $startTime = microtime(true);

    try {
        $orchestrator->processWorkflow($execution);

        $duration = round(microtime(true) - $startTime, 2);

        // Refresh execution from database
        $execution->refresh();

        echo "\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "✅ WORKFLOW COMPLETED SUCCESSFULLY!\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        echo "📊 Execution Summary:\n";
        echo "   Status: {$execution->status}\n";
        echo "   Duration: {$duration} seconds\n";
        echo "   Completed at: {$execution->completed_at}\n\n";

        // Show step results
        echo "📝 Step Results:\n\n";
        foreach ($execution->stepExecutions()->with('step')->get() as $stepExec) {
            echo "  Step {$stepExec->step->step_order}: {$stepExec->step->name}\n";
            echo "  Status: {$stepExec->status}\n";

            if ($stepExec->output_data) {
                $output = $stepExec->output_data;
                if (isset($output['content'])) {
                    $preview = substr($output['content'], 0, 100);
                    echo "  Output: {$preview}...\n";
                }
                if (isset($output['tokens_used'])) {
                    echo "  Tokens: {$output['tokens_used']}\n";
                }
            }
            echo "\n";
        }

        // Show final context
        if ($execution->context) {
            echo "📦 Final Context Keys:\n";
            foreach (array_keys($execution->context) as $key) {
                echo "  - {$key}\n";
            }
            echo "\n";
        }

        echo "✨ Test completed successfully!\n\n";

    } catch (Exception $e) {
        $duration = round(microtime(true) - $startTime, 2);

        echo "\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "❌ WORKFLOW FAILED\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        echo "Error: {$e->getMessage()}\n";
        echo "Duration: {$duration} seconds\n\n";

        // Show step statuses
        $execution->refresh();
        echo "Step Status:\n";
        foreach ($execution->stepExecutions()->with('step')->get() as $stepExec) {
            $icon = match($stepExec->status) {
                'completed' => '✅',
                'failed' => '❌',
                'running' => '⚙️',
                'pending' => '⏳',
                default => '❓',
            };
            echo "  {$icon} Step {$stepExec->step->step_order}: {$stepExec->step->name} ({$stepExec->status})\n";
            if ($stepExec->error_message) {
                echo "     Error: {$stepExec->error_message}\n";
            }
        }
        echo "\n";

        throw $e;
    }

} catch (Exception $e) {
    echo "\n❌ Error: {$e->getMessage()}\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n";
echo "==============================================\n";
echo "  Test Complete\n";
echo "==============================================\n\n";

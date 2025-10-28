<?php

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "\n";
echo "==============================================\n";
echo "  MagicAI Queue System Test\n";
echo "==============================================\n\n";

use App\Models\User;
use App\Models\Workflow;
use App\Jobs\ProcessWorkflowJob;
use App\Services\Workflow\WorkflowOrchestrator;
use App\Services\Workflow\WorkflowStepExecutor;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

// Get admin user
$user = User::first();
if (!$user) {
    echo "❌ No user found in database\n";
    exit(1);
}

echo "👤 User: {$user->name} (ID: {$user->id})\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST 1: Redis Connection\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

try {
    Redis::connection()->ping();
    echo "✅ Redis connection: OK\n";

    // Get queue stats
    $workflowQueueSize = Redis::connection()->llen('queues:workflows');
    $videoQueueSize = Redis::connection()->llen('queues:video');

    echo "📊 Queue Status:\n";
    echo "   - workflows queue: {$workflowQueueSize} jobs\n";
    echo "   - video queue: {$videoQueueSize} jobs\n\n";

} catch (\Exception $e) {
    echo "❌ Redis connection failed: {$e->getMessage()}\n\n";
    exit(1);
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST 2: Dispatch Workflow Job\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Get a workflow
$workflow = Workflow::where('status', 'active')->first();

if (!$workflow) {
    echo "❌ No active workflow found\n";
    exit(1);
}

echo "📋 Workflow: {$workflow->name} (ID: {$workflow->id})\n";
echo "📊 Steps: {$workflow->steps_count} steps\n\n";

echo "🚀 Creating workflow execution and dispatching job...\n";

$inputData = [
    'product_name' => 'AI-Powered Smart Watch Pro',
    'features' => 'Heart rate monitoring, GPS tracking, Sleep analysis',
    'audience' => 'Health-conscious professionals'
];

try {
    // Create execution
    $stepExecutor = new WorkflowStepExecutor();
    $orchestrator = new WorkflowOrchestrator($stepExecutor);
    $execution = $orchestrator->startWorkflow($workflow, $inputData, $user->id);

    echo "✅ Workflow execution created (ID: {$execution->id})\n";

    // Dispatch job
    $job = new ProcessWorkflowJob($execution);
    dispatch($job->onQueue('workflows'));

    echo "✅ Job dispatched to queue successfully!\n\n";

    // Wait a moment and check queue
    sleep(2);

    $queueSize = Redis::connection()->llen('queues:workflows');
    echo "📊 Workflow queue size after dispatch: {$queueSize} job(s)\n\n";

    echo "💡 Queue workers should process this job automatically.\n";
    echo "   Check: ps aux | grep 'queue:work'\n\n";

} catch (\Exception $e) {
    echo "❌ Failed to dispatch job: {$e->getMessage()}\n\n";
    exit(1);
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST 3: Queue Worker Status\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$workers = shell_exec("ps aux | grep 'queue:work' | grep -v grep");

if ($workers) {
    echo "✅ Active queue workers:\n\n";
    echo $workers;
    echo "\n";
} else {
    echo "⚠️  No active queue workers detected\n";
    echo "   Start workers with: php artisan queue:work\n\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST 4: Monitor Job Progress\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Waiting 10 seconds for job to process...\n\n";

for ($i = 1; $i <= 10; $i++) {
    sleep(1);
    $queueSize = Redis::connection()->llen('queues:workflows');
    echo "   [{$i}/10] Queue size: {$queueSize}\n";

    if ($queueSize == 0 && $i > 2) {
        echo "\n✅ Queue processed!\n\n";
        break;
    }
}

// Check latest execution
$latestExecution = \App\Models\WorkflowExecution::latest()->first();

if ($latestExecution) {
    echo "📊 Latest Execution:\n";
    echo "   ID: {$latestExecution->id}\n";
    echo "   Workflow: " . ($latestExecution->workflow->name ?? 'N/A') . "\n";
    echo "   Status: {$latestExecution->status}\n";
    echo "   Started: {$latestExecution->started_at}\n";

    if ($latestExecution->completed_at) {
        $duration = $latestExecution->started_at->diffInSeconds($latestExecution->completed_at);
        echo "   Completed: {$latestExecution->completed_at}\n";
        echo "   Duration: {$duration}s\n";
    }

    // Count completed steps
    $completedSteps = $latestExecution->stepExecutions()->where('status', 'completed')->count();
    $totalSteps = $latestExecution->total_steps;

    echo "   Progress: {$completedSteps}/{$totalSteps} steps\n\n";
}

echo "==============================================\n";
echo "✅ Queue System Test Complete\n";
echo "==============================================\n\n";

<?php

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "\n";
echo "==============================================\n";
echo "  MagicAI FFmpeg Workflow Execution Test\n";
echo "==============================================\n\n";

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowExecution;
use App\Models\WorkflowStepExecution;
use App\Services\Workflow\WorkflowStepExecutor;

// Get admin user
$user = User::first();
if (!$user) {
    echo "❌ No user found in database\n";
    exit(1);
}
echo "👤 User: {$user->name} (ID: {$user->id})\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST: FFmpeg Code Step Execution\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Create a test workflow step with bash script
$step = new WorkflowStep();
$step->id = 999;
$step->name = "Test FFmpeg Script";
$step->type = 'code';
$step->engine_id = null; // Not needed for code steps
$step->prompt_template = '';
$step->system_prompt_template = '';
$step->config = json_encode([
    'language' => 'bash',
    'script' => <<<'BASH'
#!/bin/bash

echo "=== FFmpeg Test Script ==="
echo ""

# Test 1: Check FFmpeg is available
echo "1. Checking FFmpeg installation..."
if command -v ffmpeg &> /dev/null; then
    echo "   ✓ FFmpeg is installed"
    ffmpeg -version | head -1
else
    echo "   ✗ FFmpeg not found"
    exit 1
fi

echo ""

# Test 2: Get FFmpeg capabilities
echo "2. FFmpeg capabilities:"
ffmpeg -codecs 2>&1 | grep -E "(h264|aac|mp3)" | head -5

echo ""

# Test 3: Create a test video (1 second, black screen with audio tone)
echo "3. Creating test video..."
OUTPUT="/tmp/ffmpeg_test_$(date +%s).mp4"

ffmpeg -f lavfi -i color=black:s=1280x720:d=1 \
       -f lavfi -i sine=frequency=440:duration=1 \
       -c:v libx264 -pix_fmt yuv420p \
       -c:a aac -shortest \
       "$OUTPUT" -y 2>&1 | tail -10

if [ -f "$OUTPUT" ]; then
    SIZE=$(stat -f%z "$OUTPUT" 2>/dev/null || stat -c%s "$OUTPUT" 2>/dev/null)
    echo "   ✓ Test video created: $OUTPUT ($SIZE bytes)"

    # Get video info
    echo ""
    echo "4. Video information:"
    ffprobe -v quiet -print_format json -show_format -show_streams "$OUTPUT" | head -30

    # Cleanup
    rm -f "$OUTPUT"
    echo ""
    echo "   ✓ Test file cleaned up"
else
    echo "   ✗ Failed to create test video"
    exit 1
fi

echo ""
echo "=== All Tests Passed ==="
BASH
]);

// Create mock execution
$execution = new WorkflowExecution();
$execution->id = 999;
$execution->user_id = $user->id;
$execution->context = [];
$execution->input_data = [];
$execution->status = 'running';

echo "📝 Test Configuration:\n";
echo "   Step: {$step->name}\n";
echo "   Type: {$step->type}\n";
echo "   Language: bash\n";
echo "   Script: FFmpeg installation and capability test\n\n";

echo "🚀 Executing bash script...\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$startTime = microtime(true);

try {
    $executor = new WorkflowStepExecutor();
    $stepExecution = new WorkflowStepExecution();
    $stepExecution->step = $step;
    $stepExecution->execution = $execution;

    $result = $executor->executeStep($stepExecution);

    $duration = round(microtime(true) - $startTime, 2);

    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "✅ SCRIPT EXECUTED SUCCESSFULLY!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    echo "📊 Execution Summary:\n";
    echo "   Type: {$result['type']}\n";
    echo "   Status: success\n";
    echo "   Duration: {$duration} seconds\n";
    if (isset($result['exit_code'])) {
        echo "   Exit Code: {$result['exit_code']}\n";
    }
    if (isset($result['output'])) {
        echo "   Output Lines: " . substr_count($result['output'], "\n") . "\n";
    }
    echo "\n";

    if (!empty($result['error_output'])) {
        echo "⚠️  Warnings/Errors:\n";
        echo "   " . str_replace("\n", "\n   ", trim($result['error_output'])) . "\n\n";
    }

    if (isset($result['output']) && !empty($result['output'])) {
        echo "📝 Full Output:\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo $result['output'];
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    }

    if (isset($result['content']) && !empty($result['content'])) {
        echo "📝 Content:\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo $result['content'];
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    }

    echo "✨ FFmpeg workflow integration test completed successfully!\n\n";

} catch (\Exception $e) {
    $duration = round(microtime(true) - $startTime, 2);

    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "❌ SCRIPT EXECUTION FAILED\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    echo "⚠️  Error Details:\n";
    echo "   Duration: {$duration} seconds\n";
    echo "   Error: {$e->getMessage()}\n";
    echo "   File: {$e->getFile()}:{$e->getLine()}\n\n";

    echo "Stack Trace:\n";
    echo $e->getTraceAsString() . "\n\n";
}

echo "==============================================\n";
echo "  Test Complete\n";
echo "==============================================\n\n";

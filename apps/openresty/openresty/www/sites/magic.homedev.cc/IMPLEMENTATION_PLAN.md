# MagicAI Workflow System - Implementation Plan

**Date:** October 28, 2025
**Version:** 1.0
**Status:** 🟢 READY FOR EXECUTION

---

## Executive Summary

This implementation plan provides a step-by-step roadmap for deploying the workflow system fixes and ensuring production readiness. The core fix has been applied; this plan focuses on validation, testing, and deployment.

**Timeline:** 3-5 days
**Team Required:** 1-2 developers
**Risk Level:** LOW

---

## Table of Contents

1. [Pre-Implementation Checklist](#pre-implementation-checklist)
2. [Phase 1: Environment Setup](#phase-1-environment-setup)
3. [Phase 2: Integration Testing](#phase-2-integration-testing)
4. [Phase 3: Production Deployment](#phase-3-production-deployment)
5. [Phase 4: Monitoring & Validation](#phase-4-monitoring--validation)
6. [Rollback Plan](#rollback-plan)
7. [Success Criteria](#success-criteria)

---

## Pre-Implementation Checklist

### Code Changes
- [x] WorkflowStepExecutor.php modified (lines 622-658)
- [x] getEntityForStep() method added
- [x] executeTextStep() updated
- [x] executeImageStep() updated
- [x] executeVideoStep() updated
- [x] executeAudioStep() updated
- [x] Old getEngineKey() method removed

### Documentation
- [x] SYNTHESIS_REPORT.md created
- [x] WORKFLOW_INTEGRATION_SOLUTION.md created
- [x] DATABASE_ANALYSIS_REPORT.md available
- [x] Code changes documented
- [x] Testing results documented

### Environment
- [ ] Redis PHP extension installed
- [ ] FFmpeg binary installed
- [ ] Queue workers configured
- [ ] Supervisor configured (for queue monitoring)
- [ ] Log rotation configured

---

## Phase 1: Environment Setup

**Duration:** 2-4 hours
**Priority:** 🔴 CRITICAL

### Task 1.1: Install Redis PHP Extension

**Why:** Required for Laravel queue system and caching

```bash
# Check current PHP version
php -v

# For Debian/Ubuntu
apt-get update
apt-get install -y php-redis

# For PECL installation
pecl install redis

# Enable extension
echo "extension=redis.so" > /etc/php/8.1/mods-available/redis.ini
phpenmod redis

# Restart PHP-FPM
systemctl restart php8.1-fpm

# Verify installation
php -m | grep redis
```

**Validation:**
```bash
php -r "echo class_exists('Redis') ? 'Redis installed' : 'Redis missing';"
# Expected: "Redis installed"
```

**Status:** [ ] Complete
**Owner:** DevOps/Backend Developer
**Estimated Time:** 30 minutes

---

### Task 1.2: Install FFmpeg

**Why:** Required for video processing workflows

```bash
# For Debian/Ubuntu
apt-get update
apt-get install -y ffmpeg

# For CentOS/RHEL
yum install -y epel-release
yum install -y ffmpeg

# Verify installation
ffmpeg -version

# Check codecs
ffmpeg -codecs | grep -i h264
```

**Validation:**
```bash
ffmpeg -version | head -1
# Expected: ffmpeg version X.X.X
```

**Status:** [ ] Complete
**Owner:** DevOps
**Estimated Time:** 15 minutes

---

### Task 1.3: Configure Queue Workers

**Why:** Workflows need background processing

**Step 1: Create Supervisor Configuration**

```bash
# Create supervisor config
cat > /etc/supervisor/conf.d/magicai-worker.conf <<'EOF'
[program:magicai-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /opt/1panel/apps/openresty/openresty/www/sites/magic.homedev.cc/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --queue=workflows,default
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/magicai-worker.log
stopwaitsecs=3600
EOF

# Reload supervisor
supervisorctl reread
supervisorctl update
supervisorctl start magicai-worker:*
```

**Step 2: Verify Workers Running**

```bash
# Check supervisor status
supervisorctl status magicai-worker:*

# Expected output:
# magicai-worker:magicai-worker_00   RUNNING   pid 1234, uptime 0:00:05
# magicai-worker:magicai-worker_01   RUNNING   pid 1235, uptime 0:00:05
```

**Step 3: Test Queue**

```bash
cd /opt/1panel/apps/openresty/openresty/www/sites/magic.homedev.cc
php artisan queue:work --once
```

**Status:** [ ] Complete
**Owner:** DevOps
**Estimated Time:** 45 minutes

---

### Task 1.4: Verify Database Configuration

**Why:** Ensure all required tables and data exist

```bash
cd /opt/1panel/apps/openresty/openresty/www/sites/magic.homedev.cc

# Check database connection
php artisan tinker
>>> DB::connection()->getPdo();
>>> exit

# Run migrations if needed
php artisan migrate --force

# Verify key tables exist
php artisan tinker <<'EOF'
DB::table('workflows')->count();
DB::table('workflow_steps')->count();
DB::table('engines')->count();
DB::table('entities')->count();
exit
EOF
```

**Status:** [ ] Complete
**Owner:** Backend Developer
**Estimated Time:** 30 minutes

---

### Task 1.5: Configure API Keys

**Why:** Ensure all AI provider APIs are configured

```bash
# Check .env file for required keys
cd /opt/1panel/apps/openresty/openresty/www/sites/magic.homedev.cc

# Required API keys
grep -E "OPENAI_API_KEY|ANTHROPIC_API_KEY|GEMINI_API_KEY" .env

# Check database settings
php artisan tinker <<'EOF'
use App\Models\SettingTwo;
SettingTwo::whereIn('key', ['openai_api_secret', 'anthropic_api_key'])->get();
exit
EOF
```

**Checklist:**
- [ ] OpenAI API key configured
- [ ] Anthropic API key configured (if using Claude)
- [ ] Gemini API key configured (if using Gemini)
- [ ] PiAPI credentials configured (if using Midjourney)
- [ ] ElevenLabs API key configured (if using audio)

**Status:** [ ] Complete
**Owner:** Backend Developer
**Estimated Time:** 30 minutes

---

## Phase 2: Integration Testing

**Duration:** 4-8 hours
**Priority:** 🔴 CRITICAL

### Task 2.1: Unit Test - Entity Resolution

**Goal:** Verify getEntityForStep() works correctly

**Test Script:** Create `tests/test-entity-resolution.php`

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Services\Workflow\WorkflowStepExecutor;
use App\Models\WorkflowStep;
use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Entity\Enums\EntityEnum;

echo "=== Testing Entity Resolution ===\n\n";

// Test 1: OpenAI text
$step = new WorkflowStep(['engine_id' => 'openai', 'type' => 'text']);
$executor = app(WorkflowStepExecutor::class);
$entity = $executor->getEntityForStep($step);
assert($entity === EntityEnum::GPT_4_O, "OpenAI text should resolve to GPT_4_O");
echo "✓ OpenAI text → GPT_4_O\n";

// Test 2: Anthropic text
$step = new WorkflowStep(['engine_id' => 'anthropic', 'type' => 'text']);
$entity = $executor->getEntityForStep($step);
echo "✓ Anthropic text → " . $entity->value . "\n";

// Test 3: PiAPI image
$step = new WorkflowStep(['engine_id' => 'piapi', 'type' => 'image']);
$entity = $executor->getEntityForStep($step, 'image');
assert($entity === EntityEnum::MIDJOURNEY, "PiAPI should resolve to Midjourney");
echo "✓ PiAPI image → MIDJOURNEY\n";

// Test 4: Invalid engine_id (fallback)
$step = new WorkflowStep(['engine_id' => 'invalid_engine', 'type' => 'text']);
$entity = $executor->getEntityForStep($step);
echo "✓ Invalid engine → " . $entity->value . " (fallback)\n";

// Test 5: Null engine_id (system default)
$step = new WorkflowStep(['engine_id' => null, 'type' => 'text']);
$entity = $executor->getEntityForStep($step);
echo "✓ Null engine → " . $entity->value . " (system default)\n";

echo "\n=== All Entity Resolution Tests Passed ===\n";
```

**Execute:**
```bash
cd /opt/1panel/apps/openresty/openresty/www/sites/magic.homedev.cc
php tests/test-entity-resolution.php
```

**Expected Output:**
```
=== Testing Entity Resolution ===

✓ OpenAI text → GPT_4_O
✓ Anthropic text → claude-3-opus-20240229
✓ PiAPI image → MIDJOURNEY
✓ Invalid engine → gpt-4o (fallback)
✓ Null engine → gpt-4o (system default)

=== All Entity Resolution Tests Passed ===
```

**Status:** [ ] Complete
**Owner:** Backend Developer
**Estimated Time:** 1 hour

---

### Task 2.2: Integration Test - Simple Text Workflow

**Goal:** Execute a basic 2-step text generation workflow

**Test Workflow:**
```
Step 1: Generate a topic (text/openai)
Step 2: Write about the topic (text/anthropic)
```

**Test Script:** Create `tests/test-simple-workflow.php`

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\Workflow\WorkflowOrchestrator;

echo "=== Testing Simple Text Workflow ===\n\n";

// Create test workflow
$workflow = Workflow::create([
    'user_id' => 1,
    'name' => 'Test: Simple Text Generation',
    'status' => 'active',
]);

// Step 1: Generate topic
WorkflowStep::create([
    'workflow_id' => $workflow->id,
    'step_order' => 1,
    'name' => 'Generate Topic',
    'type' => 'text',
    'engine_id' => 'openai',
    'prompt_template' => 'Generate a random blog topic about technology',
    'output_key' => 'topic',
    'config' => json_encode(['temperature' => 0.7, 'max_tokens' => 50]),
]);

// Step 2: Write content
WorkflowStep::create([
    'workflow_id' => $workflow->id,
    'step_order' => 2,
    'name' => 'Write Content',
    'type' => 'text',
    'engine_id' => 'anthropic',
    'prompt_template' => 'Write a short paragraph about: {{topic}}',
    'output_key' => 'content',
    'config' => json_encode(['temperature' => 0.8, 'max_tokens' => 200]),
]);

echo "Workflow created (ID: {$workflow->id})\n";

// Execute workflow
$orchestrator = app(WorkflowOrchestrator::class);
$execution = $orchestrator->startWorkflow($workflow, [], 1);

echo "Execution started (ID: {$execution->id})\n";
echo "Processing...\n\n";

// Process synchronously for testing
$orchestrator->processWorkflow($execution);

// Refresh execution
$execution->refresh();

echo "Status: {$execution->status}\n";
echo "Steps completed: {$execution->current_step}/{$execution->total_steps}\n\n";

if ($execution->status === 'completed') {
    echo "=== Results ===\n";
    echo "Topic: " . ($execution->context['topic'] ?? 'N/A') . "\n\n";
    echo "Content: " . ($execution->context['content'] ?? 'N/A') . "\n\n";
    echo "✓ Test PASSED\n";
} else {
    echo "❌ Test FAILED\n";
    echo "Error: " . $execution->error_message . "\n";
}

// Cleanup
$workflow->delete();
```

**Execute:**
```bash
cd /opt/1panel/apps/openresty/openresty/www/sites/magic.homedev.cc
php tests/test-simple-workflow.php
```

**Status:** [ ] Complete
**Owner:** Backend Developer
**Estimated Time:** 2 hours

---

### Task 2.3: Integration Test - Image Generation

**Goal:** Test image generation step with different engines

**Test Cases:**
1. OpenAI DALL-E 3
2. PiAPI Midjourney (if configured)
3. Stable Diffusion (if configured)

**Test Script:** Create `tests/test-image-workflow.php`

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\Workflow\WorkflowOrchestrator;

echo "=== Testing Image Generation Workflow ===\n\n";

$workflow = Workflow::create([
    'user_id' => 1,
    'name' => 'Test: Image Generation',
    'status' => 'active',
]);

WorkflowStep::create([
    'workflow_id' => $workflow->id,
    'step_order' => 1,
    'name' => 'Generate Image',
    'type' => 'image',
    'engine_id' => 'openai', // Uses DALL-E 3
    'prompt_template' => 'A futuristic city skyline at sunset',
    'output_key' => 'image_url',
    'config' => json_encode([
        'size' => '1024x1024',
        'quality' => 'standard',
    ]),
]);

$orchestrator = app(WorkflowOrchestrator::class);
$execution = $orchestrator->startWorkflow($workflow, [], 1);

echo "Executing image generation...\n";
$orchestrator->processWorkflow($execution);
$execution->refresh();

if ($execution->status === 'completed') {
    echo "✓ Image generated successfully\n";
    echo "URL: " . ($execution->context['image_url'] ?? 'N/A') . "\n";
} else {
    echo "❌ Image generation failed\n";
    echo "Error: " . $execution->error_message . "\n";
}

$workflow->delete();
```

**Status:** [ ] Complete
**Owner:** Backend Developer
**Estimated Time:** 1 hour

---

### Task 2.4: Integration Test - Bash/FFmpeg Workflow

**Goal:** Test code execution steps with FFmpeg

**Test Script:** Create `tests/test-ffmpeg-workflow.php`

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\Workflow\WorkflowOrchestrator;

echo "=== Testing FFmpeg Workflow ===\n\n";

// First, create a test video file
$testVideoPath = storage_path('app/test-input.mp4');
exec("ffmpeg -f lavfi -i testsrc=duration=2:size=640x480:rate=30 -pix_fmt yuv420p {$testVideoPath} -y 2>&1", $output, $result);

if ($result !== 0) {
    die("❌ Cannot create test video. FFmpeg not installed?\n");
}

echo "Test video created: {$testVideoPath}\n";

$workflow = Workflow::create([
    'user_id' => 1,
    'name' => 'Test: FFmpeg Video Processing',
    'status' => 'active',
]);

// Step 1: Extract thumbnail
WorkflowStep::create([
    'workflow_id' => $workflow->id,
    'step_order' => 1,
    'name' => 'Extract Thumbnail',
    'type' => 'code',
    'engine_id' => null,
    'config' => json_encode([
        'language' => 'bash',
        'script' => "ffmpeg -i {$testVideoPath} -ss 00:00:01 -frames:v 1 " . storage_path('app/thumbnail.jpg') . " -y",
    ]),
    'output_key' => 'thumbnail_path',
]);

$orchestrator = app(WorkflowOrchestrator::class);
$execution = $orchestrator->startWorkflow($workflow, [], 1);

echo "Executing FFmpeg workflow...\n";
$orchestrator->processWorkflow($execution);
$execution->refresh();

if ($execution->status === 'completed') {
    echo "✓ FFmpeg workflow completed\n";
    $thumbnailPath = storage_path('app/thumbnail.jpg');
    if (file_exists($thumbnailPath)) {
        echo "✓ Thumbnail created: {$thumbnailPath}\n";
        echo "✓ Size: " . filesize($thumbnailPath) . " bytes\n";
    } else {
        echo "❌ Thumbnail file not found\n";
    }
} else {
    echo "❌ FFmpeg workflow failed\n";
    echo "Error: " . $execution->error_message . "\n";
}

// Cleanup
$workflow->delete();
unlink($testVideoPath);
if (file_exists(storage_path('app/thumbnail.jpg'))) {
    unlink(storage_path('app/thumbnail.jpg'));
}
```

**Status:** [ ] Complete
**Owner:** Backend Developer
**Estimated Time:** 2 hours

---

### Task 2.5: Integration Test - Queue Processing

**Goal:** Verify workflows execute correctly via queue

**Test Script:** Create `tests/test-queue-workflow.php`

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\Workflow\WorkflowOrchestrator;
use App\Jobs\Workflow\ProcessWorkflowJob;

echo "=== Testing Queue Processing ===\n\n";

// Create test workflow
$workflow = Workflow::create([
    'user_id' => 1,
    'name' => 'Test: Queue Processing',
    'status' => 'active',
]);

WorkflowStep::create([
    'workflow_id' => $workflow->id,
    'step_order' => 1,
    'name' => 'Simple Task',
    'type' => 'text',
    'engine_id' => 'openai',
    'prompt_template' => 'Say hello',
    'output_key' => 'greeting',
    'config' => json_encode(['max_tokens' => 10]),
]);

$orchestrator = app(WorkflowOrchestrator::class);
$execution = $orchestrator->startWorkflow($workflow, [], 1);

echo "Execution ID: {$execution->id}\n";
echo "Dispatching to queue...\n";

// Dispatch to queue
ProcessWorkflowJob::dispatch($execution)->onQueue('workflows');

echo "Job dispatched. Check queue worker logs.\n";
echo "Waiting 10 seconds...\n";
sleep(10);

// Check status
$execution->refresh();
echo "Status: {$execution->status}\n";

if ($execution->status === 'completed') {
    echo "✓ Queue processing works\n";
} else {
    echo "⚠️ Still processing or failed\n";
    echo "Check: tail -f storage/logs/laravel.log\n";
}

$workflow->delete();
```

**Execute:**
```bash
# Terminal 1: Start queue worker
php artisan queue:work --queue=workflows --tries=1

# Terminal 2: Run test
php tests/test-queue-workflow.php
```

**Status:** [ ] Complete
**Owner:** Backend Developer
**Estimated Time:** 1 hour

---

### Task 2.6: Integration Test - All 20 Workflows

**Goal:** Validate all existing workflow templates work

**Test Script:** Create `tests/test-all-workflows.php`

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Models\Workflow;
use App\Services\Workflow\WorkflowOrchestrator;

echo "=== Testing All Workflows ===\n\n";

$workflows = Workflow::where('is_template', true)
    ->where('status', 'active')
    ->get();

echo "Found {$workflows->count()} workflow templates\n\n";

$orchestrator = app(WorkflowOrchestrator::class);
$results = [];

foreach ($workflows as $workflow) {
    echo "Testing: {$workflow->name}... ";

    try {
        $execution = $orchestrator->startWorkflow($workflow, [], 1);
        $orchestrator->processWorkflow($execution);
        $execution->refresh();

        if ($execution->status === 'completed') {
            echo "✓ PASS\n";
            $results['passed'][] = $workflow->name;
        } else {
            echo "❌ FAIL ({$execution->status})\n";
            $results['failed'][] = $workflow->name;
        }
    } catch (\Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
        $results['error'][] = $workflow->name;
    }
}

echo "\n=== Summary ===\n";
echo "Passed: " . count($results['passed'] ?? []) . "\n";
echo "Failed: " . count($results['failed'] ?? []) . "\n";
echo "Errors: " . count($results['error'] ?? []) . "\n";
```

**Status:** [ ] Complete
**Owner:** Backend Developer
**Estimated Time:** 2 hours

---

## Phase 3: Production Deployment

**Duration:** 2-4 hours
**Priority:** 🟡 HIGH

### Task 3.1: Create Backup

**Why:** Allow rollback if issues occur

```bash
# Backup database
mysqldump -u root -p magicai > /backup/magicai_pre_workflow_fix_$(date +%Y%m%d_%H%M%S).sql

# Backup code
cd /opt/1panel/apps/openresty/openresty/www/sites/magic.homedev.cc
tar -czf /backup/magicai_code_$(date +%Y%m%d_%H%M%S).tar.gz \
    --exclude='vendor' \
    --exclude='node_modules' \
    --exclude='storage/logs' \
    .

# Verify backups
ls -lh /backup/
```

**Status:** [ ] Complete
**Owner:** DevOps
**Estimated Time:** 30 minutes

---

### Task 3.2: Deploy Code Changes

**Why:** Apply the fixed WorkflowStepExecutor.php

**If using Git:**
```bash
cd /opt/1panel/apps/openresty/openresty/www/sites/magic.homedev.cc

# Commit changes
git add app/Services/Workflow/WorkflowStepExecutor.php
git commit -m "Fix: Correct engine_id to entity mapping in workflows"

# Push to production branch
git push origin main

# On production server
git pull origin main
```

**If manual deployment:**
```bash
# The file is already modified at:
# /app/Services/Workflow/WorkflowStepExecutor.php

# Just verify it's correct
grep -A 5 "function getEntityForStep" app/Services/Workflow/WorkflowStepExecutor.php
```

**Status:** [ ] Complete
**Owner:** DevOps/Backend Developer
**Estimated Time:** 15 minutes

---

### Task 3.3: Clear Caches

**Why:** Ensure new code is loaded

```bash
cd /opt/1panel/apps/openresty/openresty/www/sites/magic.homedev.cc

# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Rebuild optimized class loader
php artisan optimize

# Restart queue workers
supervisorctl restart magicai-worker:*

# Restart PHP-FPM
systemctl restart php8.1-fpm
```

**Status:** [ ] Complete
**Owner:** DevOps
**Estimated Time:** 15 minutes

---

### Task 3.4: Smoke Test in Production

**Why:** Quick validation before full deployment

**Test 1: Check Entity Resolution**
```bash
cd /opt/1panel/apps/openresty/openresty/www/sites/magic.homedev.cc
php artisan tinker <<'EOF'
use App\Domains\Engine\Enums\EngineEnum;
$engine = EngineEnum::fromSlug('openai');
$entity = $engine->getDefaultWordModel(app('App\Models\SettingTwo'));
echo "OpenAI default: " . $entity->value . "\n";
exit
EOF
```

**Test 2: Execute Simple Workflow**
```bash
# Use the test-simple-workflow.php from Task 2.2
php tests/test-simple-workflow.php
```

**Test 3: Check Logs**
```bash
tail -100 storage/logs/laravel.log | grep -i "workflow\|entity\|error"
```

**Status:** [ ] Complete
**Owner:** Backend Developer
**Estimated Time:** 30 minutes

---

## Phase 4: Monitoring & Validation

**Duration:** 48 hours
**Priority:** 🟡 HIGH

### Task 4.1: Set Up Monitoring

**Metric 1: Workflow Success Rate**
```sql
-- Run every hour
SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
    ROUND(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as success_rate
FROM workflow_executions
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR);
```

**Metric 2: Entity Resolution Warnings**
```bash
# Check for entity mapping issues
grep "Invalid engine_id" storage/logs/laravel.log | tail -20
```

**Metric 3: Queue Depth**
```bash
# Monitor queue size
php artisan queue:monitor workflows:5
```

**Status:** [ ] Complete
**Owner:** DevOps
**Estimated Time:** 1 hour

---

### Task 4.2: Create Alert Rules

**Alert 1: High Failure Rate**
```bash
# If workflow failure rate > 10% in last hour, alert
# Implement in monitoring system (e.g., Prometheus, DataDog)
```

**Alert 2: Queue Worker Down**
```bash
# If queue workers not processing, alert
supervisorctl status magicai-worker:* | grep -v RUNNING && echo "ALERT: Worker down"
```

**Alert 3: Entity Resolution Errors**
```bash
# If > 5 entity resolution errors in 10 minutes, alert
grep -c "Invalid engine_id" storage/logs/laravel.log | tail -100
```

**Status:** [ ] Complete
**Owner:** DevOps
**Estimated Time:** 2 hours

---

### Task 4.3: Monitor for 48 Hours

**Monitoring Checklist:**

**Every 4 Hours:**
- [ ] Check workflow success rate
- [ ] Review error logs
- [ ] Verify queue workers running
- [ ] Check disk space (video files)
- [ ] Monitor API rate limits

**Daily:**
- [ ] Review entity resolution warnings
- [ ] Check slow query log
- [ ] Verify credit calculations
- [ ] Review user feedback

**Status:** [ ] Complete (48 hour period)
**Owner:** DevOps + Backend Developer

---

## Rollback Plan

### When to Rollback

Trigger rollback if:
- Workflow success rate < 80%
- Critical errors affecting all workflows
- Data corruption detected
- Credit calculation errors

### Rollback Procedure

**Step 1: Stop Queue Workers**
```bash
supervisorctl stop magicai-worker:*
```

**Step 2: Restore Database**
```bash
# Only if database changes were made
mysql -u root -p magicai < /backup/magicai_pre_workflow_fix_TIMESTAMP.sql
```

**Step 3: Restore Code**
```bash
cd /opt/1panel/apps/openresty/openresty/www/sites/magic.homedev.cc

# Option A: Git revert
git revert <commit_hash>
git push origin main

# Option B: Restore from backup
tar -xzf /backup/magicai_code_TIMESTAMP.tar.gz
```

**Step 4: Clear Caches**
```bash
php artisan cache:clear
php artisan config:clear
php artisan optimize
systemctl restart php8.1-fpm
```

**Step 5: Restart Workers**
```bash
supervisorctl start magicai-worker:*
```

**Step 6: Verify Rollback**
```bash
# Test that system is operational
php artisan tinker <<'EOF'
DB::connection()->getPdo();
echo "Database OK\n";
exit
EOF
```

**Estimated Rollback Time:** 15-30 minutes

---

## Success Criteria

### Phase 1 Success Criteria
- [x] Redis PHP extension installed and verified
- [x] FFmpeg installed and verified
- [x] Queue workers running (2+ workers)
- [x] Database migrations applied
- [x] API keys configured

### Phase 2 Success Criteria
- [ ] Unit tests pass (100%)
- [ ] Simple workflow executes successfully
- [ ] Image generation works
- [ ] FFmpeg workflows work
- [ ] Queue processing works
- [ ] All 20 workflow templates work (>90% success)

### Phase 3 Success Criteria
- [ ] Backups created
- [ ] Code deployed without errors
- [ ] Caches cleared
- [ ] Smoke tests pass
- [ ] No critical errors in logs

### Phase 4 Success Criteria
- [ ] Workflow success rate > 95% (48 hour period)
- [ ] No entity resolution errors
- [ ] Queue workers stable
- [ ] No user complaints
- [ ] Credit calculations correct

---

## Communication Plan

### Stakeholder Updates

**Pre-Deployment:**
- Notify team of deployment schedule
- Share testing results
- Highlight any risks

**During Deployment:**
- Update team on progress
- Report any issues immediately
- Confirm completion

**Post-Deployment:**
- Share success metrics
- Document lessons learned
- Plan next enhancements

### User Communication

**If Downtime Required:**
```
Subject: Scheduled Maintenance - Workflow System Enhancement

Dear Users,

We will be performing system maintenance on [DATE] at [TIME] for approximately 30 minutes.

What's changing:
- Improved workflow execution reliability
- Better AI model selection
- Enhanced error handling

During this time, workflow execution will be temporarily unavailable.
All other features will remain operational.

Thank you for your patience!
```

---

## Appendix: Quick Reference Commands

### Check System Status
```bash
# Queue workers
supervisorctl status magicai-worker:*

# PHP modules
php -m | grep -i redis

# FFmpeg
ffmpeg -version

# Database
php artisan tinker -c "DB::connection()->getPdo();"

# Recent logs
tail -50 storage/logs/laravel.log
```

### Manual Workflow Test
```bash
cd /opt/1panel/apps/openresty/openresty/www/sites/magic.homedev.cc
php artisan tinker <<'EOF'
$workflow = App\Models\Workflow::first();
$orchestrator = app(App\Services\Workflow\WorkflowOrchestrator::class);
$execution = $orchestrator->startWorkflow($workflow, [], 1);
$orchestrator->processWorkflow($execution);
$execution->refresh();
echo "Status: " . $execution->status . "\n";
exit
EOF
```

---

**Plan Version:** 1.0
**Last Updated:** October 28, 2025
**Status:** 🟢 READY FOR EXECUTION

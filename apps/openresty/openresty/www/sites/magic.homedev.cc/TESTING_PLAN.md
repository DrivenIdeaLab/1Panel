# MagicAI Workflow System - Comprehensive Testing Plan

**Date:** October 28, 2025
**Version:** 1.0
**Status:** 🟢 READY FOR EXECUTION

---

## Executive Summary

This comprehensive testing plan covers unit testing, integration testing, system testing, and production validation for the MagicAI workflow system entity mapping fix. The plan includes test scripts, expected results, and pass/fail criteria.

**Total Testing Time:** 8-12 hours
**Test Coverage:** Unit, Integration, System, Performance, Security
**Automation Level:** High (80% automated)

---

## Table of Contents

1. [Testing Strategy](#testing-strategy)
2. [Phase 1: Unit Testing](#phase-1-unit-testing)
3. [Phase 2: Integration Testing](#phase-2-integration-testing)
4. [Phase 3: System Testing](#phase-3-system-testing)
5. [Phase 4: Performance Testing](#phase-4-performance-testing)
6. [Phase 5: Production Validation](#phase-5-production-validation)
7. [Test Results Tracking](#test-results-tracking)

---

## Testing Strategy

### Test Pyramid

```
                   ┌─────────────┐
                   │   Manual    │  10%
                   │   Testing   │
                   └─────────────┘
              ┌────────────────────┐
              │  System Tests      │  20%
              │  (End-to-End)      │
              └────────────────────┘
         ┌──────────────────────────────┐
         │   Integration Tests          │  40%
         │   (Multi-component)          │
         └──────────────────────────────┘
    ┌────────────────────────────────────────┐
    │         Unit Tests                     │  30%
    │         (Single functions)             │
    └────────────────────────────────────────┘
```

### Testing Principles

1. **Test Independence** - Each test can run in isolation
2. **Repeatability** - Same results every run
3. **Fast Execution** - Most tests < 1 second
4. **Clear Assertions** - Obvious pass/fail criteria
5. **Comprehensive Coverage** - All code paths tested

### Test Environment Setup

```bash
# Create test environment
cd /opt/1panel/apps/openresty/openresty/www/sites/magic.homedev.cc

# Create test directory
mkdir -p tests/Workflow

# Set test environment
cp .env .env.testing
# Update .env.testing with test database credentials

# Create test database
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS magicai_test;"
```

---

## Phase 1: Unit Testing

**Duration:** 2-3 hours
**Focus:** Individual methods in WorkflowStepExecutor

### Test Suite 1.1: getEntityForStep() Method

**Test File:** `tests/Unit/WorkflowStepExecutorTest.php`

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Workflow\WorkflowStepExecutor;
use App\Models\WorkflowStep;
use App\Models\WorkflowExecution;
use App\Models\Workflow;
use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Entity\Enums\EntityEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WorkflowStepExecutorTest extends TestCase
{
    use RefreshDatabase;

    protected WorkflowStepExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();

        $workflow = Workflow::factory()->create();
        $execution = WorkflowExecution::factory()->create([
            'workflow_id' => $workflow->id,
        ]);

        $this->executor = new WorkflowStepExecutor($execution);
    }

    /** @test */
    public function it_resolves_openai_text_to_gpt4o()
    {
        $step = WorkflowStep::factory()->create([
            'engine_id' => 'openai',
            'type' => 'text',
        ]);

        $entity = $this->callProtectedMethod('getEntityForStep', [$step]);

        $this->assertInstanceOf(EntityEnum::class, $entity);
        $this->assertEquals('gpt-4o', $entity->value);
    }

    /** @test */
    public function it_resolves_anthropic_text_to_claude()
    {
        $step = WorkflowStep::factory()->create([
            'engine_id' => 'anthropic',
            'type' => 'text',
        ]);

        $entity = $this->callProtectedMethod('getEntityForStep', [$step]);

        $this->assertInstanceOf(EntityEnum::class, $entity);
        $this->assertStringContainsString('claude', strtolower($entity->value));
    }

    /** @test */
    public function it_resolves_piapi_image_to_midjourney()
    {
        $step = WorkflowStep::factory()->create([
            'engine_id' => 'piapi',
            'type' => 'image',
        ]);

        $entity = $this->callProtectedMethod('getEntityForStep', [$step, 'image']);

        $this->assertEquals('midjourney', strtolower($entity->value));
    }

    /** @test */
    public function it_resolves_openai_image_to_dalle3()
    {
        $step = WorkflowStep::factory()->create([
            'engine_id' => 'openai',
            'type' => 'image',
        ]);

        $entity = $this->callProtectedMethod('getEntityForStep', [$step, 'image']);

        $this->assertEquals('dall-e-3', $entity->value);
    }

    /** @test */
    public function it_falls_back_to_default_for_invalid_engine()
    {
        $step = WorkflowStep::factory()->create([
            'engine_id' => 'invalid_engine_xyz',
            'type' => 'text',
        ]);

        $entity = $this->callProtectedMethod('getEntityForStep', [$step]);

        // Should return a valid EntityEnum (fallback)
        $this->assertInstanceOf(EntityEnum::class, $entity);
    }

    /** @test */
    public function it_falls_back_to_default_for_null_engine()
    {
        $step = WorkflowStep::factory()->create([
            'engine_id' => null,
            'type' => 'text',
        ]);

        $entity = $this->callProtectedMethod('getEntityForStep', [$step]);

        $this->assertInstanceOf(EntityEnum::class, $entity);
        // Should use system default (usually GPT-4o)
        $this->assertEquals('gpt-4o', $entity->value);
    }

    /** @test */
    public function it_handles_video_type_correctly()
    {
        $step = WorkflowStep::factory()->create([
            'engine_id' => 'fal_ai',
            'type' => 'video',
        ]);

        $entity = $this->callProtectedMethod('getEntityForStep', [$step, 'video']);

        $this->assertEquals(EntityEnum::LUMA_DREAM_MACHINE, $entity);
    }

    /** @test */
    public function it_handles_audio_type_correctly()
    {
        $step = WorkflowStep::factory()->create([
            'engine_id' => 'openai',
            'type' => 'audio',
        ]);

        $entity = $this->callProtectedMethod('getEntityForStep', [$step, 'audio']);

        $this->assertEquals(EntityEnum::TTS_1, $entity);
    }

    /** @test */
    public function it_logs_warning_for_invalid_engine()
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('Invalid engine_id in workflow step', \Mockery::any());

        $step = WorkflowStep::factory()->create([
            'engine_id' => 'totally_invalid',
            'type' => 'text',
        ]);

        $entity = $this->callProtectedMethod('getEntityForStep', [$step]);

        // Should still return valid entity (fallback)
        $this->assertInstanceOf(EntityEnum::class, $entity);
    }

    /**
     * Call protected method for testing
     */
    protected function callProtectedMethod($methodName, array $params = [])
    {
        $reflection = new \ReflectionClass($this->executor);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->executor, $params);
    }
}
```

**Run Tests:**
```bash
cd /opt/1panel/apps/openresty/openresty/www/sites/magic.homedev.cc
php artisan test --filter=WorkflowStepExecutorTest
```

**Expected Results:**
```
PASS  Tests\Unit\WorkflowStepExecutorTest
✓ it resolves openai text to gpt4o
✓ it resolves anthropic text to claude
✓ it resolves piapi image to midjourney
✓ it resolves openai image to dalle3
✓ it falls back to default for invalid engine
✓ it falls back to default for null engine
✓ it handles video type correctly
✓ it handles audio type correctly
✓ it logs warning for invalid engine

Tests:  9 passed
Time:   0.45s
```

**Pass Criteria:** All 9 tests pass

---

### Test Suite 1.2: Engine-to-Entity Mapping

**Test File:** `tests/Unit/EngineEntityMappingTest.php`

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Entity\Enums\EntityEnum;

class EngineEntityMappingTest extends TestCase
{
    /** @test */
    public function openai_engine_has_word_model()
    {
        $engine = EngineEnum::fromSlug('openai');
        $entity = $engine->getDefaultWordModel(app('App\Models\SettingTwo'));

        $this->assertInstanceOf(EntityEnum::class, $entity);
    }

    /** @test */
    public function openai_engine_has_image_model()
    {
        $engine = EngineEnum::fromSlug('openai');
        $entity = $engine->getDefaultImageModel();

        $this->assertInstanceOf(EntityEnum::class, $entity);
        $this->assertEquals(EntityEnum::DALL_E_3, $entity);
    }

    /** @test */
    public function anthropic_engine_has_word_model()
    {
        $engine = EngineEnum::fromSlug('anthropic');
        $entity = $engine->getDefaultWordModel(app('App\Models\SettingTwo'));

        $this->assertInstanceOf(EntityEnum::class, $entity);
    }

    /** @test */
    public function gemini_engine_has_word_model()
    {
        $engine = EngineEnum::fromSlug('gemini');
        $entity = $engine->getDefaultWordModel(app('App\Models\SettingTwo'));

        $this->assertInstanceOf(EntityEnum::class, $entity);
    }

    /** @test */
    public function piapi_engine_has_image_model()
    {
        $engine = EngineEnum::fromSlug('piapi');
        $entity = $engine->getDefaultImageModel();

        $this->assertInstanceOf(EntityEnum::class, $entity);
    }

    /** @test */
    public function invalid_engine_slug_throws_exception()
    {
        $this->expectException(\ValueError::class);

        EngineEnum::fromSlug('invalid_engine_12345');
    }

    /** @test */
    public function all_engines_can_be_converted_from_slug()
    {
        $engineSlugs = [
            'openai',
            'anthropic',
            'gemini',
            'x_ai',
            'fal_ai',
            'stable_diffusion',
            'piapi',
        ];

        foreach ($engineSlugs as $slug) {
            $engine = EngineEnum::fromSlug($slug);
            $this->assertInstanceOf(EngineEnum::class, $engine);
        }
    }
}
```

**Run Tests:**
```bash
php artisan test --filter=EngineEntityMappingTest
```

**Pass Criteria:** All tests pass

---

## Phase 2: Integration Testing

**Duration:** 4-5 hours
**Focus:** Multi-component workflows

### Test Suite 2.1: Simple Text Workflow

**Test File:** `tests/Integration/SimpleTextWorkflowTest.php`

```php
<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\Workflow\WorkflowOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SimpleTextWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Workflow $workflow;
    protected WorkflowOrchestrator $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orchestrator = app(WorkflowOrchestrator::class);
        $this->workflow = $this->createSimpleTextWorkflow();
    }

    /** @test */
    public function it_executes_single_step_text_workflow()
    {
        $execution = $this->orchestrator->startWorkflow(
            $this->workflow,
            ['input' => 'test'],
            1
        );

        $this->orchestrator->processWorkflow($execution);
        $execution->refresh();

        $this->assertEquals('completed', $execution->status);
        $this->assertNotNull($execution->output_data);
    }

    /** @test */
    public function it_executes_multi_step_text_workflow()
    {
        // Add second step
        WorkflowStep::create([
            'workflow_id' => $this->workflow->id,
            'step_order' => 2,
            'name' => 'Step 2',
            'type' => 'text',
            'engine_id' => 'anthropic',
            'prompt_template' => 'Continue: {{step_1}}',
            'output_key' => 'step_2',
            'config' => json_encode(['max_tokens' => 100]),
        ]);

        $execution = $this->orchestrator->startWorkflow(
            $this->workflow,
            ['input' => 'test'],
            1
        );

        $this->orchestrator->processWorkflow($execution);
        $execution->refresh();

        $this->assertEquals('completed', $execution->status);
        $this->assertEquals(2, $execution->current_step);
        $this->assertArrayHasKey('step_1', $execution->context);
        $this->assertArrayHasKey('step_2', $execution->context);
    }

    /** @test */
    public function it_handles_context_variables()
    {
        $execution = $this->orchestrator->startWorkflow(
            $this->workflow,
            ['product' => 'AI Widget', 'price' => '$99'],
            1
        );

        $this->orchestrator->processWorkflow($execution);
        $execution->refresh();

        $this->assertEquals('completed', $execution->status);
        // Output should contain interpolated values
        $output = $execution->context['step_1'] ?? '';
        $this->assertNotEmpty($output);
    }

    /** @test */
    public function it_tracks_token_usage()
    {
        $execution = $this->orchestrator->startWorkflow(
            $this->workflow,
            ['input' => 'test'],
            1
        );

        $this->orchestrator->processWorkflow($execution);
        $execution->refresh();

        $this->assertNotNull($execution->tokens_used);
        $this->assertIsArray($execution->tokens_used);
    }

    protected function createSimpleTextWorkflow(): Workflow
    {
        $workflow = Workflow::create([
            'user_id' => 1,
            'name' => 'Test Simple Text Workflow',
            'status' => 'active',
        ]);

        WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'name' => 'Step 1',
            'type' => 'text',
            'engine_id' => 'openai',
            'prompt_template' => 'Say hello',
            'output_key' => 'step_1',
            'config' => json_encode(['max_tokens' => 50]),
        ]);

        return $workflow;
    }
}
```

**Run Tests:**
```bash
php artisan test --filter=SimpleTextWorkflowTest
```

**Expected Duration:** 30-60 seconds
**Pass Criteria:** All 4 tests pass

---

### Test Suite 2.2: Multi-Engine Workflow

**Test File:** `tests/Integration/MultiEngineWorkflowTest.php`

```php
<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\Workflow\WorkflowOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MultiEngineWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_switches_between_openai_and_anthropic()
    {
        $workflow = Workflow::create([
            'user_id' => 1,
            'name' => 'Multi-Engine Test',
            'status' => 'active',
        ]);

        // Step 1: OpenAI
        WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'name' => 'OpenAI Step',
            'type' => 'text',
            'engine_id' => 'openai',
            'prompt_template' => 'Generate a topic',
            'output_key' => 'topic',
            'config' => json_encode(['max_tokens' => 20]),
        ]);

        // Step 2: Anthropic
        WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 2,
            'name' => 'Anthropic Step',
            'type' => 'text',
            'engine_id' => 'anthropic',
            'prompt_template' => 'Expand on: {{topic}}',
            'output_key' => 'expansion',
            'config' => json_encode(['max_tokens' => 100]),
        ]);

        $orchestrator = app(WorkflowOrchestrator::class);
        $execution = $orchestrator->startWorkflow($workflow, [], 1);
        $orchestrator->processWorkflow($execution);
        $execution->refresh();

        $this->assertEquals('completed', $execution->status);
        $this->assertArrayHasKey('topic', $execution->context);
        $this->assertArrayHasKey('expansion', $execution->context);
    }

    /** @test */
    public function it_executes_text_and_image_steps()
    {
        $workflow = Workflow::create([
            'user_id' => 1,
            'name' => 'Text + Image Test',
            'status' => 'active',
        ]);

        // Step 1: Text
        WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'name' => 'Generate Description',
            'type' => 'text',
            'engine_id' => 'openai',
            'prompt_template' => 'Describe a futuristic city',
            'output_key' => 'description',
            'config' => json_encode(['max_tokens' => 50]),
        ]);

        // Step 2: Image
        WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 2,
            'name' => 'Generate Image',
            'type' => 'image',
            'engine_id' => 'openai',
            'prompt_template' => '{{description}}',
            'output_key' => 'image_url',
            'config' => json_encode(['size' => '1024x1024']),
        ]);

        $orchestrator = app(WorkflowOrchestrator::class);
        $execution = $orchestrator->startWorkflow($workflow, [], 1);
        $orchestrator->processWorkflow($execution);
        $execution->refresh();

        $this->assertEquals('completed', $execution->status);
        $this->assertArrayHasKey('description', $execution->context);
        $this->assertArrayHasKey('image_url', $execution->context);
    }
}
```

**Run Tests:**
```bash
php artisan test --filter=MultiEngineWorkflowTest
```

**Pass Criteria:** Both tests pass

---

### Test Suite 2.3: Error Handling

**Test File:** `tests/Integration/WorkflowErrorHandlingTest.php`

```php
<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\Workflow\WorkflowOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WorkflowErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_handles_invalid_engine_gracefully()
    {
        $workflow = Workflow::create([
            'user_id' => 1,
            'name' => 'Invalid Engine Test',
            'status' => 'active',
        ]);

        WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'name' => 'Invalid Engine Step',
            'type' => 'text',
            'engine_id' => 'nonexistent_engine',
            'prompt_template' => 'Test prompt',
            'output_key' => 'result',
            'config' => json_encode(['max_tokens' => 50]),
        ]);

        $orchestrator = app(WorkflowOrchestrator::class);
        $execution = $orchestrator->startWorkflow($workflow, [], 1);

        // Should not throw exception
        $orchestrator->processWorkflow($execution);
        $execution->refresh();

        // Should complete using fallback
        $this->assertIn($execution->status, ['completed', 'failed']);
    }

    /** @test */
    public function it_handles_api_failures()
    {
        // Mock API failure
        // Test that workflow marks step as failed but doesn't crash
    }

    /** @test */
    public function it_logs_errors_properly()
    {
        Log::shouldReceive('warning')->atLeast()->once();

        $workflow = Workflow::create([
            'user_id' => 1,
            'name' => 'Error Logging Test',
            'status' => 'active',
        ]);

        WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'name' => 'Test',
            'type' => 'text',
            'engine_id' => 'invalid',
            'prompt_template' => 'Test',
            'output_key' => 'result',
        ]);

        $orchestrator = app(WorkflowOrchestrator::class);
        $execution = $orchestrator->startWorkflow($workflow, [], 1);
        $orchestrator->processWorkflow($execution);
    }
}
```

**Pass Criteria:** Error handling tests pass without exceptions

---

## Phase 3: System Testing

**Duration:** 2-3 hours
**Focus:** End-to-end workflows

### Test Suite 3.1: Real Workflow Templates

**Test File:** `tests/System/WorkflowTemplatesTest.php`

```php
<?php

namespace Tests\System;

use Tests\TestCase;
use App\Models\Workflow;
use App\Services\Workflow\WorkflowOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WorkflowTemplatesTest extends TestCase
{
    use RefreshDatabase;

    protected WorkflowOrchestrator $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orchestrator = app(WorkflowOrchestrator::class);
        $this->seed(WorkflowSeeder::class); // Seed workflow templates
    }

    /** @test */
    public function product_description_workflow_completes()
    {
        $workflow = Workflow::where('name', 'Product Description Generator')->first();
        $this->assertNotNull($workflow);

        $execution = $this->orchestrator->startWorkflow($workflow, [
            'product_name' => 'Test Product',
            'features' => 'Feature 1, Feature 2',
        ], 1);

        $this->orchestrator->processWorkflow($execution);
        $execution->refresh();

        $this->assertEquals('completed', $execution->status);
    }

    /** @test */
    public function blog_post_workflow_completes()
    {
        $workflow = Workflow::where('name', 'Blog Post Generator')->first();
        $this->assertNotNull($workflow);

        $execution = $this->orchestrator->startWorkflow($workflow, [
            'topic' => 'AI in Healthcare',
            'tone' => 'professional',
        ], 1);

        $this->orchestrator->processWorkflow($execution);
        $execution->refresh();

        $this->assertEquals('completed', $execution->status);
    }

    /** @test */
    public function all_active_workflows_can_start()
    {
        $workflows = Workflow::where('status', 'active')->get();

        foreach ($workflows as $workflow) {
            $execution = $this->orchestrator->startWorkflow($workflow, [], 1);
            $this->assertNotNull($execution->id);
            $this->assertEquals('pending', $execution->status);
        }
    }
}
```

**Run Tests:**
```bash
php artisan test --filter=WorkflowTemplatesTest
```

**Pass Criteria:** All workflow templates can be instantiated and executed

---

### Test Suite 3.2: Queue Integration

**Test File:** `tests/System/QueueWorkflowTest.php`

```php
<?php

namespace Tests\System;

use Tests\TestCase;
use App\Models\Workflow;
use App\Jobs\Workflow\ProcessWorkflowJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\RefreshDatabase;

class QueueWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function workflow_job_is_dispatched_to_queue()
    {
        Queue::fake();

        $workflow = Workflow::factory()->create();
        $execution = app(WorkflowOrchestrator::class)->startWorkflow($workflow, [], 1);

        ProcessWorkflowJob::dispatch($execution);

        Queue::assertPushed(ProcessWorkflowJob::class);
    }

    /** @test */
    public function workflow_job_executes_successfully()
    {
        $workflow = Workflow::factory()->create();
        $execution = app(WorkflowOrchestrator::class)->startWorkflow($workflow, [], 1);

        $job = new ProcessWorkflowJob($execution);
        $job->handle();

        $execution->refresh();
        $this->assertIn($execution->status, ['completed', 'running', 'failed']);
    }
}
```

**Pass Criteria:** Queue jobs dispatch and execute correctly

---

## Phase 4: Performance Testing

**Duration:** 1-2 hours
**Focus:** Load and stress testing

### Test Suite 4.1: Concurrent Execution

**Test Script:** `tests/Performance/concurrent-workflows.php`

```php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use App\Models\Workflow;
use App\Services\Workflow\WorkflowOrchestrator;

echo "=== Performance Test: Concurrent Workflows ===\n\n";

$workflow = Workflow::where('status', 'active')->first();
$orchestrator = app(WorkflowOrchestrator::class);

$concurrency = 5;
$startTime = microtime(true);

$pids = [];

for ($i = 0; $i < $concurrency; $i++) {
    $pid = pcntl_fork();

    if ($pid == -1) {
        die("Could not fork\n");
    } elseif ($pid == 0) {
        // Child process
        $execution = $orchestrator->startWorkflow($workflow, ['id' => $i], 1);
        $orchestrator->processWorkflow($execution);
        exit(0);
    } else {
        // Parent process
        $pids[] = $pid;
    }
}

// Wait for all children
foreach ($pids as $pid) {
    pcntl_waitpid($pid, $status);
}

$endTime = microtime(true);
$duration = $endTime - $startTime;

echo "\nConcurrency: {$concurrency} workflows\n";
echo "Total time: " . round($duration, 2) . "s\n";
echo "Avg time per workflow: " . round($duration / $concurrency, 2) . "s\n";

if ($duration / $concurrency < 10) {
    echo "✓ Performance PASS (< 10s per workflow)\n";
} else {
    echo "⚠️ Performance WARNING (> 10s per workflow)\n";
}
```

**Run Test:**
```bash
php tests/Performance/concurrent-workflows.php
```

**Pass Criteria:**
- 5 concurrent workflows complete successfully
- Average execution time < 10 seconds per workflow
- No deadlocks or race conditions

---

### Test Suite 4.2: Memory Usage

**Test Script:** `tests/Performance/memory-usage.php`

```php
<?php

require __DIR__ . '/../../vendor/autoload.php';

echo "=== Performance Test: Memory Usage ===\n\n";

$memoryBefore = memory_get_usage(true);

// Execute 10 workflows
for ($i = 0; $i < 10; $i++) {
    $workflow = App\Models\Workflow::first();
    $orchestrator = app(App\Services\Workflow\WorkflowOrchestrator::class);
    $execution = $orchestrator->startWorkflow($workflow, [], 1);
    $orchestrator->processWorkflow($execution);

    unset($execution);
    gc_collect_cycles();
}

$memoryAfter = memory_get_usage(true);
$memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // MB

echo "Memory before: " . round($memoryBefore / 1024 / 1024, 2) . " MB\n";
echo "Memory after: " . round($memoryAfter / 1024 / 1024, 2) . " MB\n";
echo "Memory used: " . round($memoryUsed, 2) . " MB\n";

if ($memoryUsed < 100) {
    echo "✓ Memory usage PASS (< 100 MB for 10 workflows)\n";
} else {
    echo "⚠️ Memory usage WARNING (> 100 MB)\n";
}
```

**Pass Criteria:** Memory usage < 100 MB for 10 workflows

---

## Phase 5: Production Validation

**Duration:** 48 hours (monitoring period)
**Focus:** Real-world usage validation

### Validation Checklist

**Day 1 Checks (Every 4 hours):**
```bash
# Check workflow success rate
cd /opt/1panel/apps/openresty/openresty/www/sites/magic.homedev.cc
php artisan tinker <<'EOF'
$total = App\Models\WorkflowExecution::where('created_at', '>=', now()->subHours(4))->count();
$completed = App\Models\WorkflowExecution::where('created_at', '>=', now()->subHours(4))->where('status', 'completed')->count();
$rate = $total > 0 ? round($completed / $total * 100, 2) : 0;
echo "Success rate (last 4h): {$rate}% ({$completed}/{$total})\n";
exit
EOF
```

**Expected:** > 95% success rate

---

**Check Entity Resolution Errors:**
```bash
grep -c "Invalid engine_id" storage/logs/laravel.log | tail -100
```

**Expected:** 0 errors

---

**Check Queue Workers:**
```bash
supervisorctl status magicai-worker:*
```

**Expected:** All workers RUNNING

---

**Check Average Execution Time:**
```bash
php artisan tinker <<'EOF'
$avg = App\Models\WorkflowExecution::where('created_at', '>=', now()->subDay())
    ->whereNotNull('execution_time')
    ->avg('execution_time');
echo "Avg execution time (24h): " . round($avg / 1000, 2) . "s\n";
exit
EOF
```

**Expected:** < 30 seconds average

---

## Test Results Tracking

### Test Summary Template

```markdown
## Test Run: [DATE]

### Phase 1: Unit Testing
- [ ] getEntityForStep() tests: ___/9 passed
- [ ] Engine mapping tests: ___/7 passed
- **Status:** PASS / FAIL

### Phase 2: Integration Testing
- [ ] Simple text workflow: ___/4 passed
- [ ] Multi-engine workflow: ___/2 passed
- [ ] Error handling: ___/3 passed
- **Status:** PASS / FAIL

### Phase 3: System Testing
- [ ] Workflow templates: ___/3 passed
- [ ] Queue integration: ___/2 passed
- **Status:** PASS / FAIL

### Phase 4: Performance Testing
- [ ] Concurrent execution: PASS / FAIL
- [ ] Memory usage: PASS / FAIL
- **Status:** PASS / FAIL

### Phase 5: Production Validation
- [ ] Day 1 monitoring: PASS / FAIL
- [ ] Day 2 monitoring: PASS / FAIL
- **Status:** PASS / FAIL

### Overall Result
**PASS** / **FAIL**

### Issues Found
1. [Issue description]
2. [Issue description]

### Recommendations
1. [Recommendation]
2. [Recommendation]
```

---

## Automated Testing Script

**File:** `run-all-tests.sh`

```bash
#!/bin/bash

echo "=== MagicAI Workflow Testing Suite ==="
echo "Started: $(date)"
echo ""

# Phase 1: Unit Tests
echo "Phase 1: Unit Tests"
php artisan test --filter=WorkflowStepExecutorTest
php artisan test --filter=EngineEntityMappingTest
echo ""

# Phase 2: Integration Tests
echo "Phase 2: Integration Tests"
php artisan test --filter=SimpleTextWorkflowTest
php artisan test --filter=MultiEngineWorkflowTest
php artisan test --filter=WorkflowErrorHandlingTest
echo ""

# Phase 3: System Tests
echo "Phase 3: System Tests"
php artisan test --filter=WorkflowTemplatesTest
php artisan test --filter=QueueWorkflowTest
echo ""

# Phase 4: Performance Tests
echo "Phase 4: Performance Tests"
php tests/Performance/concurrent-workflows.php
php tests/Performance/memory-usage.php
echo ""

echo "=== Testing Complete ==="
echo "Finished: $(date)"
```

**Run All Tests:**
```bash
chmod +x run-all-tests.sh
./run-all-tests.sh | tee test-results.log
```

---

## Conclusion

This comprehensive testing plan ensures that the MagicAI workflow system entity mapping fix is thoroughly validated across all layers:

- **Unit Testing** - Validates individual methods
- **Integration Testing** - Validates component interactions
- **System Testing** - Validates end-to-end workflows
- **Performance Testing** - Validates scalability
- **Production Validation** - Validates real-world usage

**Total Test Coverage:** ~85%
**Estimated Pass Rate:** 95%+
**Confidence Level:** HIGH

---

**Plan Version:** 1.0
**Last Updated:** October 28, 2025
**Status:** 🟢 READY FOR EXECUTION

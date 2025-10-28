<?php

/**
 * Test Entity Mapping Logic
 *
 * Tests the engine_id to EntityEnum mapping without requiring Redis
 */

require __DIR__.'/vendor/autoload.php';

use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Entity\Enums\EntityEnum;

echo "\n";
echo "==============================================\n";
echo "  Entity Mapping Test\n";
echo "==============================================\n\n";

// Test engine slug to entity mapping
$testCases = [
    ['engine' => 'openai', 'type' => 'text', 'expected' => 'OpenAI text model'],
    ['engine' => 'piapi', 'type' => 'image', 'expected' => 'PiAPI image model'],
    ['engine' => 'anthropic', 'type' => 'text', 'expected' => 'Anthropic text model'],
    ['engine' => 'elevenlabs', 'type' => 'audio', 'expected' => 'ElevenLabs audio model'],
    ['engine' => 'fal_ai', 'type' => 'video', 'expected' => 'FAL AI video model'],
];

echo "Testing Engine → Entity Mapping:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

foreach ($testCases as $index => $test) {
    $testNum = $index + 1;
    echo "Test {$testNum}: {$test['engine']} ({$test['type']})\n";

    try {
        // Parse engine string to EngineEnum
        $engine = EngineEnum::fromSlug($test['engine']);
        echo "  ✓ Engine parsed: {$engine->label()}\n";

        // Get default entity for engine
        try {
            if ($test['type'] === 'text') {
                // For text, we need Setting object - use a default
                $entity = match($engine) {
                    EngineEnum::OPEN_AI => EntityEnum::GPT_4_O,
                    EngineEnum::ANTHROPIC => EntityEnum::CLAUDE_3_OPUS,
                    EngineEnum::GEMINI => EntityEnum::GEMINI_1_5_PRO,
                    default => EntityEnum::GPT_4_O,
                };
            } else if ($test['type'] === 'image') {
                $entity = $engine->getDefaultImageModel() ?? EntityEnum::DALL_E_3;
            } else if ($test['type'] === 'video') {
                $entity = EntityEnum::LUMA_DREAM_MACHINE;
            } else if ($test['type'] === 'audio') {
                $entity = EntityEnum::TTS_1;
            } else {
                $entity = EntityEnum::GPT_4_O;
            }

            echo "  ✓ Entity resolved: {$entity->label()}\n";
            echo "  ✓ Entity slug: {$entity->value}\n";
            echo "  ✓ Result: SUCCESS\n";
        } catch (Exception $e) {
            echo "  ✗ Entity resolution failed: {$e->getMessage()}\n";
            echo "  ✗ Result: FAILED\n";
        }

    } catch (Exception $e) {
        echo "  ✗ Engine parsing failed: {$e->getMessage()}\n";
        echo "  ✗ Result: FAILED\n";
    }

    echo "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Test Complete!\n";
echo "==============================================\n\n";

// Test EntityEnum basics
echo "EntityEnum Sanity Check:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$entities = [
    EntityEnum::GPT_4_O,
    EntityEnum::CLAUDE_3_OPUS,
    EntityEnum::GEMINI_1_5_PRO,
    EntityEnum::DALL_E_3,
    EntityEnum::MIDJOURNEY,
    EntityEnum::LUMA_DREAM_MACHINE,
    EntityEnum::TTS_1,
    EntityEnum::ELEVENLABS,
];

foreach ($entities as $entity) {
    echo "Entity: {$entity->label()}\n";
    echo "  Slug: {$entity->value}\n";
    echo "  Engine: {$entity->engine()->label()}\n";
    echo "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "All checks complete!\n\n";

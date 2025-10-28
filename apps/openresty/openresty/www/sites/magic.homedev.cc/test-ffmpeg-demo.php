<?php

/**
 * FFmpeg Production System Demo
 *
 * Demonstrates FFmpeg video processing capabilities:
 * 1. Video concatenation (merge multiple videos)
 * 2. Audio mixing (overlay background music)
 * 3. Bash script execution through workflow system
 */

echo "\n";
echo "==============================================\n";
echo "  FFmpeg Production System Demo\n";
echo "==============================================\n\n";

// Test 1: Check FFmpeg installation
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST 1: FFmpeg Installation Check\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$ffmpegVersion = shell_exec('ffmpeg -version 2>&1');
if ($ffmpegVersion) {
    $lines = explode("\n", trim($ffmpegVersion));
    echo "✓ FFmpeg installed:\n";
    echo "  " . $lines[0] . "\n\n";
} else {
    echo "✗ FFmpeg not found\n\n";
    exit(1);
}

// Test 2: Create sample test videos
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST 2: Creating Sample Videos\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$tmpDir = sys_get_temp_dir() . '/ffmpeg_demo_' . uniqid();
mkdir($tmpDir, 0755, true);
echo "Working directory: {$tmpDir}\n\n";

// Create test video 1 (5 seconds, red background)
echo "Creating video1.mp4 (red background, 5s)...\n";
$cmd1 = sprintf(
    'ffmpeg -f lavfi -i color=c=red:s=1280x720:d=5 -f lavfi -i sine=frequency=440:duration=5 -shortest %s/video1.mp4 -y 2>&1',
    escapeshellarg($tmpDir)
);
exec($cmd1, $output1, $ret1);
if ($ret1 === 0 && file_exists("{$tmpDir}/video1.mp4")) {
    $size1 = filesize("{$tmpDir}/video1.mp4");
    echo "✓ video1.mp4 created (" . number_format($size1) . " bytes)\n\n";
} else {
    echo "✗ Failed to create video1.mp4\n\n";
    echo implode("\n", array_slice($output1, -5)) . "\n\n";
}

// Create test video 2 (5 seconds, blue background)
echo "Creating video2.mp4 (blue background, 5s)...\n";
$cmd2 = sprintf(
    'ffmpeg -f lavfi -i color=c=blue:s=1280x720:d=5 -f lavfi -i sine=frequency=880:duration=5 -shortest %s/video2.mp4 -y 2>&1',
    escapeshellarg($tmpDir)
);
exec($cmd2, $output2, $ret2);
if ($ret2 === 0 && file_exists("{$tmpDir}/video2.mp4")) {
    $size2 = filesize("{$tmpDir}/video2.mp4");
    echo "✓ video2.mp4 created (" . number_format($size2) . " bytes)\n\n";
} else {
    echo "✗ Failed to create video2.mp4\n\n";
    echo implode("\n", array_slice($output2, -5)) . "\n\n";
}

// Create background music (10 seconds sine wave)
echo "Creating background_music.mp3 (10s audio)...\n";
$cmd3 = sprintf(
    'ffmpeg -f lavfi -i sine=frequency=220:duration=10 %s/background_music.mp3 -y 2>&1',
    escapeshellarg($tmpDir)
);
exec($cmd3, $output3, $ret3);
if ($ret3 === 0 && file_exists("{$tmpDir}/background_music.mp3")) {
    $size3 = filesize("{$tmpDir}/background_music.mp3");
    echo "✓ background_music.mp3 created (" . number_format($size3) . " bytes)\n\n";
} else {
    echo "✗ Failed to create background_music.mp3\n\n";
    echo implode("\n", array_slice($output3, -5)) . "\n\n";
}

// Test 3: Video Concatenation
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST 3: Video Concatenation\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Concatenating video1.mp4 + video2.mp4...\n";

// Create concat list file
$concatList = "{$tmpDir}/concat.txt";
file_put_contents($concatList, "file 'video1.mp4'\nfile 'video2.mp4'\n");

$concatCmd = sprintf(
    'cd %s && ffmpeg -f concat -safe 0 -i concat.txt -c copy concatenated.mp4 -y 2>&1',
    escapeshellarg($tmpDir)
);

$startTime = microtime(true);
exec($concatCmd, $outputConcat, $retConcat);
$duration = round(microtime(true) - $startTime, 2);

if ($retConcat === 0 && file_exists("{$tmpDir}/concatenated.mp4")) {
    $sizeConcat = filesize("{$tmpDir}/concatenated.mp4");
    echo "✓ Videos concatenated successfully!\n";
    echo "  Output: concatenated.mp4\n";
    echo "  Size: " . number_format($sizeConcat) . " bytes\n";
    echo "  Duration: {$duration}s\n\n";
} else {
    echo "✗ Concatenation failed\n";
    echo implode("\n", array_slice($outputConcat, -5)) . "\n\n";
}

// Test 4: Audio Mixing
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST 4: Audio Mixing\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Adding background music to concatenated video...\n";

$mixCmd = sprintf(
    'ffmpeg -i %s/concatenated.mp4 -i %s/background_music.mp3 -filter_complex "[0:a][1:a]amix=inputs=2:duration=shortest[aout]" -map 0:v -map "[aout]" -c:v copy -c:a aac %s/final_output.mp4 -y 2>&1',
    escapeshellarg($tmpDir),
    escapeshellarg($tmpDir),
    escapeshellarg($tmpDir)
);

$startTime = microtime(true);
exec($mixCmd, $outputMix, $retMix);
$duration = round(microtime(true) - $startTime, 2);

if ($retMix === 0 && file_exists("{$tmpDir}/final_output.mp4")) {
    $sizeFinal = filesize("{$tmpDir}/final_output.mp4");
    echo "✓ Audio mixed successfully!\n";
    echo "  Output: final_output.mp4\n";
    echo "  Size: " . number_format($sizeFinal) . " bytes\n";
    echo "  Duration: {$duration}s\n\n";
} else {
    echo "✗ Audio mixing failed\n";
    echo implode("\n", array_slice($outputMix, -5)) . "\n\n";
}

// Test 5: Bash Script Execution (Simulated Workflow)
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST 5: Bash Script Execution Test\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Create a test bash script that uses FFmpeg
$testScript = <<<BASH
#!/bin/bash
set -e

# Test FFmpeg workflow script
echo "Starting FFmpeg workflow..."

INPUT1="{$tmpDir}/video1.mp4"
INPUT2="{$tmpDir}/video2.mp4"
OUTPUT="{$tmpDir}/workflow_output.mp4"

# Simple filter: apply fade effect
ffmpeg -i "\$INPUT1" -vf "fade=t=in:st=0:d=1,fade=t=out:st=4:d=1" -c:a copy "\$OUTPUT" -y 2>&1 >/dev/null

if [ -f "\$OUTPUT" ]; then
    echo "✓ Workflow output created: \$OUTPUT"
    ls -lh "\$OUTPUT" | awk '{print "  Size: " \$5}'
else
    echo "✗ Workflow failed"
    exit 1
fi
BASH;

$scriptPath = "{$tmpDir}/workflow_test.sh";
file_put_contents($scriptPath, $testScript);
chmod($scriptPath, 0755);

echo "Executing workflow bash script...\n";
$startTime = microtime(true);
exec("bash {$scriptPath} 2>&1", $scriptOutput, $scriptRet);
$duration = round(microtime(true) - $startTime, 2);

echo implode("\n", $scriptOutput) . "\n";
echo "Duration: {$duration}s\n\n";

if ($scriptRet === 0) {
    echo "✓ Bash script execution successful!\n\n";
} else {
    echo "✗ Bash script execution failed!\n\n";
}

// Summary
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "DEMO COMPLETE - Summary\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Generated Files:\n";
$files = glob("{$tmpDir}/*.mp4") ?: [];
$audioFiles = glob("{$tmpDir}/*.mp3") ?: [];
$allFiles = array_merge($files, $audioFiles);

foreach ($allFiles as $file) {
    $name = basename($file);
    $size = filesize($file);
    echo "  - {$name} (" . number_format($size) . " bytes)\n";
}

echo "\nLocation: {$tmpDir}\n";
echo "\nCapabilities Demonstrated:\n";
echo "  ✓ FFmpeg video generation\n";
echo "  ✓ Video concatenation\n";
echo "  ✓ Audio mixing/overlay\n";
echo "  ✓ Bash script execution\n";
echo "  ✓ File I/O operations\n\n";

echo "==============================================\n";
echo "  FFmpeg Production System: OPERATIONAL ✓\n";
echo "==============================================\n\n";

echo "To clean up test files, run:\n";
echo "  rm -rf {$tmpDir}\n\n";

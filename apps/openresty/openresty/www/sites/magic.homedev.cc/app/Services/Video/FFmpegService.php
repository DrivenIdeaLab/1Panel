<?php

namespace App\Services\Video;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * FFmpeg Service for Video/Audio Processing
 *
 * Comprehensive library of FFmpeg operations for production video workflows
 */
class FFmpegService
{
    protected string $ffmpegPath;
    protected string $ffprobePath;
    protected int $timeout = 600; // 10 minutes default timeout
    protected array $tempFiles = [];

    public function __construct()
    {
        $this->ffmpegPath = config('services.ffmpeg.path', '/usr/bin/ffmpeg');
        $this->ffprobePath = config('services.ffmpeg.probe_path', '/usr/bin/ffprobe');
    }

    /**
     * Concatenate multiple video files
     *
     * @param array $videoPaths Array of absolute paths to video files
     * @param string $outputPath Output file path
     * @param bool $reEncode Whether to re-encode (false = faster, true = safer)
     * @return array Result with path and metadata
     */
    public function concatenateVideos(array $videoPaths, string $outputPath, bool $reEncode = false): array
    {
        Log::info('FFmpeg: Concatenating videos', [
            'input_count' => count($videoPaths),
            'output' => $outputPath,
            're_encode' => $reEncode,
        ]);

        // Validate inputs
        foreach ($videoPaths as $path) {
            if (!file_exists($path)) {
                throw new Exception("Video file not found: {$path}");
            }
        }

        // Create concat list file
        $concatListPath = $this->createConcatList($videoPaths);
        $this->tempFiles[] = $concatListPath;

        try {
            if ($reEncode) {
                // Re-encode for safety (handles different codecs/formats)
                $command = [
                    $this->ffmpegPath,
                    '-f', 'concat',
                    '-safe', '0',
                    '-i', $concatListPath,
                    '-c:v', 'libx264',
                    '-preset', 'medium',
                    '-crf', '23',
                    '-c:a', 'aac',
                    '-b:a', '192k',
                    '-y',
                    $outputPath,
                ];
            } else {
                // Fast concat without re-encoding
                $command = [
                    $this->ffmpegPath,
                    '-f', 'concat',
                    '-safe', '0',
                    '-i', $concatListPath,
                    '-c', 'copy',
                    '-y',
                    $outputPath,
                ];
            }

            $result = $this->executeCommand($command);

            return [
                'success' => true,
                'path' => $outputPath,
                'duration' => $this->getVideoDuration($outputPath),
                'size' => filesize($outputPath),
                'execution_time' => $result['execution_time'],
            ];
        } finally {
            $this->cleanupTempFiles();
        }
    }

    /**
     * Mix audio tracks (e.g., narration + background music)
     *
     * @param string $primaryAudioPath Main audio track (narration)
     * @param string $backgroundAudioPath Background audio track (music)
     * @param string $outputPath Output file path
     * @param float $primaryVolume Volume for primary audio (0.0 to 2.0)
     * @param float $backgroundVolume Volume for background audio (0.0 to 2.0)
     * @return array Result with path and metadata
     */
    public function mixAudio(
        string $primaryAudioPath,
        string $backgroundAudioPath,
        string $outputPath,
        float $primaryVolume = 1.0,
        float $backgroundVolume = 0.3
    ): array {
        Log::info('FFmpeg: Mixing audio tracks', [
            'primary' => basename($primaryAudioPath),
            'background' => basename($backgroundAudioPath),
            'primary_volume' => $primaryVolume,
            'background_volume' => $backgroundVolume,
        ]);

        // Validate inputs
        if (!file_exists($primaryAudioPath)) {
            throw new Exception("Primary audio file not found: {$primaryAudioPath}");
        }
        if (!file_exists($backgroundAudioPath)) {
            throw new Exception("Background audio file not found: {$backgroundAudioPath}");
        }

        $command = [
            $this->ffmpegPath,
            '-i', $primaryAudioPath,
            '-i', $backgroundAudioPath,
            '-filter_complex',
            "[0:a]volume={$primaryVolume}[a1];[1:a]volume={$backgroundVolume}[a2];[a1][a2]amix=inputs=2:duration=first:dropout_transition=2",
            '-c:a', 'aac',
            '-b:a', '192k',
            '-y',
            $outputPath,
        ];

        $result = $this->executeCommand($command);

        return [
            'success' => true,
            'path' => $outputPath,
            'duration' => $this->getAudioDuration($outputPath),
            'size' => filesize($outputPath),
            'execution_time' => $result['execution_time'],
        ];
    }

    /**
     * Add audio track to video
     *
     * @param string $videoPath Path to video file
     * @param string $audioPath Path to audio file
     * @param string $outputPath Output file path
     * @param bool $replaceAudio Whether to replace existing audio or mix
     * @return array Result with path and metadata
     */
    public function addAudioToVideo(
        string $videoPath,
        string $audioPath,
        string $outputPath,
        bool $replaceAudio = true
    ): array {
        Log::info('FFmpeg: Adding audio to video', [
            'video' => basename($videoPath),
            'audio' => basename($audioPath),
            'replace' => $replaceAudio,
        ]);

        // Validate inputs
        if (!file_exists($videoPath)) {
            throw new Exception("Video file not found: {$videoPath}");
        }
        if (!file_exists($audioPath)) {
            throw new Exception("Audio file not found: {$audioPath}");
        }

        if ($replaceAudio) {
            // Replace existing audio
            $command = [
                $this->ffmpegPath,
                '-i', $videoPath,
                '-i', $audioPath,
                '-c:v', 'copy',
                '-c:a', 'aac',
                '-b:a', '192k',
                '-map', '0:v:0',
                '-map', '1:a:0',
                '-shortest',
                '-y',
                $outputPath,
            ];
        } else {
            // Mix with existing audio
            $command = [
                $this->ffmpegPath,
                '-i', $videoPath,
                '-i', $audioPath,
                '-filter_complex', '[0:a][1:a]amix=inputs=2:duration=first[aout]',
                '-c:v', 'copy',
                '-map', '0:v:0',
                '-map', '[aout]',
                '-c:a', 'aac',
                '-b:a', '192k',
                '-shortest',
                '-y',
                $outputPath,
            ];
        }

        $result = $this->executeCommand($command);

        return [
            'success' => true,
            'path' => $outputPath,
            'duration' => $this->getVideoDuration($outputPath),
            'size' => filesize($outputPath),
            'execution_time' => $result['execution_time'],
        ];
    }

    /**
     * Resize/scale video to specific dimensions
     *
     * @param string $inputPath Input video path
     * @param string $outputPath Output video path
     * @param int $width Target width
     * @param int $height Target height
     * @param string $scaleMode 'fit' (maintain aspect ratio) or 'fill' (exact size)
     * @return array Result with path and metadata
     */
    public function resizeVideo(
        string $inputPath,
        string $outputPath,
        int $width,
        int $height,
        string $scaleMode = 'fit'
    ): array {
        Log::info('FFmpeg: Resizing video', [
            'input' => basename($inputPath),
            'dimensions' => "{$width}x{$height}",
            'mode' => $scaleMode,
        ]);

        if (!file_exists($inputPath)) {
            throw new Exception("Video file not found: {$inputPath}");
        }

        if ($scaleMode === 'fit') {
            // Maintain aspect ratio, fit within dimensions
            $filterComplex = "scale={$width}:{$height}:force_original_aspect_ratio=decrease,pad={$width}:{$height}:(ow-iw)/2:(oh-ih)/2";
        } else {
            // Exact dimensions (may distort)
            $filterComplex = "scale={$width}:{$height}";
        }

        $command = [
            $this->ffmpegPath,
            '-i', $inputPath,
            '-vf', $filterComplex,
            '-c:v', 'libx264',
            '-preset', 'medium',
            '-crf', '23',
            '-c:a', 'copy',
            '-y',
            $outputPath,
        ];

        $result = $this->executeCommand($command);

        return [
            'success' => true,
            'path' => $outputPath,
            'dimensions' => "{$width}x{$height}",
            'duration' => $this->getVideoDuration($outputPath),
            'size' => filesize($outputPath),
            'execution_time' => $result['execution_time'],
        ];
    }

    /**
     * Convert video to vertical format (9:16 for TikTok/Instagram)
     *
     * @param string $inputPath Input video path
     * @param string $outputPath Output video path
     * @param int $width Target width (default 1080)
     * @param int $height Target height (default 1920)
     * @return array Result with path and metadata
     */
    public function convertToVertical(
        string $inputPath,
        string $outputPath,
        int $width = 1080,
        int $height = 1920
    ): array {
        return $this->resizeVideo($inputPath, $outputPath, $width, $height, 'fit');
    }

    /**
     * Trim video to specific duration
     *
     * @param string $inputPath Input video path
     * @param string $outputPath Output video path
     * @param float $startTime Start time in seconds
     * @param float $duration Duration in seconds
     * @return array Result with path and metadata
     */
    public function trimVideo(
        string $inputPath,
        string $outputPath,
        float $startTime = 0,
        float $duration = 60
    ): array {
        Log::info('FFmpeg: Trimming video', [
            'input' => basename($inputPath),
            'start' => $startTime,
            'duration' => $duration,
        ]);

        if (!file_exists($inputPath)) {
            throw new Exception("Video file not found: {$inputPath}");
        }

        $command = [
            $this->ffmpegPath,
            '-i', $inputPath,
            '-ss', (string)$startTime,
            '-t', (string)$duration,
            '-c:v', 'libx264',
            '-c:a', 'aac',
            '-y',
            $outputPath,
        ];

        $result = $this->executeCommand($command);

        return [
            'success' => true,
            'path' => $outputPath,
            'duration' => $duration,
            'size' => filesize($outputPath),
            'execution_time' => $result['execution_time'],
        ];
    }

    /**
     * Add fade in/out transitions
     *
     * @param string $inputPath Input video path
     * @param string $outputPath Output video path
     * @param float $fadeInDuration Fade in duration in seconds
     * @param float $fadeOutDuration Fade out duration in seconds
     * @return array Result with path and metadata
     */
    public function addFadeTransitions(
        string $inputPath,
        string $outputPath,
        float $fadeInDuration = 1.0,
        float $fadeOutDuration = 1.0
    ): array {
        Log::info('FFmpeg: Adding fade transitions', [
            'input' => basename($inputPath),
            'fade_in' => $fadeInDuration,
            'fade_out' => $fadeOutDuration,
        ]);

        if (!file_exists($inputPath)) {
            throw new Exception("Video file not found: {$inputPath}");
        }

        $videoDuration = $this->getVideoDuration($inputPath);
        $fadeOutStart = $videoDuration - $fadeOutDuration;

        $filterComplex = "fade=t=in:st=0:d={$fadeInDuration},fade=t=out:st={$fadeOutStart}:d={$fadeOutDuration}";

        $command = [
            $this->ffmpegPath,
            '-i', $inputPath,
            '-vf', $filterComplex,
            '-c:a', 'copy',
            '-y',
            $outputPath,
        ];

        $result = $this->executeCommand($command);

        return [
            'success' => true,
            'path' => $outputPath,
            'duration' => $this->getVideoDuration($outputPath),
            'size' => filesize($outputPath),
            'execution_time' => $result['execution_time'],
        ];
    }

    /**
     * Extract audio from video
     *
     * @param string $videoPath Input video path
     * @param string $outputPath Output audio path
     * @param string $format Audio format (mp3, aac, wav)
     * @return array Result with path and metadata
     */
    public function extractAudio(
        string $videoPath,
        string $outputPath,
        string $format = 'mp3'
    ): array {
        Log::info('FFmpeg: Extracting audio', [
            'video' => basename($videoPath),
            'format' => $format,
        ]);

        if (!file_exists($videoPath)) {
            throw new Exception("Video file not found: {$videoPath}");
        }

        $codecMap = [
            'mp3' => 'libmp3lame',
            'aac' => 'aac',
            'wav' => 'pcm_s16le',
        ];

        $codec = $codecMap[$format] ?? 'libmp3lame';

        $command = [
            $this->ffmpegPath,
            '-i', $videoPath,
            '-vn',
            '-c:a', $codec,
            '-b:a', '192k',
            '-y',
            $outputPath,
        ];

        $result = $this->executeCommand($command);

        return [
            'success' => true,
            'path' => $outputPath,
            'format' => $format,
            'duration' => $this->getAudioDuration($outputPath),
            'size' => filesize($outputPath),
            'execution_time' => $result['execution_time'],
        ];
    }

    /**
     * Create video from image with audio
     *
     * @param string $imagePath Input image path
     * @param string $audioPath Input audio path
     * @param string $outputPath Output video path
     * @return array Result with path and metadata
     */
    public function createVideoFromImage(
        string $imagePath,
        string $audioPath,
        string $outputPath
    ): array {
        Log::info('FFmpeg: Creating video from image', [
            'image' => basename($imagePath),
            'audio' => basename($audioPath),
        ]);

        if (!file_exists($imagePath)) {
            throw new Exception("Image file not found: {$imagePath}");
        }
        if (!file_exists($audioPath)) {
            throw new Exception("Audio file not found: {$audioPath}");
        }

        $audioDuration = $this->getAudioDuration($audioPath);

        $command = [
            $this->ffmpegPath,
            '-loop', '1',
            '-i', $imagePath,
            '-i', $audioPath,
            '-c:v', 'libx264',
            '-tune', 'stillimage',
            '-c:a', 'aac',
            '-b:a', '192k',
            '-pix_fmt', 'yuv420p',
            '-shortest',
            '-t', (string)$audioDuration,
            '-y',
            $outputPath,
        ];

        $result = $this->executeCommand($command);

        return [
            'success' => true,
            'path' => $outputPath,
            'duration' => $this->getVideoDuration($outputPath),
            'size' => filesize($outputPath),
            'execution_time' => $result['execution_time'],
        ];
    }

    /**
     * Get video metadata
     *
     * @param string $videoPath Path to video file
     * @return array Video metadata (duration, dimensions, codec, etc.)
     */
    public function getVideoMetadata(string $videoPath): array
    {
        if (!file_exists($videoPath)) {
            throw new Exception("Video file not found: {$videoPath}");
        }

        $command = [
            $this->ffprobePath,
            '-v', 'quiet',
            '-print_format', 'json',
            '-show_format',
            '-show_streams',
            $videoPath,
        ];

        $result = $this->executeCommand($command, 30);
        $metadata = json_decode($result['output'], true);

        $videoStream = collect($metadata['streams'] ?? [])->firstWhere('codec_type', 'video');
        $audioStream = collect($metadata['streams'] ?? [])->firstWhere('codec_type', 'audio');

        return [
            'duration' => (float)($metadata['format']['duration'] ?? 0),
            'size' => (int)($metadata['format']['size'] ?? 0),
            'bitrate' => (int)($metadata['format']['bit_rate'] ?? 0),
            'format' => $metadata['format']['format_name'] ?? null,
            'video' => $videoStream ? [
                'codec' => $videoStream['codec_name'] ?? null,
                'width' => $videoStream['width'] ?? null,
                'height' => $videoStream['height'] ?? null,
                'fps' => $this->parseFps($videoStream['r_frame_rate'] ?? '0/1'),
            ] : null,
            'audio' => $audioStream ? [
                'codec' => $audioStream['codec_name'] ?? null,
                'sample_rate' => $audioStream['sample_rate'] ?? null,
                'channels' => $audioStream['channels'] ?? null,
            ] : null,
        ];
    }

    /**
     * Execute FFmpeg command with progress tracking
     *
     * @param array $command Command array
     * @param int|null $timeout Timeout in seconds
     * @return array Execution result
     */
    protected function executeCommand(array $command, ?int $timeout = null): array
    {
        $startTime = microtime(true);
        $timeout = $timeout ?? $this->timeout;

        $process = new Process($command);
        $process->setTimeout($timeout);

        try {
            $process->run(function ($type, $buffer) {
                if ($type === Process::ERR) {
                    // FFmpeg outputs progress to stderr
                    Log::debug('FFmpeg progress', ['output' => $buffer]);
                }
            });

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $executionTime = microtime(true) - $startTime;

            return [
                'success' => true,
                'output' => $process->getOutput(),
                'execution_time' => round($executionTime, 2),
            ];
        } catch (ProcessFailedException $e) {
            Log::error('FFmpeg command failed', [
                'command' => implode(' ', $command),
                'error' => $e->getMessage(),
                'output' => $process->getErrorOutput(),
            ]);

            throw new Exception("FFmpeg command failed: " . $process->getErrorOutput());
        }
    }

    /**
     * Create concat list file for FFmpeg
     */
    protected function createConcatList(array $filePaths): string
    {
        $tempPath = sys_get_temp_dir() . '/ffmpeg_concat_' . uniqid() . '.txt';

        $content = '';
        foreach ($filePaths as $path) {
            // Escape single quotes in path
            $escapedPath = str_replace("'", "'\\''", $path);
            $content .= "file '{$escapedPath}'\n";
        }

        file_put_contents($tempPath, $content);

        return $tempPath;
    }

    /**
     * Get video duration in seconds
     */
    protected function getVideoDuration(string $videoPath): float
    {
        $command = [
            $this->ffprobePath,
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $videoPath,
        ];

        $result = $this->executeCommand($command, 30);
        return (float)trim($result['output']);
    }

    /**
     * Get audio duration in seconds
     */
    protected function getAudioDuration(string $audioPath): float
    {
        return $this->getVideoDuration($audioPath);
    }

    /**
     * Parse frame rate from FFprobe output
     */
    protected function parseFps(string $fpsString): float
    {
        if (str_contains($fpsString, '/')) {
            [$num, $den] = explode('/', $fpsString);
            return $den > 0 ? round($num / $den, 2) : 0;
        }
        return (float)$fpsString;
    }

    /**
     * Clean up temporary files
     */
    protected function cleanupTempFiles(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];
    }

    /**
     * Set custom timeout for long operations
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Destructor - cleanup temp files
     */
    public function __destruct()
    {
        $this->cleanupTempFiles();
    }
}

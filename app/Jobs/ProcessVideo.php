<?php

namespace App\Jobs;

use App\Enums\VideoResolution;
use App\Enums\VideoStatus;
use App\Models\Video;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use FFMpeg\FFProbe;
use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Filters\Video\ResizeFilter;
use FFMpeg\Format\Video\X264;
use RuntimeException;
use Throwable;

final class ProcessVideo implements ShouldQueue
{
    use Dispatchable, Queueable;

    private const int AUDIO_KBPS = 160;
    // We always encode variants as MP4 (H.264/AAC) for compatibility
    private const string OUTPUT_EXTENSION = 'mp4';

    private FFProbe $ffprobe;
    private FFMpeg $ffmpeg;

    public function __construct(
        public int $videoId
    ) {
        // Configure FFProbe using our config/ffmpeg.php
        $this->ffprobe = FFProbe::create([
            'ffmpeg.binaries' => config('ffmpeg.ffmpeg_binaries'),
            'ffprobe.binaries' => config('ffmpeg.ffprobe_binaries'),
            'timeout' => config('ffmpeg.timeout'),
            'ffmpeg.threads' => config('ffmpeg.threads'),
        ], null, null);

        // Instantiate FFMpeg using our config/ffmpeg.php
        $this->ffmpeg = FFMpeg::create([
            'ffmpeg.binaries' => config('ffmpeg.ffmpeg_binaries'),
            'ffprobe.binaries' => config('ffmpeg.ffprobe_binaries'),
            'timeout' => config('ffmpeg.timeout'),
            'ffmpeg.threads' => config('ffmpeg.threads'),
        ], null, null);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $video = Video::findOrFail($this->videoId);

        try {
            // Get the absolute path of the original file from storage
            $absolutePath = Storage::disk($video->disk)->path($video->path);
            [$originalWidth, $originalHeight] = $this->getOriginalVideoDimensions($absolutePath);

            // Filter out target resolutions larger than the source (no upscaling on either dimension)
            $eligibleResolutions = array_filter(VideoResolution::dimensions(), function ($dim) use ($originalHeight, $originalWidth) {
                return $dim['w'] < $originalWidth && $dim['h'] < $originalHeight;
            });

            if (empty($eligibleResolutions)) {
                // No downsized resolution applicable; skipping conversion and keeping original only.
                return;
            }

            $pathInfo = pathinfo($video->path);
            $basename = $pathInfo['filename'];
            $dirname = $pathInfo['dirname'];

            // Iterate only over eligible resolutions
            foreach ($eligibleResolutions as $resolution => $dimensions) {
                try {
                    // Build output relative path next to original (e.g., userId/videos/name_720p.mp4)
                    $outputRelative = sprintf('%s/%s_%s.%s',
                        $dirname,
                        $basename,
                        $resolution,
                        self::OUTPUT_EXTENSION
                    );

                    // Resolve absolute output path and delete existing file if any
                    $outputAbsolute = Storage::disk($video->disk)->path($outputRelative);

                    // Open source video
                    $media = $this->ffmpeg->open($absolutePath);

                    // Apply resize while preserving aspect ratio (no upscale guaranteed by eligibility)
                    $media->filters()
                        ->resize(
                            new Dimension($dimensions['w'], $dimensions['h']),
                            ResizeFilter::RESIZEMODE_FIT,
                            true
                        );

                    // Choose encoding parameters
                    $format = new X264('aac', 'libx264')
                        ->setKiloBitrate(VideoResolution::bitrates()[$resolution] ?? 4000)
                        ->setAudioKiloBitrate(self::AUDIO_KBPS)
                        ->setAdditionalParameters(['-movflags', '+faststart']);

                    // Save
                    $media->save($format, $outputAbsolute);

                    $video->variants()
                        ->create([
                            'name' => basename($outputRelative),
                            'resolution' => VideoResolution::from($resolution),
                            'type' => $video->type,
                            'disk' => $video->disk,
                            'path' => $outputRelative,
                        ]);
                } catch (Throwable $renditionError) {
                    Log::error('Rendition conversion failed', [
                        'video_id' => $video->id,
                        'label' => $resolution,
                        'error' => $renditionError->getMessage(),
                    ]);
                    // Continue with other resolutions
                }
            }

            $video->update([
                'status' => VideoStatus::Complete,
            ]);
        } catch (Throwable $e) {
            Log::error('Converting the video failed', [
                'video_id' => $video->id,
                'error' => $e->getMessage()
            ]);

            // Updating status/error fields
            try {
                $video->update([
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ]);
            } catch (Throwable) {}
        }

        // TODO: Notify user via email
    }

    private function getOriginalVideoDimensions(string $absolutePath): array
    {
        // Probe original video dimensions
        $videoStream = $this->ffprobe
            ->streams($absolutePath)
            ->videos()
            ->first();

        if (!$videoStream) {
            throw new RuntimeException('No video stream found.');
        }

        $originalWidth = (int)$videoStream->get('width');
        $originalHeight = (int)$videoStream->get('height');

        if ($originalWidth <= 0 || $originalHeight <= 0) {
            throw new RuntimeException('Unable to read original video dimensions.');
        }

        return [
            $originalWidth,
            $originalHeight,
        ];
    }
}

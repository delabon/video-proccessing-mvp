<?php

namespace App\Jobs;

use App\Enums\VideoResolution;
use App\Enums\VideoStatus;
use App\Events\VideoProcessed;
use App\Models\Video;
use Exception;
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

    private ?FFProbe $ffprobe = null;
    private ?FFMpeg $ffmpeg = null;

    public function __construct(
        public int $videoId
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $video = Video::query()
            ->where('status', VideoStatus::Uploaded->value)
            ->findOrFail($this->videoId);

        try {
            $this->ffmpeg = $this->createFFMpeg();
            $this->ffprobe = $this->createFFProbe();

            $video->update(['status' => VideoStatus::Processing]);

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
                $this->generateVariant(
                    $dirname,
                    $basename,
                    $resolution,
                    $video,
                    $absolutePath,
                    $dimensions
                );
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

        event(new VideoProcessed($video));
    }

    private function getOriginalVideoDimensions(string $absolutePath): array
    {
        try {
            Log::debug('FFProbe debugging', [
                'absolute_path' => $absolutePath,
                'file_exists' => file_exists($absolutePath),
                'file_size' => file_exists($absolutePath) ? filesize($absolutePath) : 0,
            ]);

            // Get all streams first
            $allStreams = $this->ffprobe->streams($absolutePath);
            Log::debug('All streams', [
                'streams_count' => $allStreams ? $allStreams->count() : 'NULL',
                'streams_type' => gettype($allStreams),
            ]);

            if (!$allStreams) {
                throw new RuntimeException('FFProbe returned null for streams - file may be corrupted or unsupported format');
            }

            // Get video streams
            $videoStreams = $allStreams->videos();
            Log::debug('Video streams', [
                'video_streams_count' => $videoStreams->count(),
                'video_streams' => $videoStreams->count() > 0 ? 'available' : 'none',
            ]);

            $videoStream = $videoStreams->first();

            if (!$videoStream) {
                // Try to get any stream that might have video properties
                $firstStream = $allStreams->first();
                if ($firstStream) {
                    Log::debug('First stream info', [
                        'codec_type' => $firstStream->get('codec_type'),
                        'width' => $firstStream->get('width'),
                        'height' => $firstStream->get('height'),
                    ]);
                }
                throw new RuntimeException('No video stream found in file.');
            }

            $originalWidth = (int)$videoStream->get('width');
            $originalHeight = (int)$videoStream->get('height');

            Log::debug('Video stream properties', [
                'width' => $originalWidth,
                'height' => $originalHeight,
                'codec_name' => $videoStream->get('codec_name'),
                'duration' => $videoStream->get('duration'),
            ]);

            if ($originalWidth <= 0 || $originalHeight <= 0) {
                throw new RuntimeException('Unable to read valid video dimensions from stream.');
            }

            return [$originalWidth, $originalHeight];

        } catch (Throwable $e) {
            Log::error('FFProbe analysis failed', [
                'path' => $absolutePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function generateVariant(
        string $dirname,
        string $basename,
        string $resolution,
        Video $video,
        string $absolutePath,
        array $dimensions
    ): void {
        try {
            // Build output relative path next to original (e.g., userId/videos/name_720p.mp4)
            $outputRelative = sprintf(
                '%s/%s_%s.%s',
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

    private function createFFProbe(): FFProbe
    {
        $config = $this->getFfmpegConfig();

        Log::debug('Creating FFProbe with config', [
            'ffprobe_binaries' => $config['ffprobe.binaries'],
            'ffprobe_exists' => file_exists($config['ffprobe.binaries']),
            'ffprobe_executable' => is_executable($config['ffprobe.binaries']),
        ]);

        try {
            return FFProbe::create($config);
        } catch (\Exception $e) {
            Log::error('FFProbe creation failed', [
                'config' => $config,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException('FFProbe initialization failed: ' . $e->getMessage());
        }
    }

    private function createFFMpeg(): FFMpeg
    {
        $config = $this->getFfmpegConfig();

        Log::debug('Creating FFMpeg with config', [
            'ffmpeg_binaries' => $config['ffmpeg.binaries'],
            'ffmpeg_exists' => file_exists($config['ffmpeg.binaries']),
            'ffmpeg_executable' => is_executable($config['ffmpeg.binaries']),
        ]);

        try {
            return FFMpeg::create($config);
        } catch (\Exception $e) {
            Log::error('FFMpeg creation failed', [
                'config' => $config,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException('FFMpeg initialization failed: ' . $e->getMessage());
        }
    }

    private function getFfmpegConfig(): array
    {
        return [
            'ffmpeg.binaries' => config('ffmpeg.ffmpeg_binaries'),
            'ffprobe.binaries' => config('ffmpeg.ffprobe_binaries'),
            'timeout' => config('ffmpeg.timeout'),
            'ffmpeg.threads' => config('ffmpeg.threads'),
        ];
    }
}

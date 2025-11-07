<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\VideoStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\VideoUploadRequest;
use App\Jobs\ProcessVideo;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Throwable;

final class VideoUploaderController extends Controller
{
    public function __invoke(VideoUploadRequest $request): JsonResponse
    {
        $user = $request->user();
        $file = $request->file('video');
        $disk = config('filesystems.default');

        $path = $file->store($user->id . '/videos');

        if (! $path) {
            // Error storing the video.
            Log::error('Video upload failed', [
                'user_id' => $user->id,
                'disk' => $disk,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);

            return response()->json([
                'message' => 'Failed uploading the video, please try again later.'
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            DB::beginTransaction();

            // Why using a transaction here? well because we're creating a video and a job in DB for processing the video in the queue
            $video = $user->videos()
                ->create([
                    'name' => $file->getClientOriginalName(),
                    'type' => $file->getClientMimeType(),
                    'disk' => $disk,
                    'path' => $path,
                    'status' => VideoStatus::Uploaded,
                ]);

            ProcessVideo::dispatch($video->id);

            DB::commit();

            return response()
                ->json([
                    'id' => $video->id,
                ])
                ->setStatusCode(ResponseAlias::HTTP_ACCEPTED)
                ->header('Retry-After', 300);
        } catch (Throwable $e) {
            DB::rollBack();

            // Remove the video
            try {
                Storage::disk($disk)->delete($path);
            } catch (Throwable) {}

            Log::error('Upload failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed uploading the video, please try again later.'
            ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

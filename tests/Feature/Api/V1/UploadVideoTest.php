<?php

use App\Jobs\ProcessVideo;
use App\Models\User;
use App\Models\Video;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // No global setup required yet. Individual tests will fake storage / bus as needed.
});

it('returns 401 for unauthenticated requests', function () {
    $response = $this->postJson('/api/v1/videos/upload');

    $response->assertStatus(401);
});

it('validates the video input and fails for missing or wrong file', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'sanctum');

    // Missing file
    $response = $this->postJson('/api/v1/videos/upload', []);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors('video');

    // Wrong mimetype
    Storage::fake(config('filesystems.default'));
    $badFile = UploadedFile::fake()->create('not-a-video.txt', 10, 'text/plain');

    $response = $this->postJson('/api/v1/videos/upload', ['video' => $badFile]);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors('video');
});

it('stores an uploaded video, creates a DB record and dispatches the processing job', function () {
    Storage::fake(config('filesystems.default'));
    Bus::fake();

    $user = User::factory()->create();
    $this->actingAs($user, 'sanctum');

    $file = UploadedFile::fake()->create('video.mp4', 5120, 'video/mp4'); // 5MB

    $response = $this->postJson('/api/v1/videos/upload', [
        'video' => $file,
    ]);

    $response->assertStatus(202);

    $id = $response->json('id');
    expect($id)->toBeInt();

    $video = Video::find($id);
    expect($video)->not->toBeNull();

    expect(Storage::disk($video->disk)->exists($video->path))->toBeTrue();

    Bus::assertDispatched(ProcessVideo::class, function ($job) use ($video) {
        return $job->videoId === $video->id;
    });
});

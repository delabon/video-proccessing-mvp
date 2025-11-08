<?php

namespace App\Listeners;

use App\Events\VideoProcessed;
use App\Mail\VideoProcessedEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

final class AfterVideoProcessed
{
    public function handle(VideoProcessed $event): void
    {
        $user = $event->video->user;
        Mail::to($user->email)->sendNow(new VideoProcessedEmail());
    }
}

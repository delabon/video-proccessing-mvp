<?php

use App\Repositories\VideoRepository;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {
    public int $count = 0;

    public function mount(): void
    {
        $this->countVideos();
    }

    #[On('re-count-videos')]
    public function countVideos(): void
    {
        $this->count = app(VideoRepository::class)->userVideosCount(auth()->user());
    }
}; ?>

<span class="border rounded-full p-0.5 text-xs w-[22px] h-[22px] inline-flex items-center justify-center">
    {{ $count }}
</span>

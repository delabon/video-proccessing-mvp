<?php

use App\Models\Video;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public function with(): array
    {
        return [
            'videos' => auth()->user()
                ->videos()
                ->with(['variants'])
                ->latest()
                ->simplePaginate(10),
        ];
    }
}; ?>

<div>
    @if($videos->isNotEmpty())
        <table class="table-auto w-full">
            <thead>
                <tr class="align-top">
                    <th class="text-left border-b">ID</th>
                    <th class="text-left border-b">Name</th>
                    <th class="text-left border-b">Status</th>
                    <th class="text-left border-b">Resolutions</th>
                    <th class="text-left border-b">Uploaded At</th>
                    <th class="text-left border-b">Updated At</th>
                </tr>
            </thead>
            <tbody>
            @foreach($videos as $video)
                <tr class="align-top">
                    <td>{{ $video->id }}</td>
                    <td>{{ $video->name }}</td>
                    <td>{{ $video->status->label() }}</td>
                    <td>
                        <div class="flex flex-wrap gap-3">
                            <a href="{{ route('videos.download', $video->id) }}" target="_blank" class="underline">Original</a>

                            @if(!empty($video->variants))
                                @foreach($video->variants as $variant)
                                    <a href="{{ route('videos.variants.download', $variant->id) }}" target="_blank" class="underline">{{ $variant->resolution->value }}</a>
                                @endforeach
                            @endif
                        </div>
                    </td>
                    <td>{{ $video->created_at->format('M j, Y') }}</td>
                    <td>{{ $video->updated_at->format('M j, Y') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="mt-6">
            {{ $videos->links() }}
        </div>
    @else
        <p>No videos yet. Please upload one using our API.</p>
    @endif
</div>

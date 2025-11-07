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
                    @if(!empty($video->variants))
                        <div class="flex flex-wrap gap-3">
                            @foreach($video->variants as $variant)
                                <a href="#" class="underline">{{ $variant->resolution->value }}</a>
                            @endforeach
                        </div>
                    @endif
                </td>
                <td>{{ $video->created_at->format('j M, Y') }}</td>
                <td>{{ $video->updated_at->format('j M, Y') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="mt-6">
        {{ $videos->links() }}
    </div>
</div>

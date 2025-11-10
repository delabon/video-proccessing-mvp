<x-layouts.app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <h1 class="font-bold text-2xl">Videos</h1>
        </div>
        <div class="relative p-6 h-full flex-1 overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <livewire:videos.list/>
        </div>
    </div>
</x-layouts.app>

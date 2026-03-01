<div class="flex items-center gap-2">
    <select
        wire:model.live="franchiseId"
        class="block rounded-lg border-none bg-white/5 py-1.5 pe-8 ps-3 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
    >
        @foreach ($franchises as $id => $name)
            <option value="{{ $id }}">{{ $name }}</option>
        @endforeach
    </select>
</div>

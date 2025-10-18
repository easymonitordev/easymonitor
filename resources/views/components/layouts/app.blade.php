<x-layouts.app.sidebar :title="$title ?? null">
    <main class="flex-1 p-6">
        {{ $slot }}
    </main>
</x-layouts.app.sidebar>


@props([
    'caption' => null,
])

<div {{ $attributes->class(['w-full overflow-x-auto rounded-lg border border-gray-700']) }}>
    <table class="w-full min-w-full border-collapse text-left text-sm text-gray-200">
        @if (filled($caption))
            <caption class="px-4 py-3 text-left text-sm font-medium text-gray-300">
                {{ $caption }}
            </caption>
        @endif

        {{ $slot }}
    </table>
</div>

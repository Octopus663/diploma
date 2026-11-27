@props(['title' => null])
<div {{ $attributes->merge(['class' => 'bg-white overflow-hidden shadow-sm sm:rounded-lg']) }}>
    @if ($title)
        <div class="px-6 py-4 border-b border-gray-100 font-semibold text-gray-800">{{ $title }}</div>
    @endif
    <div class="p-6">
        {{ $slot }}
    </div>
</div>

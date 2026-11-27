@props(['status'])
@php
    $labels = [
        'new' => 'Нове', 'processing' => 'В роботі', 'completed' => 'Виконане', 'cancelled' => 'Скасоване',
        'draft' => 'Чернетка', 'issued' => 'Виданий', 'signed' => 'Підписаний',
    ];
    $colors = [
        'new' => 'bg-blue-100 text-blue-800', 'processing' => 'bg-yellow-100 text-yellow-800',
        'completed' => 'bg-green-100 text-green-800', 'cancelled' => 'bg-gray-200 text-gray-700',
        'draft' => 'bg-gray-100 text-gray-700', 'issued' => 'bg-blue-100 text-blue-800', 'signed' => 'bg-green-100 text-green-800',
    ];
@endphp
<span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $colors[$status] ?? 'bg-gray-100 text-gray-700' }}">
    {{ $labels[$status] ?? $status }}
</span>

@props(['value', 'currency' => 'грн'])
<span class="whitespace-nowrap">{{ number_format((float) $value, 2, ',', ' ') }} {{ $currency }}</span>

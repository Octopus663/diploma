@php($payload = $document->payload)
<x-app-layout>
    <x-slot name="title">{{ $document->number }}</x-slot>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $document->title }}</h2>
            <x-status-badge :status="$document->status" />
            <div class="ms-auto flex gap-2">
                <a href="{{ route('documents.export.csv', $document) }}" class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 rounded-md text-xs font-semibold text-gray-700 uppercase hover:bg-gray-50">CSV</a>
                <a href="{{ route('documents.export.json', $document) }}" class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 rounded-md text-xs font-semibold text-gray-700 uppercase hover:bg-gray-50">JSON</a>
            </div>
        </div>
        <p class="text-sm text-gray-500 mt-1">
            {{ $document->number }} · {{ $document->template->name }}
            @if ($document->order)
                · <a class="text-blue-600 hover:underline" href="{{ route('orders.show', $document->order) }}">{{ $document->order->number }}</a>
            @endif
        </p>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid md:grid-cols-3 gap-4">
                <x-stat-card label="Контрагент" :value="$document->counterparty->name" />
                <x-stat-card label="Сума" :value="$document->amount !== null ? number_format((float) $document->amount, 2, ',', ' ').' грн' : '—'" />
                <x-stat-card label="Виданий" :value="$document->issued_at?->format('d.m.Y') ?? '—'" />
            </div>

            @if (! empty($payload['lines']))
                <x-card title="Рядки документа">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-left text-gray-500 border-b">
                                <tr>
                                    <th class="py-2 pr-4">Артикул</th>
                                    <th class="py-2 pr-4">Найменування</th>
                                    <th class="py-2 pr-4 text-right">Кількість</th>
                                    <th class="py-2 pr-4 text-right">Ціна</th>
                                    <th class="py-2 text-right">Сума</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($payload['lines'] as $line)
                                    <tr class="border-b">
                                        <td class="py-2 pr-4 text-gray-500">{{ $line['sku'] }}</td>
                                        <td class="py-2 pr-4">{{ $line['name'] }}</td>
                                        <td class="py-2 pr-4 text-right">{{ $line['quantity'] }} {{ $line['unit'] }}</td>
                                        <td class="py-2 pr-4 text-right"><x-money :value="$line['price']" /></td>
                                        <td class="py-2 text-right"><x-money :value="$line['total']" /></td>
                                    </tr>
                                @endforeach
                                <tr><td colspan="4" class="py-2 text-right font-semibold">Разом</td><td class="py-2 text-right font-semibold"><x-money :value="$payload['total'] ?? 0" /></td></tr>
                                <tr><td colspan="4" class="py-2 text-right text-gray-500">у т.ч. ПДВ</td><td class="py-2 text-right text-gray-500"><x-money :value="$payload['vat'] ?? 0" /></td></tr>
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif

            <x-card title="Дані документа (payload)">
                <pre class="text-xs bg-gray-50 p-4 rounded overflow-x-auto">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </x-card>
        </div>
    </div>
</x-app-layout>

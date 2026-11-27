@php($showCounterparty = $showCounterparty ?? true)
<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="text-left text-gray-500 border-b">
            <tr>
                <th class="py-2 pr-4">Номер</th>
                <th class="py-2 pr-4">Назва</th>
                <th class="py-2 pr-4">Шаблон</th>
                @if ($showCounterparty)
                    <th class="py-2 pr-4">Контрагент</th>
                @endif
                <th class="py-2 pr-4">Статус</th>
                <th class="py-2 text-right">Сума</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($documents as $document)
                <tr class="border-b last:border-0 hover:bg-gray-50">
                    <td class="py-2 pr-4"><a class="text-blue-600 hover:underline" href="{{ route('documents.show', $document) }}">{{ $document->number }}</a></td>
                    <td class="py-2 pr-4">{{ $document->title }}</td>
                    <td class="py-2 pr-4 text-gray-500">{{ $document->template?->name }}</td>
                    @if ($showCounterparty)
                        <td class="py-2 pr-4">{{ $document->counterparty?->name }}</td>
                    @endif
                    <td class="py-2 pr-4"><x-status-badge :status="$document->status" /></td>
                    <td class="py-2 text-right">
                        @if ($document->amount !== null)
                            <x-money :value="$document->amount" />
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-gray-500">Документів немає</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

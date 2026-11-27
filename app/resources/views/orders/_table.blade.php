<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="text-left text-gray-500 border-b">
            <tr>
                <th class="py-2 pr-4">Номер</th>
                <th class="py-2 pr-4">Дата</th>
                <th class="py-2 pr-4">Контрагент</th>
                <th class="py-2 pr-4">Статус</th>
                @isset($orders->first()->items_count)
                    <th class="py-2 pr-4 text-right">Позицій</th>
                @endisset
                <th class="py-2 text-right">Сума</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($orders as $order)
                <tr class="border-b last:border-0 hover:bg-gray-50">
                    <td class="py-2 pr-4"><a class="text-blue-600 hover:underline" href="{{ route('orders.show', $order) }}">{{ $order->number }}</a></td>
                    <td class="py-2 pr-4 whitespace-nowrap">{{ $order->ordered_at?->format('d.m.Y') }}</td>
                    <td class="py-2 pr-4">
                        @if ($order->relationLoaded('counterparty') && $order->counterparty)
                            <a class="hover:underline" href="{{ route('counterparties.show', $order->counterparty) }}">{{ $order->counterparty->name }}</a>
                        @endif
                    </td>
                    <td class="py-2 pr-4"><x-status-badge :status="$order->status" /></td>
                    @isset($order->items_count)
                        <td class="py-2 pr-4 text-right">{{ $order->items_count }}</td>
                    @endisset
                    <td class="py-2 text-right"><x-money :value="$order->total" /></td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-gray-500">Немає замовлень</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

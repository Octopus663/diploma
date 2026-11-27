<x-app-layout>
    <x-slot name="title">{{ $order->number }}</x-slot>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Замовлення {{ $order->number }}</h2>
            <x-status-badge :status="$order->status" />
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid md:grid-cols-3 gap-4">
                <x-stat-card label="Дата" :value="$order->ordered_at?->format('d.m.Y')" />
                <x-stat-card label="Контрагент" :value="$order->counterparty->name" />
                <x-stat-card label="Сума" :value="number_format((float) $order->total, 2, ',', ' ').' '.$order->currency" />
            </div>

            <x-card title="Позиції">
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
                            @foreach ($order->items as $item)
                                <tr class="border-b last:border-0">
                                    <td class="py-2 pr-4 text-gray-500">{{ $item->product->sku }}</td>
                                    <td class="py-2 pr-4">{{ $item->product->name }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $item->quantity }} {{ $item->product->unit }}</td>
                                    <td class="py-2 pr-4 text-right"><x-money :value="$item->price" /></td>
                                    <td class="py-2 text-right"><x-money :value="$item->total" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            <div class="grid md:grid-cols-2 gap-6">
                <x-card title="Сформувати документ">
                    <form method="POST" action="{{ route('orders.documents.store', $order) }}" class="flex flex-wrap gap-3 items-end">
                        @csrf
                        <div class="grow">
                            <x-input-label for="template_id" value="Шаблон" />
                            <select id="template_id" name="template_id" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                @foreach ($templates as $template)
                                    <option value="{{ $template->id }}">{{ $template->name }} ({{ $template->type }})</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('template_id')" class="mt-2" />
                        </div>
                        <x-primary-button>Сформувати</x-primary-button>
                    </form>
                </x-card>

                <x-card title="Документи замовлення">
                    @forelse ($order->documents as $document)
                        <div class="flex justify-between py-1 text-sm">
                            <a class="text-blue-600 hover:underline" href="{{ route('documents.show', $document) }}">{{ $document->number }}</a>
                            <span class="text-gray-500">{{ $document->template->name }}</span>
                            <x-status-badge :status="$document->status" />
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Документів ще немає.</p>
                    @endforelse
                </x-card>
            </div>
        </div>
    </div>
</x-app-layout>

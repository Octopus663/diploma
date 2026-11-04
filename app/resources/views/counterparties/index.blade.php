<x-app-layout>
    <x-slot name="title">Контрагенти</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Контрагенти</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" class="bg-white shadow-sm sm:rounded-lg p-4 flex flex-wrap gap-3 items-end">
                <div class="grow">
                    <x-input-label for="q" value="Назва або код ЄДРПОУ" />
                    <x-text-input id="q" name="q" :value="request('q')" class="mt-1 w-full" />
                </div>
                <div>
                    <x-input-label for="type" value="Тип" />
                    <select id="type" name="type" class="mt-1 border-gray-300 rounded-md shadow-sm">
                        <option value="">Усі</option>
                        <option value="company" @selected(request('type') === 'company')>Юридична особа</option>
                        <option value="individual" @selected(request('type') === 'individual')>Фізична особа</option>
                    </select>
                </div>
                <x-primary-button>Знайти</x-primary-button>
            </form>

            <x-card>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-gray-500 border-b">
                            <tr>
                                <th class="py-2 pr-4">Назва</th>
                                <th class="py-2 pr-4">Код</th>
                                <th class="py-2 pr-4">Місто</th>
                                <th class="py-2 text-right">Замовлень</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($counterparties as $counterparty)
                                <tr class="border-b last:border-0 hover:bg-gray-50">
                                    <td class="py-2 pr-4"><a class="text-blue-600 hover:underline" href="{{ route('counterparties.show', $counterparty) }}">{{ $counterparty->name }}</a></td>
                                    <td class="py-2 pr-4 text-gray-500">{{ $counterparty->code }}</td>
                                    <td class="py-2 pr-4">{{ $counterparty->city }}</td>
                                    <td class="py-2 text-right">{{ $counterparty->orders_count }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-gray-500">Нічого не знайдено</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $counterparties->links() }}</div>
            </x-card>
        </div>
    </div>
</x-app-layout>

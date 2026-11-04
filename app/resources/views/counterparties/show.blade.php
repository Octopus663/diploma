<x-app-layout>
    <x-slot name="title">{{ $counterparty->name }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $counterparty->name }}</h2>
        <p class="text-sm text-gray-500 mt-1">
            {{ $counterparty->type === 'company' ? 'Юридична особа' : 'Фізична особа' }} · код {{ $counterparty->code }} · {{ $counterparty->city }}, {{ $counterparty->address }}
        </p>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid md:grid-cols-4 gap-4">
                <x-stat-card label="Замовлень" :value="$counterparty->orders_count" />
                <x-stat-card label="Документів" :value="$counterparty->documents_count" />
                <x-stat-card label="Оборот" :value="number_format($turnover, 2, ',', ' ').' грн'" />
                <x-stat-card label="Контакти" :value="$counterparty->email" />
            </div>

            <x-card title="Замовлення">
                @include('orders._table', ['orders' => $orders])
                <div class="mt-4">{{ $orders->links() }}</div>
            </x-card>

            <x-card title="Останні документи">
                @include('documents._table', ['documents' => $documents, 'showCounterparty' => false])
            </x-card>
        </div>
    </div>
</x-app-layout>

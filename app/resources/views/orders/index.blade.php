<x-app-layout>
    <x-slot name="title">Замовлення</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Замовлення</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" class="bg-white shadow-sm sm:rounded-lg p-4 flex flex-wrap gap-3 items-end">
                <div>
                    <x-input-label for="q" value="Номер" />
                    <x-text-input id="q" name="q" :value="request('q')" class="mt-1 w-40" placeholder="ORD-..." />
                </div>
                <div>
                    <x-input-label for="status" value="Статус" />
                    <select id="status" name="status" class="mt-1 border-gray-300 rounded-md shadow-sm">
                        <option value="">Усі</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="from" value="З" />
                    <x-text-input id="from" type="date" name="from" :value="request('from')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="to" value="По" />
                    <x-text-input id="to" type="date" name="to" :value="request('to')" class="mt-1" />
                </div>
                <x-primary-button>Фільтрувати</x-primary-button>
            </form>

            <x-card>
                @include('orders._table', ['orders' => $orders])
                <div class="mt-4">{{ $orders->links() }}</div>
            </x-card>
        </div>
    </div>
</x-app-layout>

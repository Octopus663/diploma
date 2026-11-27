<x-app-layout>
    <x-slot name="title">Документи</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Документи та звіти</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" class="bg-white shadow-sm sm:rounded-lg p-4 flex flex-wrap gap-3 items-end">
                <div>
                    <x-input-label for="status" value="Статус" />
                    <select id="status" name="status" class="mt-1 border-gray-300 rounded-md shadow-sm">
                        <option value="">Усі</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <x-primary-button>Фільтрувати</x-primary-button>
                <p class="text-sm text-gray-500 ms-auto">Новий документ формується зі сторінки замовлення.</p>
            </form>

            <x-card>
                @include('documents._table', ['documents' => $documents])
                <div class="mt-4">{{ $documents->links() }}</div>
            </x-card>
        </div>
    </div>
</x-app-layout>

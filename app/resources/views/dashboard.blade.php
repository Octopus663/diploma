<x-app-layout>
    <x-slot name="title">Огляд</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Огляд</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <x-stat-card label="Контрагенти" :value="number_format($stats['counterparties'], 0, ',', ' ')" />
                <x-stat-card label="Замовлення" :value="number_format($stats['orders'], 0, ',', ' ')" />
                <x-stat-card label="Документи" :value="number_format($stats['documents'], 0, ',', ' ')" />
                <x-stat-card label="Оборот (без скасованих)" :value="number_format($stats['revenue'], 0, ',', ' ').' грн'" />
            </div>

            <div class="grid lg:grid-cols-3 gap-6">
                <x-card title="Оборот за місяцями" class="lg:col-span-2">
                    <div class="h-72">
                        <canvas data-chart='@json($monthlyChart)'></canvas>
                    </div>
                </x-card>

                <x-card title="Замовлення за статусами">
                    <table class="w-full text-sm">
                        @foreach ($byStatus as $row)
                            <tr class="border-b last:border-0">
                                <td class="py-2"><x-status-badge :status="$row->status" /></td>
                                <td class="py-2 text-right">{{ number_format($row->cnt, 0, ',', ' ') }}</td>
                                <td class="py-2 text-right text-gray-500"><x-money :value="$row->amount" /></td>
                            </tr>
                        @endforeach
                    </table>
                </x-card>
            </div>

            <x-card title="Останні замовлення">
                @include('orders._table', ['orders' => $latest])
            </x-card>
        </div>
    </div>
</x-app-layout>

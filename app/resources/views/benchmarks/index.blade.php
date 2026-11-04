<x-app-layout>
    <x-slot name="title">Бенчмарки</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Результати вимірювань</h2>
        <p class="text-sm text-gray-500 mt-1">
            Один і той самий застосунок у чотирьох режимах: PHP-FPM, RoadRunner, Swoole, FrankenPHP (1 vCPU, 512 MB).
            @if ($updatedAt) Дані оновлено {{ $updatedAt }}. @endif
        </p>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @unless ($hasData)
                <x-card>
                    <p class="text-gray-600">Результатів ще немає. Запустіть <code>./bench/run.sh</code>, потім <code>python analysis/parse.py</code>: зведений файл з'явиться в <code>app/resources/benchmarks/summary.csv</code>.</p>
                </x-card>
            @else
                <x-card title="Пікова пропускна здатність">
                    <div class="h-80"><canvas data-chart='@json($peakChart)'></canvas></div>

                    <div class="overflow-x-auto mt-6">
                        <table class="w-full text-sm">
                            <thead class="text-left text-gray-500 border-b">
                                <tr>
                                    <th class="py-2 pr-4">Сценарій</th>
                                    @foreach ($modes as $mode)
                                        <th class="py-2 pr-4 text-right">{{ $labels[$mode] }}, RPS</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($peakTable as $row)
                                    <tr class="border-b last:border-0">
                                        <td class="py-2 pr-4 font-medium">{{ $row['scenario'] }}</td>
                                        @foreach ($modes as $mode)
                                            <td class="py-2 pr-4 text-right">
                                                {{ $row[$mode] > 0 ? number_format($row[$mode], 0, ',', ' ') : '—' }}
                                                @if ($row[$mode.'_gain'] !== null && $row[$mode] > 0)
                                                    <span class="{{ $row[$mode.'_gain'] >= 0 ? 'text-green-600' : 'text-red-600' }} text-xs">
                                                        ({{ $row[$mode.'_gain'] >= 0 ? '+' : '' }}{{ $row[$mode.'_gain'] }}%)
                                                    </span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>

                <div x-data="{ tab: 0 }" class="space-y-4">
                    <div class="flex flex-wrap gap-2">
                        @foreach ($scenarios as $i => $scenario)
                            <button type="button" @click="tab = {{ $i }}"
                                :class="tab === {{ $i }} ? 'bg-gray-800 text-white' : 'bg-white text-gray-700'"
                                class="px-4 py-2 rounded-md shadow-sm text-sm font-medium">{{ $scenario }}</button>
                        @endforeach
                    </div>

                    @foreach ($scenarios as $i => $scenario)
                        <div x-show="tab === {{ $i }}" class="grid lg:grid-cols-3 gap-6">
                            <x-card><div class="h-72"><canvas data-chart='@json($rpsCharts[$i])'></canvas></div></x-card>
                            <x-card><div class="h-72"><canvas data-chart='@json($latencyCharts[$i])'></canvas></div></x-card>
                            <x-card><div class="h-72"><canvas data-chart='@json($memoryCharts[$i])'></canvas></div></x-card>
                        </div>
                    @endforeach
                </div>
            @endunless
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="title">Про стенд</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Про стенд</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid lg:grid-cols-2 gap-6">
                <x-card title="Цей запит обслуговує">
                    <table class="w-full text-sm">
                        @foreach ($server as $label => $value)
                            <tr class="border-b last:border-0">
                                <td class="py-2 pr-4 text-gray-500">{{ $label }}</td>
                                <td class="py-2 font-medium">{{ $value }}</td>
                            </tr>
                        @endforeach
                    </table>
                    <p class="mt-4 text-sm text-gray-600">
                        @if ($persistent)
                            Оновіть сторінку кілька разів: PID повторюється, бо застосунок живе в пам'яті воркера і не завантажується заново для кожного запиту.
                        @else
                            У режимі PHP-FPM кожен запит заново завантажує фреймворк (модель Share-Nothing), хоча процес FPM може бути той самий.
                        @endif
                    </p>
                </x-card>

                <x-card title="Режими стенду">
                    <table class="w-full text-sm">
                        <tr class="border-b"><td class="py-2 pr-4 font-medium">PHP-FPM + Nginx</td><td class="py-2 text-gray-500">:8081</td><td class="py-2 text-gray-600">класична модель, ініціалізація на кожен запит</td></tr>
                        <tr class="border-b"><td class="py-2 pr-4 font-medium">Octane + RoadRunner</td><td class="py-2 text-gray-500">:8082</td><td class="py-2 text-gray-600">сервер на Go, воркери PHP у пам'яті</td></tr>
                        <tr class="border-b"><td class="py-2 pr-4 font-medium">Octane + Swoole</td><td class="py-2 text-gray-500">:8083</td><td class="py-2 text-gray-600">розширення PHP з власним event loop</td></tr>
                        <tr><td class="py-2 pr-4 font-medium">Octane + FrankenPHP</td><td class="py-2 text-gray-500">:8084</td><td class="py-2 text-gray-600">PHP вбудований у вебсервер Caddy</td></tr>
                    </table>
                </x-card>
            </div>

            <x-card title="Життєвий цикл запиту">
                <div class="grid md:grid-cols-2 gap-6 text-sm">
                    <div>
                        <div class="font-semibold mb-2">PHP-FPM</div>
                        <ol class="list-decimal ms-5 space-y-1 text-gray-700">
                            <li>Nginx передає запит у FastCGI</li>
                            <li>Завантаження autoload, конфігурації, сервіс-провайдерів</li>
                            <li>Створення контейнера, ORM, маршрутизатора</li>
                            <li>Обробка запиту</li>
                            <li>Знищення всього стану</li>
                        </ol>
                    </div>
                    <div>
                        <div class="font-semibold mb-2">Laravel Octane</div>
                        <ol class="list-decimal ms-5 space-y-1 text-gray-700">
                            <li>Застосунок завантажується один раз при старті воркера</li>
                            <li>Сервер передає запит готовому воркеру</li>
                            <li>Клон контейнера (sandbox), скидання стану між запитами</li>
                            <li>Обробка запиту</li>
                            <li>Воркер чекає наступний запит (перезапуск після --max-requests)</li>
                        </ol>
                    </div>
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>

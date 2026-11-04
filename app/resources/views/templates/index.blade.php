<x-app-layout>
    <x-slot name="title">Шаблони</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Шаблони документів</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-card>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-gray-500 border-b">
                            <tr>
                                <th class="py-2 pr-4">Код</th>
                                <th class="py-2 pr-4">Назва</th>
                                <th class="py-2 pr-4">Тип</th>
                                <th class="py-2 pr-4">Поля</th>
                                <th class="py-2 pr-4 text-right">Документів</th>
                                <th class="py-2 text-right">Активний</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($templates as $template)
                                <tr class="border-b last:border-0">
                                    <td class="py-2 pr-4 text-gray-500">{{ $template->code }}</td>
                                    <td class="py-2 pr-4">{{ $template->name }}</td>
                                    <td class="py-2 pr-4">{{ $template->type }}</td>
                                    <td class="py-2 pr-4 text-gray-500">{{ implode(', ', $template->fields ?? []) }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $template->documents_count }}</td>
                                    <td class="py-2 text-right">{{ $template->is_active ? 'так' : 'ні' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $templates->links() }}</div>
            </x-card>
        </div>
    </div>
</x-app-layout>

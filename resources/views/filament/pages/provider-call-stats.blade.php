<x-filament-panels::page>
    <x-filament::section :heading="__('admin.provider_call_stats.quota_heading')" :description="__('admin.provider_call_stats.quota_description')">
        <dl class="grid gap-4 sm:grid-cols-3">
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.provider_call_stats.this_hour') }}</dt>
                <dd class="text-2xl font-semibold">{{ number_format($this->quota()->hour) }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.provider_call_stats.today') }}</dt>
                <dd class="text-2xl font-semibold">{{ number_format($this->quota()->day) }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.provider_call_stats.this_month') }}</dt>
                <dd class="text-2xl font-semibold">{{ number_format($this->quota()->month) }}</dd>
            </div>
        </dl>
    </x-filament::section>

    <x-filament::section :heading="__('admin.provider_call_stats.by_provider')" class="mt-6">
        @if ($this->summaries()->isEmpty())
            <p>{{ __('admin.provider_call_stats.empty') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-start text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-white/10">
                            <th class="py-2 pe-4 font-medium">{{ __('admin.provider_call_stats.provider') }}</th>
                            <th class="py-2 pe-4 font-medium">{{ __('admin.provider_call_stats.this_hour') }}</th>
                            <th class="py-2 pe-4 font-medium">{{ __('admin.provider_call_stats.today') }}</th>
                            <th class="py-2 font-medium">{{ __('admin.provider_call_stats.this_month') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->summaries() as $row)
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="py-2 pe-4">{{ $row->provider }}</td>
                                <td class="py-2 pe-4">{{ number_format($row->hour) }}</td>
                                <td class="py-2 pe-4">{{ number_format($row->day) }}</td>
                                <td class="py-2">{{ number_format($row->month) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>

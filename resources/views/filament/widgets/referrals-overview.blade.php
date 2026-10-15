<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-user-plus" icon-color="primary">
        <x-slot name="heading">Doctor referrals — last 30 days</x-slot>
        <x-slot name="description">Visits through /dr/&lt;code&gt; links. Orders and commission per doctor are in Salla's Affiliate marketing dashboard.</x-slot>

        <div class="grid gap-6 md:grid-cols-3">
            <div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Total visits</div>
                <div class="text-3xl font-semibold">{{ number_format($total) }}</div>
            </div>
            <div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Unique visits</div>
                <div class="text-3xl font-semibold">{{ number_format($unique) }}</div>
            </div>
            <div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Top partners</div>
                @if ($top === [])
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No referral visits yet.</p>
                @else
                    <ol class="mt-1 space-y-1 text-sm">
                        @foreach ($top as $row)
                            <li class="flex justify-between gap-2">
                                <a href="{{ $row['url'] }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $row['name'] }}</a>
                                <span class="text-gray-600 dark:text-gray-300">{{ number_format($row['visits']) }} / {{ number_format($row['unique']) }} unique</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

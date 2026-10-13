<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-building-storefront" icon-color="primary">
        <x-slot name="heading">Store, orders and stock are managed in Salla</x-slot>

        <p class="text-sm text-gray-600 dark:text-gray-300">
            This dashboard manages the website's content: products' editorial copy,
            categories, contact messages and newsletter subscribers. Prices, stock,
            orders, customers and shipping live on the Salla store at
            <a href="{{ $storeUrl }}" target="_blank" rel="noopener" class="font-semibold text-primary-600 underline dark:text-primary-400">{{ $storeHost }}</a>.
        </p>

        <div class="mt-4">
            <x-filament::button tag="a" :href="$manageUrl" target="_blank" rel="noopener" icon="heroicon-o-arrow-top-right-on-square" icon-position="after">
                Open Salla
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

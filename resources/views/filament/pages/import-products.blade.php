{{--
    Product import (§7.2). Built from Filament's own Blade components so it
    inherits the panel's colours and dark mode rather than styling its own.
    The two tables use minimal inline styles because the panel's compiled CSS
    only contains the utilities Filament itself uses.
--}}
@php
    $fields = $this->getFields();
    $missing = $this->getMissingRequired();
    $cell = 'padding:6px 10px;border-bottom:1px solid rgba(127,127,127,0.2);text-align:left;vertical-align:top;white-space:nowrap;';
@endphp

<x-filament-panels::page>

    <x-filament::section>
        <x-slot name="heading">1. Choose a file</x-slot>
        <x-slot name="description">
            A .csv or .xlsx file with a header row. Required columns: SKU, name, price, category.
            Lists (benefits, ingredients, free from, shop filters) are separated with a vertical bar: Hydrates|Plumps.
            Products are matched by SKU: existing ones are updated, new ones created.
        </x-slot>

        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <input type="file" wire:model="upload" accept=".csv,.xlsx" />
            <div wire:loading wire:target="upload"><x-filament::loading-indicator style="height:20px;width:20px;" /></div>
            <x-filament::link :href="$this->getProductsUrl()" icon="heroicon-m-arrow-left">Back to products</x-filament::link>
        </div>

        @error('upload')
            <p style="color:rgb(220,38,38);margin-top:8px;">{{ $message }}</p>
        @enderror

        @if ($originalName)
            <p style="margin-top:10px;">
                <strong>{{ $originalName }}</strong> — {{ $totalRows }} data row{{ $totalRows === 1 ? '' : 's' }}.
                @if ($totalRows > \App\Filament\Pages\ImportProducts::QUEUE_THRESHOLD)
                    More than {{ \App\Filament\Pages\ImportProducts::QUEUE_THRESHOLD }} rows, so it will import in the background.
                @endif
            </p>
        @endif
    </x-filament::section>

    @if ($headers !== [])
        <x-filament::section>
            <x-slot name="heading">2. Match columns</x-slot>
            <x-slot name="description">Guessed from the header names. Change any that are wrong; leave optional fields unmapped to keep their current values.</x-slot>

            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:12px;">
                @foreach ($fields as $field => [$label, $type, $required])
                    <label style="display:block;">
                        <span style="display:block;font-size:0.85rem;margin-bottom:4px;">
                            {{ $label }}@if ($required)<span style="color:rgb(220,38,38);"> *</span>@endif
                        </span>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="mapping.{{ $field }}">
                                <option value="">— not imported —</option>
                                @foreach ($headers as $index => $header)
                                    <option value="{{ $index }}">{{ $header !== '' ? $header : 'Column '.($index + 1) }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </label>
                @endforeach
            </div>

            @if ($missing !== [])
                <p style="color:rgb(220,38,38);margin-top:12px;">Still unmapped: {{ implode(', ', $missing) }}.</p>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">3. Preview</x-slot>
            <x-slot name="description">The first {{ count($previewRows) }} rows as they will be read with the columns above.</x-slot>

            <div style="overflow-x:auto;">
                <table style="border-collapse:collapse;font-size:0.85rem;width:100%;">
                    <thead>
                        <tr>
                            <th style="{{ $cell }}">Line</th>
                            @foreach ($fields as $field => [$label])
                                @if (($mapping[$field] ?? '') !== '' && ($mapping[$field] ?? null) !== null)
                                    <th style="{{ $cell }}">{{ $label }}</th>
                                @endif
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->getMappedPreview() as $line => $values)
                            <tr>
                                <td style="{{ $cell }}">{{ $line }}</td>
                                @foreach ($values as $value)
                                    <td style="{{ $cell }}">{{ $value }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">4. Import</x-slot>

            <label style="display:flex;gap:8px;align-items:center;margin-bottom:14px;">
                <x-filament::input.checkbox wire:model="skipInvalid" />
                <span>Skip invalid rows and import the rest. Unticked, one bad row stops the whole file and nothing changes.</span>
            </label>

            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <x-filament::button color="gray" wire:click="check" icon="heroicon-m-magnifying-glass">Check file</x-filament::button>
                <x-filament::button wire:click="runImport" icon="heroicon-m-arrow-up-tray">Import</x-filament::button>
                <x-filament::button color="gray" outlined wire:click="startOver">Start over</x-filament::button>
            </div>

            @if ($validCount !== null)
                <p style="margin-top:14px;">
                    {{ $validCount }} row{{ $validCount === 1 ? '' : 's' }} ready,
                    <span @if ($errorCount > 0) style="color:rgb(220,38,38);" @endif>{{ $errorCount }} with errors</span>.
                </p>
            @endif

            @if ($errorLines !== [])
                <ul style="margin-top:8px;padding-left:18px;list-style:disc;color:rgb(220,38,38);font-size:0.85rem;">
                    @foreach ($errorLines as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                    @if ($errorCount > count($errorLines))
                        <li>…and {{ $errorCount - count($errorLines) }} more.</li>
                    @endif
                </ul>
            @endif
        </x-filament::section>
    @endif

</x-filament-panels::page>

{{--
    The settings page shell. Matches the shape Filament's own form-bearing
    pages use (see filament-panels::pages.auth.edit-profile): the form is
    submitted by wire:submit to the page's save() method, and the actions come
    from getCachedFormActions() so the button reflects the page's own
    definitions rather than being hard-coded here.
--}}
<x-filament-panels::page>
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
        />
    </x-filament-panels::form>
</x-filament-panels::page>

<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Admin;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns;
use Filament\Pages\Page;

/**
 * Store settings (§7.6), super admin only.
 *
 * Every field here is read at runtime through Setting::get(key, config(...)),
 * so a change takes effect on the next request without a deploy. Writes go
 * through Setting::put(), which flushes the cached settings map — the model
 * also flushes on save and delete, so a value can never be stale.
 *
 * VAT is entered as a percentage and stored as a rate. An admin typing "15"
 * into a field that stores 0.15 would otherwise set VAT to 1500% and misstate
 * the tax on every order placed afterwards.
 *
 * @property Form $form
 */
final class ManageSettings extends Page
{
    use Concerns\InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 99;

    protected static ?string $title = 'Store settings';

    protected static ?string $navigationLabel = 'Settings';

    protected static string $view = 'filament.pages.manage-settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * Settings are the one thing that can quietly break the whole shop, so
     * this is super-admin only. canAccess() is what actually refuses the
     * route — Filament checks it before registering navigation and aborts 403
     * on both mount and hydrate.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof Admin && $user->canManageSettings();
    }

    public function mount(): void
    {
        $this->form->fill([
            'store_email' => Setting::get('store_email', config('kotiva.mail.store_email')),
            'admin_notification_emails' => (array) Setting::get(
                'admin_notification_emails',
                config('kotiva.mail.admin_notification_emails')
            ),
            'low_stock_alert_email' => Setting::get('low_stock_alert_email', config('kotiva.mail.store_email')),
            'cod_enabled' => (bool) Setting::get('cod_enabled', true),
            'cart_ttl_hours' => (int) Setting::get('cart_ttl_hours', config('kotiva.cart.ttl_hours')),
            'low_stock_threshold' => (int) Setting::get(
                'low_stock_threshold',
                config('kotiva.stock.low_stock_threshold')
            ),
            // Stored as a rate, shown as a percentage.
            'vat_rate_percent' => round(((float) Setting::get('vat_rate', config('kotiva.vat_rate'))) * 100, 4),
            'announcement_enabled' => (bool) ($this->announcement()['enabled'] ?? false),
            'announcement_text' => (string) ($this->announcement()['text'] ?? ''),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function announcement(): array
    {
        $value = Setting::get('announcement_bar', ['enabled' => false, 'text' => '']);

        return is_array($value) ? $value : ['enabled' => false, 'text' => ''];
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Contact')
                    ->description('Where the shop writes from, and who hears about it.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('store_email')
                            ->label('Store contact email')
                            ->email()
                            ->required()
                            ->helperText('Used as the reply-to on customer email, and shown on the contact page.'),

                        Forms\Components\TextInput::make('low_stock_alert_email')
                            ->label('Low-stock alert email')
                            ->email()
                            ->required()
                            ->helperText('Where low-stock warnings are sent.'),

                        Forms\Components\TagsInput::make('admin_notification_emails')
                            ->label('Order notification emails')
                            ->placeholder('Add an address')
                            ->columnSpanFull()
                            ->nestedRecursiveRules(['email'])
                            ->helperText('Everyone here is emailed when an order is placed. Press Enter after each address.'),
                    ]),

                Forms\Components\Section::make('Checkout')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Toggle::make('cod_enabled')
                            ->label('Cash on delivery')
                            ->helperText('The only payment method. Turning it off leaves checkout with nothing to offer.'),

                        Forms\Components\TextInput::make('cart_ttl_hours')
                            ->label('Cart lifetime')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(720)
                            ->required()
                            ->suffix('hours')
                            ->helperText('After this, an abandoned cart is purged and its reserved stock returned.'),

                        Forms\Components\TextInput::make('vat_rate_percent')
                            ->label('VAT rate')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->required()
                            ->suffix('%')
                            ->helperText('Prices are VAT-inclusive; this is the portion extracted for display on orders, never added to the price.'),
                    ]),

                Forms\Components\Section::make('Catalog')
                    ->schema([
                        Forms\Components\TextInput::make('low_stock_threshold')
                            ->label('Default low-stock threshold')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->helperText('The starting threshold for a new product. Existing products keep their own value.'),
                    ]),

                Forms\Components\Section::make('Announcement bar')
                    ->description('Shown above the navigation on every storefront page.')
                    ->schema([
                        Forms\Components\Toggle::make('announcement_enabled')
                            ->label('Show the announcement bar')
                            ->live(),

                        Forms\Components\TextInput::make('announcement_text')
                            ->label('Message')
                            ->maxLength(160)
                            ->required(fn (Forms\Get $get): bool => (bool) $get('announcement_enabled'))
                            ->helperText('Kept short: it sits above the navigation on every page.'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Addresses that are not addresses would silently stop order mail, so
        // they are dropped rather than stored.
        $notificationEmails = array_values(array_filter(
            array_map('trim', (array) ($data['admin_notification_emails'] ?? [])),
            fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        ));

        Setting::put('store_email', $data['store_email']);
        Setting::put('low_stock_alert_email', $data['low_stock_alert_email']);
        Setting::put('admin_notification_emails', $notificationEmails);
        Setting::put('cod_enabled', (bool) $data['cod_enabled']);
        Setting::put('cart_ttl_hours', (int) $data['cart_ttl_hours']);
        Setting::put('low_stock_threshold', (int) $data['low_stock_threshold']);
        // Percentage back to a rate.
        Setting::put('vat_rate', round(((float) $data['vat_rate_percent']) / 100, 6));
        Setting::put('announcement_bar', [
            'enabled' => (bool) $data['announcement_enabled'],
            'text' => trim((string) ($data['announcement_text'] ?? '')),
        ]);

        Notification::make()
            ->success()
            ->title('Settings saved')
            ->body('Changes take effect immediately.')
            ->send();
    }

    /**
     * @return array<int, Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save settings')
                ->submit('save'),
        ];
    }
}

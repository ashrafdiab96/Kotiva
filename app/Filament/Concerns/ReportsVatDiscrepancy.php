<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Models\Product;
use App\Support\Money;
use App\Support\Vat;
use Filament\Notifications\Notification;

/**
 * Tells an admin when a product's two prices do not agree at the configured
 * VAT rate — and changes neither of them.
 *
 * The distinction matters. Both prices come from a signed-off brochure, so the
 * application has no standing to decide which of two client-supplied figures is
 * the mistaken one. Correcting the inclusive price to excl × 1.15 would be a
 * silent repricing; refusing the save would make a brochure that deliberately
 * rounds to a round number unenterable. Reporting it is the only response that
 * respects both.
 *
 * The notification is persistent rather than a toast, because the admin who
 * saved the row is usually not the person who decides which price is right.
 */
trait ReportsVatDiscrepancy
{
    protected function reportVatDiscrepancy(): void
    {
        $product = $this->record;

        if (! $product instanceof Product || $product->pricesAgreeWithVatRate()) {
            return;
        }

        $currency = (string) config('kotiva.currency.code');
        $delta = $product->vatDiscrepancy();
        $expected = Vat::inclusiveOf($product->priceExclVat());

        Notification::make()
            ->warning()
            ->persistent()
            ->title('Saved — but the two prices disagree')
            ->body(sprintf(
                '%s excluding VAT implies %s at %s VAT, and %s is saved. '
                .'The difference is %s. Both figures have been kept exactly as entered; '
                .'change one only if the brochure says so.',
                Money::format($product->priceExclVat(), $currency),
                Money::format($expected, $currency),
                Vat::rateLabel(),
                Money::format($product->priceInclVat(), $currency),
                Money::format(ltrim($delta, '-'), $currency),
            ))
            ->send();
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A contact form enquiry.
 *
 * Stored before the notification email is attempted, so a mail failure loses a
 * notification rather than a customer's message.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $enquiry_type
 * @property string|null $message
 * @property bool $is_handled
 * @property CarbonInterface|null $handled_at
 */
final class ContactMessage extends Model
{
    protected $fillable = [
        'name',
        'email',
        'enquiry_type',
        'message',
        'is_handled',
        'handled_at',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'is_handled' => 'boolean',
            'handled_at' => 'datetime',
        ];
    }

    /** @param Builder<ContactMessage> $query */
    public function scopeUnhandled(Builder $query): void
    {
        $query->where('is_handled', false);
    }

    public function markHandled(): void
    {
        $this->forceFill(['is_handled' => true, 'handled_at' => now()])->save();
    }

    /**
     * The enquiry types offered on the contact form, kept here so the form and
     * the dashboard filter cannot drift apart.
     *
     * @return array<string, string>
     */
    public static function enquiryTypes(): array
    {
        return [
            'stockist' => 'Find a Stockist',
            'waitlist' => 'Online Waitlist',
            'wholesale' => 'Wholesale / Retail Partnership',
            'product' => 'Product Question',
            'media' => 'Media / Press',
            'other' => 'Other',
        ];
    }

    public function enquiryLabel(): string
    {
        return self::enquiryTypes()[$this->enquiry_type] ?? 'General';
    }
}

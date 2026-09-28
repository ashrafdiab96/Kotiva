<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The validated delivery details a shopper entered.
 *
 * A readonly object rather than the raw request array, so CheckoutService
 * cannot read a key that a future form forgets to send, and so the phone is
 * guaranteed to have been normalised exactly once on the way in.
 */
final readonly class CheckoutDetails
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        /** Normalised to +9665XXXXXXXX. */
        public string $phone,
        public int $zoneId,
        public int $cityId,
        public string $cityName,
        public string $addressLine1,
        public ?string $addressLine2,
        public ?string $district,
        public ?string $postalCode,
        public ?string $note,
        public bool $marketingOptIn,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Already validated.
     */
    public static function fromValidated(array $data, string $cityName): self
    {
        return new self(
            firstName: trim((string) $data['first_name']),
            lastName: trim((string) $data['last_name']),
            email: mb_strtolower(trim((string) $data['email'])),
            phone: (string) $data['phone'],
            zoneId: (int) $data['zone_id'],
            cityId: (int) $data['city_id'],
            cityName: $cityName,
            addressLine1: trim((string) $data['address_line1']),
            addressLine2: self::nullableTrim($data['address_line2'] ?? null),
            district: self::nullableTrim($data['district'] ?? null),
            postalCode: self::nullableTrim($data['postal_code'] ?? null),
            note: self::nullableTrim($data['note'] ?? null),
            marketingOptIn: (bool) ($data['marketing_opt_in'] ?? false),
        );
    }

    public function fullName(): string
    {
        return trim($this->firstName.' '.$this->lastName);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSession(): array
    {
        return [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'zone_id' => $this->zoneId,
            'city_id' => $this->cityId,
            'city_name' => $this->cityName,
            'address_line1' => $this->addressLine1,
            'address_line2' => $this->addressLine2,
            'district' => $this->district,
            'postal_code' => $this->postalCode,
            'note' => $this->note,
            'marketing_opt_in' => $this->marketingOptIn,
        ];
    }

    /**
     * @param  array<string, mixed>  $session
     */
    public static function fromSession(array $session): self
    {
        return new self(
            firstName: (string) $session['first_name'],
            lastName: (string) $session['last_name'],
            email: (string) $session['email'],
            phone: (string) $session['phone'],
            zoneId: (int) $session['zone_id'],
            cityId: (int) $session['city_id'],
            cityName: (string) $session['city_name'],
            addressLine1: (string) $session['address_line1'],
            addressLine2: self::nullableTrim($session['address_line2'] ?? null),
            district: self::nullableTrim($session['district'] ?? null),
            postalCode: self::nullableTrim($session['postal_code'] ?? null),
            note: self::nullableTrim($session['note'] ?? null),
            marketingOptIn: (bool) ($session['marketing_opt_in'] ?? false),
        );
    }

    private static function nullableTrim(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}

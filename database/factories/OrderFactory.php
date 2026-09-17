<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Support\OrderNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
final class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_no' => OrderNumber::generate(fn (): bool => false),
            'customer_id' => Customer::factory(),
            'status' => OrderStatus::Pending,
            'payment_method' => PaymentMethod::CashOnDelivery,
            'payment_status' => PaymentStatus::Unpaid,
            'currency' => 'SAR',
            'subtotal' => '300.00',
            'shipping_fee' => '25.00',
            'discount_total' => '0.00',
            'vat_amount' => '42.39',
            'grand_total' => '325.00',
            'shipping_zone_id' => null,
            'shipping_city_id' => null,
            'shipping_name' => $this->faker->name(),
            'shipping_phone' => '+9665'.$this->faker->numerify('########'),
            'shipping_address_line1' => $this->faker->streetAddress(),
            'shipping_address_line2' => null,
            'shipping_district' => 'Al Olaya',
            'shipping_city_name' => 'Riyadh',
            'shipping_postal_code' => null,
            'customer_note' => null,
            'admin_note' => null,
            'placed_at' => now(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ];
    }

    public function status(OrderStatus $status): self
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    public function paid(): self
    {
        return $this->state(fn (): array => ['payment_status' => PaymentStatus::Paid]);
    }
}

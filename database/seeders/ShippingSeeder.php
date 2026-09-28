<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ShippingCity;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use Illuminate\Database\Seeder;

/**
 * Three KSA delivery zones with realistic cities and fees (§4).
 *
 * Idempotent throughout: re-running must not duplicate a city or stamp over a
 * fee the client has since changed from the dashboard.
 */
final class ShippingSeeder extends Seeder
{
    /**
     * @var list<array{
     *     name: string, sort_order: int, fee: string, threshold: string|null,
     *     days: array{int, int}, cities: list<array{string, string}>
     * }>
     */
    private const ZONES = [
        [
            'name' => 'Riyadh',
            'sort_order' => 1,
            'fee' => '25.00',
            'threshold' => '300.00',
            'days' => [1, 2],
            'cities' => [
                ['Riyadh', 'الرياض'],
            ],
        ],
        [
            'name' => 'Eastern Province',
            'sort_order' => 2,
            'fee' => '30.00',
            'threshold' => '300.00',
            'days' => [2, 3],
            'cities' => [
                ['Dammam', 'الدمام'],
                ['Khobar', 'الخبر'],
                ['Jubail', 'الجبيل'],
                ['Al Ahsa', 'الأحساء'],
            ],
        ],
        [
            'name' => 'Rest of KSA',
            'sort_order' => 3,
            'fee' => '40.00',
            'threshold' => '300.00',
            'days' => [3, 5],
            'cities' => [
                ['Jeddah', 'جدة'],
                ['Makkah', 'مكة المكرمة'],
                ['Madinah', 'المدينة المنورة'],
                ['Taif', 'الطائف'],
                ['Abha', 'أبها'],
                ['Tabuk', 'تبوك'],
                ['Buraidah', 'بريدة'],
                ['Hail', 'حائل'],
                ['Jizan', 'جيزان'],
                ['Najran', 'نجران'],
                ['Yanbu', 'ينبع'],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::ZONES as $definition) {
            $zone = ShippingZone::firstOrCreate(
                ['name' => $definition['name']],
                ['is_active' => true, 'sort_order' => $definition['sort_order']]
            );

            // firstOrCreate, not updateOrCreate: a fee the client has adjusted
            // must survive a re-seed.
            ShippingRate::firstOrCreate(
                ['zone_id' => $zone->id],
                [
                    'fee' => $definition['fee'],
                    'free_shipping_threshold' => $definition['threshold'],
                    'estimated_days_min' => $definition['days'][0],
                    'estimated_days_max' => $definition['days'][1],
                    'is_active' => true,
                ]
            );

            foreach ($definition['cities'] as [$nameEn, $nameAr]) {
                ShippingCity::firstOrCreate(
                    ['zone_id' => $zone->id, 'name_en' => $nameEn],
                    ['name_ar' => $nameAr, 'is_active' => true]
                );
            }
        }
    }
}

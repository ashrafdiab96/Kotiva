<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Products keep only the path of their Salla page ("/micellar-water/p1713901761").
 *
 * The store origin lives in .env (KOTIVA_SALLA_STORE_URL) and is joined to the
 * path in App\Support\Salla::productUrl() alone, so moving the store no longer
 * means rewriting every product row.
 *
 * Existing full URLs on the configured store are reduced to their path. A URL
 * on any other origin is cleared rather than guessed at; `kotiva:salla-links
 * --check` then reports that product as unlinked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->renameColumn('salla_url', 'salla_path');
        });

        $store = parse_url((string) config('kotiva.salla.store_url'));
        $storeHost = strtolower((string) ($store['host'] ?? ''));
        $storePrefix = rtrim((string) ($store['path'] ?? ''), '/');

        DB::table('products')->whereNotNull('salla_path')->orderBy('id')->each(function (object $row) use ($storeHost, $storePrefix): void {
            $value = (string) $row->salla_path;

            if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
                return;
            }

            $url = parse_url($value);
            $path = (string) ($url['path'] ?? '');
            $onStore = $storeHost !== ''
                && strtolower((string) ($url['host'] ?? '')) === $storeHost
                && ($storePrefix === '' || str_starts_with($path, $storePrefix.'/'));

            DB::table('products')->where('id', $row->id)->update([
                'salla_path' => $onStore && $path !== '' ? substr($path, strlen($storePrefix)) : null,
            ]);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('salla_path', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('salla_path', 500)->nullable()->change();
        });

        $store = rtrim((string) config('kotiva.salla.store_url'), '/');

        DB::table('products')->where('salla_path', 'like', '/%')->orderBy('id')->each(function (object $row) use ($store): void {
            DB::table('products')->where('id', $row->id)->update(['salla_path' => $store.$row->salla_path]);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->renameColumn('salla_path', 'salla_url');
        });
    }
};

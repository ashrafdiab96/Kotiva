<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            // Unique so re-subscribing is idempotent rather than duplicating a
            // person on the list.
            $table->string('email')->unique();

            $table->timestamp('subscribed_at')->nullable();
            // Kept rather than deleted on unsubscribe: re-adding someone who
            // has opted out is the one mistake a mailing list must not make.
            $table->timestamp('unsubscribed_at')->nullable();

            $table->string('source')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('unsubscribed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};

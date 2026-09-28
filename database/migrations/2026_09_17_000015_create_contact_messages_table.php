<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contact form submissions.
 *
 * The static site posted these to an n8n webhook run by the previous agency,
 * which the handover README flagged as outside the codebase and not guaranteed
 * to keep running (§3). Storing them here means an enquiry survives even if
 * the notification email fails.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            // Matches the enquiry types on the contact form's select.
            $table->string('enquiry_type')->nullable();
            $table->text('message')->nullable();

            $table->boolean('is_handled')->default(false);
            $table->timestamp('handled_at')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['is_handled', 'created_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};

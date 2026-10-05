<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('status');
            $table->string('billing_interval');
            $table->string('product_name');
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('shipping_fee')->default(0);
            $table->boolean('requires_shipping')->default(false);
            $table->string('customer_name');
            $table->string('email')->index();
            $table->string('phone');
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('postcode', 10)->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->date('next_billing_date')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('past_due_since')->nullable();
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_billing_date']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('subscription_id')->nullable()->after('coupon_id')->constrained()->nullOnDelete();
            $table->boolean('is_renewal')->default(false)->after('subscription_id');
            $table->date('period_start')->nullable()->after('is_renewal');

            // One renewal order per billing period, even if the renewal command runs twice.
            $table->unique(['subscription_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['subscription_id', 'period_start']);
            $table->dropConstrainedForeignId('subscription_id');
            $table->dropColumn(['is_renewal', 'period_start']);
        });

        Schema::dropIfExists('subscriptions');
    }
};

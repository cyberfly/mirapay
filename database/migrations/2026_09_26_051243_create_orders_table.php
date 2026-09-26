<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('order_number')->unique();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status');
            $table->string('product_name');
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('discount')->default(0);
            $table->unsignedInteger('shipping_fee')->default(0);
            $table->unsignedInteger('total');
            $table->string('coupon_code')->nullable();
            $table->boolean('requires_shipping')->default(false);
            $table->string('customer_name');
            $table->string('email');
            $table->string('phone');
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('postcode', 10)->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('payment_gateway')->default('manual');
            $table->string('payment_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

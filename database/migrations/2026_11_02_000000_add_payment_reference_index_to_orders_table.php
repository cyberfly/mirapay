<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CHIP callbacks look orders up by their purchase id.
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['payment_gateway', 'payment_reference']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_gateway', 'payment_reference']);
        });
    }
};

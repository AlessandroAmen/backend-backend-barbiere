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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('customer')->after('email'); // customer, barber, manager, admin
            $table->foreignId('barber_shop_id')->nullable()->after('role')->constrained('barbers')->onDelete('set null');
            $table->foreignId('manager_id')->nullable()->after('barber_shop_id')->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['barber_shop_id']);
            $table->dropForeign(['manager_id']);
            $table->dropColumn(['role', 'barber_shop_id', 'manager_id']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Pastikan field ini ada
            if (!Schema::hasColumn('invoices', 'status')) {
                $table->string('status')->default('Belum Lunas')->after('total_amount');
            }
            if (!Schema::hasColumn('invoices', 'total_received')) {
                $table->decimal('total_received', 15, 2)->default(0)->after('status');
            }
            if (!Schema::hasColumn('invoices', 'remaining_amount')) {
                $table->decimal('remaining_amount', 15, 2)->default(0)->after('total_received');
            }
            
            // Index untuk performa
            $table->index('status');
            $table->index('remaining_amount');
            $table->index('invoice_date');
        });
    }

    public function down()
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['status', 'total_received', 'remaining_amount']);
        });
    }
};
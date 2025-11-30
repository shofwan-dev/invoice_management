<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('payment_steps', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->after('payment_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payment_steps');
        Schema::table('payment_steps', function (Blueprint $table) {
            $table->dropColumn('bank_name');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_productions', function (Blueprint $table) {
            $table->string('customer', 255)->nullable()->after('part_number');
            $table->string('size_part', 255)->nullable()->after('customer');
            $table->string('pic_shift1', 100)->nullable()->after('notes');
            $table->string('pic_shift2', 100)->nullable()->after('pic_shift1');
            $table->json('part_options')->nullable()->after('pic_shift2');
        });

        Schema::table('production_records', function (Blueprint $table) {
            $table->unsignedInteger('counter_start')->nullable()->after('end_time');
            $table->unsignedInteger('counter_end')->nullable()->after('counter_start');
            $table->decimal('rpm', 10, 2)->nullable()->after('counter_end');
        });
    }

    public function down(): void
    {
        Schema::table('production_records', function (Blueprint $table) {
            $table->dropColumn(['counter_start', 'counter_end', 'rpm']);
        });

        Schema::table('master_productions', function (Blueprint $table) {
            $table->dropColumn(['customer', 'size_part', 'pic_shift1', 'pic_shift2', 'part_options']);
        });
    }
};

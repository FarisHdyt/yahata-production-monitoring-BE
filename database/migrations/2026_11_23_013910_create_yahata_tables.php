<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::create('master_productions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('part_number', 100)->nullable();
            $table->decimal('rpm', 10, 2)->nullable();
            $table->decimal('pcs_per_revolution', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('production_records', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedTinyInteger('shift');
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->string('part_number', 100);
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('output');
            $table->string('pic', 100);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['date', 'machine_id']);
            $table->index(['date', 'shift']);
        });

        Schema::create('downtime_categories', function (Blueprint $table) {
            $table->unsignedTinyInteger('code')->primary();
            $table->string('name', 100)->unique();
            $table->boolean('is_downtime')->default(true);
            $table->timestamps();
        });

        Schema::create('downtime_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('category_code');
            $table->foreign('category_code')->references('code')->on('downtime_categories');
            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();
            $table->unsignedTinyInteger('shift')->nullable(); // null = di luar shift
            $table->string('pic', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['machine_id', 'start_at']);
            $table->index('start_at');
        });

        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedTinyInteger('shift');
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->string('pic', 100);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['date', 'shift', 'machine_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_assignments');
        Schema::dropIfExists('downtime_events');
        Schema::dropIfExists('downtime_categories');
        Schema::dropIfExists('production_records');
        Schema::dropIfExists('master_productions');
        Schema::dropIfExists('machines');
    }
};
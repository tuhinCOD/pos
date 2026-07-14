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
        // Schema::create('clients', function (Blueprint $table) {
        //     $table->id();
        //     $table->string('name', length: 100)->nullable(true);
        //     $table->string('contact', length: 14);
        //     $table->string('email', length: 100)->unique()->nullable(true);
        //     $table->string('password');
        //     $table->string('address', length: 500)->nullable(true);
        //     $table->foreignId('city_id')
        //     ->nullable(true)
        //     ->constrained('cities');
        //     $table->foreignId('status_id')
        //     ->constrained('statuses');
        //     $table->foreignId('user_id')
        //     ->nullable()
        //     ->constrained('users');
        //     $table->decimal('point', '8', '3');
        //     $table->timestamps();
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::dropIfExists('clients');
    }
};

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
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name', length: 150);
            $table->string('contact', length: 14)->unique();
            $table->string('address', length: 500);
            $table->foreignId('city_id')
            ->constrained('cities');
            $table->foreignId('user_id')
            ->constrained('users');
            $table->foreignId('updated_by')
            ->nullable()
            ->constrained('users');
            $table->timestamps();
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->index('name');
            $table->index('contact');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};

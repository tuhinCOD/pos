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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', length: 150);
            $table->string('contact', length: 14)->unique()->nullable(true);
            $table->string('email', length: 100)->unique()->nullable(true);
            $table->string('website', length: 100)->unique()->nullable(true);
            $table->string('address', length: 500)->nullable(true);
            $table->string('logo')->nullable(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};

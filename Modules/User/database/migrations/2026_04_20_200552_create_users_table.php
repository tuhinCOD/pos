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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', length: 100)->nullable(true);
            $table->string('contact', length: 14);
            $table->string('email', length: 100)->unique()->nullable(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable(true);
            $table->string('address', length: 500)->nullable(true);
            $table->foreignId('city_id')
            ->nullable(true)
            ->constrained('cities');
            $table->string('nid', length: 25)->nullable(true);
            $table->decimal('point', 10, 2)->default(0);
            $table->foreignId('status_id')
            ->nullable(true)
            ->constrained('statuses');
            $table->foreignId('role_id')
            ->constrained('roles');
            $table->foreignId('user_id')
            ->nullable(true)
            ->constrained('users');
            $table->foreignId('updated_by')
            ->nullable()
            ->constrained('users');
            $table->rememberToken()->nullable(true);
            $table->string('remarks', length: 500)->nullable(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('name');
            $table->index('contact');
            $table->index('email');
            $table->index('nid');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};

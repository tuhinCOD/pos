<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_returns', function (Blueprint $table) {
            $table->foreignId('product_unit_id')->nullable()->after('product_id')->constrained('units')->nullOnDelete();
            $table->decimal('product_unit_qty', 10, 2)->nullable()->after('product_unit_id');
        });

        DB::statement('UPDATE supplier_returns SET product_unit_id = unit_id, product_unit_qty = qty');
    }

    public function down(): void
    {
        Schema::table('supplier_returns', function (Blueprint $table) {
            $table->dropForeign(['product_unit_id']);
            $table->dropColumn('product_unit_id');
            $table->dropColumn('product_unit_qty');
        });
    }
};

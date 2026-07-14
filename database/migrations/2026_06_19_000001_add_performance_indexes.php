<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->index(['product_id', 'branch_id']);
            $table->index(['level_specific_id', 'level_specific_type']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('payment_invoice_no');
            $table->index('payment_type');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->index('product_id');
            $table->index('branch_id');
            $table->index('status_id');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->index('product_id');
            $table->index('branch_id');
            $table->index('client_id');
            $table->index('product_price_id');
        });

        Schema::table('credits', function (Blueprint $table) {
            $table->index('invoice_no');
            $table->index('credit_type');
            $table->index(['invoice_no', 'credit_type']);
        });
    }

    public function down(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            $table->dropIndex(['invoice_no', 'credit_type']);
            $table->dropIndex('credits_credit_type_index');
            $table->dropIndex('credits_invoice_no_index');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_product_price_id_index');
            $table->dropIndex('sales_client_id_index');
            $table->dropIndex('sales_branch_id_index');
            $table->dropIndex('sales_product_id_index');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex('purchases_status_id_index');
            $table->dropIndex('purchases_branch_id_index');
            $table->dropIndex('purchases_product_id_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_payment_type_index');
            $table->dropIndex('payments_payment_invoice_no_index');
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->dropIndex('stocks_level_specific_id_level_specific_type_index');
            $table->dropIndex('stocks_product_id_branch_id_index');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barcodes', function (Blueprint $table) {
            $table->index('product_id');
            $table->index('purchase_id');
            $table->index('branch_id');
            $table->index('status_id');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->index('client_id');
            $table->index('product_id');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index('parent_id');
        });

        Schema::table('client_returns', function (Blueprint $table) {
            $table->index('product_id');
            $table->index('sale_id');
            $table->index('status_id');
            $table->index('branch_id');
        });

        Schema::table('damages', function (Blueprint $table) {
            $table->index('product_id');
            $table->index('status_id');
            $table->index('branch_id');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->index('order_id');
            $table->index('status_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('invoice_no');
            $table->index('status_id');
            $table->index('product_id');
            $table->index('client_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('status_id');
            $table->index('payment_method_id');
        });

        Schema::table('product_prices', function (Blueprint $table) {
            $table->index('product_id');
        });

        Schema::table('product_reviews', function (Blueprint $table) {
            $table->index('product_id');
            $table->index('client_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('category_id');
            $table->index('status_id');
        });

        Schema::table('repairs', function (Blueprint $table) {
            $table->index('damage_id');
            $table->index('status_id');
            $table->index('product_id');
            $table->index('branch_id');
        });

        Schema::table('statuses', function (Blueprint $table) {
            $table->index('parent_id');
        });

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->index('branch_from');
            $table->index('branch_to');
            $table->index('status_id');
            $table->index('sale_id');
            $table->index('product_id');
        });

        Schema::table('supplier_returns', function (Blueprint $table) {
            $table->index('product_id');
            $table->index('purchase_id');
            $table->index('status_id');
            $table->index('branch_id');
        });

        Schema::table('temps', function (Blueprint $table) {
            $table->index('status_id');
            $table->index('branch_id');
            $table->index('product_id');
            $table->index('client_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('status_id');
            $table->index('role_id');
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_branch_id_index');
            $table->dropIndex('users_role_id_index');
            $table->dropIndex('users_status_id_index');
        });

        Schema::table('temps', function (Blueprint $table) {
            $table->dropIndex('temps_client_id_index');
            $table->dropIndex('temps_product_id_index');
            $table->dropIndex('temps_branch_id_index');
            $table->dropIndex('temps_status_id_index');
        });

        Schema::table('supplier_returns', function (Blueprint $table) {
            $table->dropIndex('supplier_returns_branch_id_index');
            $table->dropIndex('supplier_returns_status_id_index');
            $table->dropIndex('supplier_returns_purchase_id_index');
            $table->dropIndex('supplier_returns_product_id_index');
        });

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropIndex('stock_transfers_product_id_index');
            $table->dropIndex('stock_transfers_sale_id_index');
            $table->dropIndex('stock_transfers_status_id_index');
            $table->dropIndex('stock_transfers_branch_to_index');
            $table->dropIndex('stock_transfers_branch_from_index');
        });

        Schema::table('statuses', function (Blueprint $table) {
            $table->dropIndex('statuses_parent_id_index');
        });

        Schema::table('repairs', function (Blueprint $table) {
            $table->dropIndex('repairs_branch_id_index');
            $table->dropIndex('repairs_product_id_index');
            $table->dropIndex('repairs_status_id_index');
            $table->dropIndex('repairs_damage_id_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_status_id_index');
            $table->dropIndex('products_category_id_index');
        });

        Schema::table('product_reviews', function (Blueprint $table) {
            $table->dropIndex('product_reviews_client_id_index');
            $table->dropIndex('product_reviews_product_id_index');
        });

        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropIndex('product_prices_product_id_index');
        });

        Schema::table('product_discounts', function (Blueprint $table) {
            $table->dropIndex('product_discounts_branch_id_index');
            $table->dropIndex('product_discounts_status_id_index');
            $table->dropIndex('product_discounts_product_id_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_payment_method_id_index');
            $table->dropIndex('payments_status_id_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_client_id_index');
            $table->dropIndex('orders_product_id_index');
            $table->dropIndex('orders_status_id_index');
            $table->dropIndex('orders_invoice_no_index');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropIndex('deliveries_status_id_index');
            $table->dropIndex('deliveries_order_id_index');
        });

        Schema::table('damages', function (Blueprint $table) {
            $table->dropIndex('damages_branch_id_index');
            $table->dropIndex('damages_status_id_index');
            $table->dropIndex('damages_product_id_index');
        });

        Schema::table('client_returns', function (Blueprint $table) {
            $table->dropIndex('client_returns_branch_id_index');
            $table->dropIndex('client_returns_status_id_index');
            $table->dropIndex('client_returns_sale_id_index');
            $table->dropIndex('client_returns_product_id_index');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('categories_parent_id_index');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropIndex('carts_product_id_index');
            $table->dropIndex('carts_client_id_index');
        });

        Schema::table('barcodes', function (Blueprint $table) {
            $table->dropIndex('barcodes_status_id_index');
            $table->dropIndex('barcodes_branch_id_index');
            $table->dropIndex('barcodes_purchase_id_index');
            $table->dropIndex('barcodes_product_id_index');
        });
    }
};

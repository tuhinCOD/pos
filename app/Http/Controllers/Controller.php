<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Modules\Product\Models\Product;
use Modules\Status\Models\Status;
use Modules\Stock\Models\Stock;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: "Laravel API",
    version: "1.0.0",
    contact: new OA\Contact(email: "you@example.com"),
    license: new OA\License(name: "MIT")
)]
#[OA\Server(
    url: "http://localhost:8000/api",
    description: "Local server"
)]

abstract class Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected function getStockQty(int $productId, int $branchId, ?array $attributes = null): float
    {
        $query = Stock::where('product_id', $productId)
            ->where('branch_id', $branchId);

        if (is_array($attributes)) {
            if (empty($attributes)) {
                $query->whereNull('attributes');
            } else {
                foreach ($attributes as $k => $v) {
                    if ($k === '' || $k === null) {
                        continue;
                    }
                    $query->where("attributes->{$k}", $v);
                }
            }
        }

        $latestStock = $query->latest('id')->first();
        return $latestStock ? (float)$latestStock->stock_qty : 0;
    }

    protected function updateProductStockStatus(int $productId): void
    {
        $productStatus = Status::where('name', 'product')->first();
        if (!$productStatus) return;

        $activeStatus = Status::where('name', 'active')
            ->where('parent_id', $productStatus->id)->first();
        $outOfStockStatus = Status::where('name', 'out of stock')
            ->where('parent_id', $productStatus->id)->first();

        $product = Product::find($productId);
        if (!$product) return;

        $stockRecords = Stock::where('product_id', $productId)
            ->orderBy('id', 'desc')
            ->get();
        $latestPerCombo = [];
        foreach ($stockRecords as $s) {
            $key = $s->attributes ? json_encode($s->attributes, JSON_UNESCAPED_UNICODE) : '__no_attrs__';
            if (!isset($latestPerCombo[$key])) {
                $latestPerCombo[$key] = (float)$s->stock_qty;
            }
        }

        $hasStock = false;
        foreach ($latestPerCombo as $qty) {
            if ($qty > 0) {
                $hasStock = true;
                break;
            }
        }

        if (!$hasStock && $outOfStockStatus) {
            $product->status_id = $outOfStockStatus->id;
            $product->save();
        } elseif ($hasStock && $activeStatus) {
            $product->status_id = $activeStatus->id;
            $product->save();
        }
    }
}

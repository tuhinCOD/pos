<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Product\Models\Product;
use Modules\ProductPrice\Models\ProductPrice;
use Modules\Category\Models\Category;
use Modules\ProductReview\Models\ProductReview;
use Modules\Coupon\Models\Coupon;
use Modules\Order\Models\Order;
use Modules\Order\Events\OrderUpdate;
use Modules\Cart\Models\Cart;
use Modules\Status\Models\Status;
use Modules\Unit\Models\Unit;

use Modules\Wishlist\Models\Wishlist;
use Modules\User\Models\User;
use Modules\City\Models\City;
use Modules\Company\Models\Company;
use Modules\Stock\Models\Stock;

class StorefrontController extends Controller
{
    public function companyInfo()
    {
        $company = Company::first();
        return response()->json([
            'status' => 'success',
            'company' => $company,
        ]);
    }

    public function featuredProducts()
    {
        $products = Product::with(['images', 'productPrice', 'category'])
            ->orderBy('id', 'desc')
            ->limit(8)
            ->get();

        return response()->json([
            'status' => 'success',
            'products' => $products,
        ]);
    }

    public function products(Request $request)
    {
        $products = Product::with(['images', 'productPrice.product.unit', 'category', 'productDiscount'])
            ->when($request->search, function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('description', 'like', "%{$request->search}%")
                    ->orWhereHas('category', function ($cq) use ($request) {
                        $cq->where('name', 'like', "%{$request->search}%");
                    });
            })
            ->when($request->category, function ($q) use ($request) {
                $category = Category::with('children')->find($request->category);
                if ($category && $category->children->isNotEmpty()) {
                    $childIds = $category->children->pluck('id')->toArray();
                    $childIds[] = $category->id;
                    $q->whereIn('category_id', $childIds);
                } else {
                    $q->where('category_id', $request->category);
                }
            })
            ->when($request->min_price, function ($q) use ($request) {
                $q->whereHas('productPrice', function ($pq) use ($request) {
                    $pq->where('price', '>=', $request->min_price);
                });
            })
            ->when($request->max_price, function ($q) use ($request) {
                $q->whereHas('productPrice', function ($pq) use ($request) {
                    $pq->where('price', '<=', $request->max_price);
                });
            })
            ->when($request->sort, function ($q) use ($request) {
                match ($request->sort) {
                    'price_asc' => $q->orderBy(
                        ProductPrice::select('price')->whereColumn('product_id', 'products.id'),
                        'asc'
                    ),
                    'price_desc' => $q->orderBy(
                        ProductPrice::select('price')->whereColumn('product_id', 'products.id'),
                        'desc'
                    ),
                    'newest' => $q->orderBy('id', 'desc'),
                    'name' => $q->orderBy('name', 'asc'),
                    default => $q->orderBy('id', 'desc'),
                };
            }, function ($q) {
                $q->orderBy('id', 'desc');
            })
            ->paginate($request->perPage ?? 12);

        return response()->json([
            'status' => 'success',
            'products' => $products,
        ]);
    }

    public function productDetail($id)
    {
        $product = Product::with([
            'images',
            'productPrice.product.unit',
            'category',
            'productDiscount',
        ])->findOrFail($id);

        $stockRecords = Stock::where('product_id', $id)
            ->orderBy('id', 'desc')
            ->get();
        $stockByAttributes = [];
        foreach ($stockRecords as $s) {
            $key = $s->attributes ? json_encode($s->attributes, JSON_UNESCAPED_UNICODE) : '{}';
            if (!isset($stockByAttributes[$key])) {
                $stockByAttributes[$key] = (float)$s->stock_qty;
            }
        }

        $reviews = ProductReview::with(['client', 'images'])
            ->where('product_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        $relatedProducts = Product::with(['images', 'productPrice'])
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $id)
            ->limit(4)
            ->get();

        return response()->json([
            'status' => 'success',
            'product' => $product,
            'reviews' => $reviews,
            'related_products' => $relatedProducts,
            'stock_by_attributes' => $stockByAttributes,
        ]);
    }

    public function categories()
    {
        $parentCategories = Category::withCount('products')
            ->whereNull('parent_id')
            ->get();

        $childCategories = Category::withCount('products')
            ->whereNotNull('parent_id')
            ->with('parent:id,name')
            ->get()
            ->groupBy(fn($cat) => $cat->parent?->name ?? 'Uncategorized');

        return response()->json([
            'status' => 'success',
            'parent_categories' => $parentCategories,
            'child_categories' => $childCategories,
        ]);
    }

    public function productReviews($productId)
    {
        $reviews = ProductReview::with(['client', 'images'])
            ->where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'reviews' => $reviews,
        ]);
    }

    public function validateCoupon(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $coupon = Coupon::where('code', $request->code)
            ->where('expiry_date', '>=', now())
            ->first();

        if (!$coupon) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or expired coupon code',
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'coupon' => $coupon,
        ]);
    }

    public function placeOrder(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_price_id' => 'required|exists:product_prices,id',
            'items.*.unit_id' => 'required|exists:units,id',
            'items.*.qty' => 'required|numeric|min:0.001',
            'items.*.price' => 'required|numeric',
            'items.*.attributes' => 'nullable|string|max:500',
            'shipping_name' => 'required|string|max:150',
            'shipping_contact' => 'required|string|max:20',
            'shipping_city_id' => 'required|exists:cities,id',
            'shipping_address' => 'required|string|max:500',
            'note' => 'nullable|string|max:500',
            'coupon_code' => 'nullable|string',
            'payment_method' => 'nullable|string',
        ]);

        $clientId = Auth::id();
        $status = Status::where('name', 'order')->first();
        $orderStatus = $status ? Status::where('parent_id', $status->id)->first() : null;

        if (!$orderStatus) {
            $orderStatus = Status::first();
        }

        $invoiceNo = 'ORD-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        $discount = 0;

        if ($request->coupon_code) {
            $coupon = Coupon::where('code', $request->coupon_code)
                ->where('expiry_date', '>=', now())
                ->first();
            if ($coupon) {
                $discount = $coupon->discount;
            }
        }

        $orders = [];
        $subtotal = 0;

        foreach ($request->items as $item) {
            $vat = 0;
            $shippingFee = $request->shipping_fee ?? 0;

            $itemDiscount = 0;
            if ($discount > 0) {
                $itemDiscount = ($item['price'] * $item['qty']) * ($discount / 100);
            }

            $order = new Order();
            $order->invoice_no = $invoiceNo;
            $order->status_id = $orderStatus->id;
            $order->client_id = $clientId;
            $order->product_id = $item['product_id'];
            $order->product_price_id = $item['product_price_id'];
            $order->unit_id = $item['unit_id'];
            $order->qty = $item['qty'];
            $order->price = $item['price'];
            $order->shipping_fee = $shippingFee;
            $order->vat = $vat;
            $order->discount = $itemDiscount;
            $order->attributes = $item['attributes'] ?? null;
            $order->shipping_name = $request->shipping_name;
            $order->shipping_contact = $request->shipping_contact;
            $order->shipping_city_id = $request->shipping_city_id;
            $order->shipping_address = $request->shipping_address;
            $order->note = $request->note;
            $order->save();

            event(new OrderUpdate($order));

            $orders[] = $order->load(['product', 'productPrice.product.unit', 'status']);
            $subtotal += $item['price'] * $item['qty'];
        }

        Cart::where('client_id', $clientId)->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Order placed successfully',
            'invoice_no' => $invoiceNo,
            'orders' => $orders,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $subtotal - ($discount > 0 ? $subtotal * ($discount / 100) : 0),
        ]);
    }

    public function myOrders()
    {
        $orders = Order::with(['product.images', 'productPrice.product.unit', 'status'])
            ->where('client_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('invoice_no');

        return response()->json([
            'status' => 'success',
            'orders' => $orders,
        ]);
    }

    public function cities()
    {
        return response()->json([
            'status' => 'success',
            'cities' => City::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . Auth::id(),
            'phone' => 'nullable|string|max:20',
            'city_id' => 'nullable|exists:cities,id',
            'address' => 'nullable|string|max:500',
        ]);

        $user = User::find(Auth::id());
        $user->name = $request->name;
        $user->email = $request->email;
        $user->contact = $request->phone;
        $user->city_id = $request->city_id;
        $user->address = $request->address;
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully',
            'user' => $user,
        ]);
    }
}

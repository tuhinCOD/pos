<?php

namespace Modules\Order\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Order\Events\OrderUpdate;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;
use Modules\ProductPrice\Models\ProductPrice;
use Modules\Status\Models\Status;
use Modules\Unit\Models\Unit;
use OpenApi\Attributes as OA;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DataExport;
use App\Jobs\ExportData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

#[OA\Tag(name: "Orders")]
class OrderController extends Controller
{
    #[OA\Get(
        path: "/orders",
        tags: ["Orders"],
        summary: "List orders",
        description: "Get paginated orders, with optional search by product",
        parameters: [
            new OA\Parameter(
                name: "search",
                in: "query",
                description: "Search term",
                required: false,
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "page",
                in: "query",
                description: "Pagination",
                required: false,
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Orders fetched successfully",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "client", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "user", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "product", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "productPrice", type: "array", items: new OA\Items(type: "object")),
                    ]
                )
            )
        ],
        security: [["bearerAuth" => []]]
    )]
    public function index(Request $request)
    {
        $orders = Order::with(['status', 'product', 'productPrice', 'client', 'user', 'shippingCity'])
        ->when($request->search, function ($query) use ($request) {
            return $query->where('invoice_no', 'like', '%' . $request->search . '%')
            ->orWhereHas('product', function ($productQuery) use ($request) {
                $productQuery->where('name', 'like', "%{$request->search}%")
                ->orWhere('barcode', 'like', "%{$request->search}%");
            })
            ->orWhereHas('client', function ($clientQuery) use ($request) {
                $clientQuery->where('name', 'like', "%$request->search%")
                    ->orWhere('contact', 'like', "%$request->search%")
                    ->orWhere('email', 'like', "%$request->search%");
            })
            ->orWhereHas('user', function ($userQuery) use ($request) {
                $userQuery->where('name', 'like', "%$request->search%")
                    ->orWhere('contact', 'like', "%$request->search%")
                    ->orWhere('email', 'like', "%$request->search%");
            });
        })
        ->when($request->status, function ($q) use ($request) {
            $q->where('status_id', $request->status);
        })
        ->when($request->products, function ($q) use ($request) {
            $productIds = explode(',', $request->products);
            $q->whereIn('product_id', $productIds);
        })
        ->orderBy('id', 'desc')
        ->paginate($request->perPage ?? 20)->onEachSide(0);

        $orderStatus = Status::where('name', 'order')->first();
        $statuses = $orderStatus ? Status::where('parent_id', $orderStatus->id)->get() : [];
        $products = Product::all();
        $product_price = ProductPrice::all();
        $unit = Unit::all();

        return response()->json([
            'status' => 'success',
            'orders' => $orders,
            'statuses' => $statuses,
            'products' => $products,
            'product_price' => $product_price,
            'unit' => $unit
        ]);
    }

    public function export(Request $request)
    {
        $days = $request->integer('days');

        if ($days) {
            $oldest = Order::min('created_at');

            if ($oldest) {
                $maxDays = (int) Carbon::parse($oldest)->diffInDays(now());

                if ($days > $maxDays) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Data is only available for the last {$maxDays} day(s). Please enter a value of {$maxDays} or less.",
                    ], 422);
                }
            }
        }

        $orders = Order::with(['branch', 'status', 'client', 'product', 'productPrice', 'user', 'updatedBy', 'unit'])
            ->latest('id')
            ->when($request->search, function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->whereHas('client', function ($clientQuery) use ($request) {
                        $clientQuery->where('name', 'like', "%{$request->search}%")
                            ->orWhere('contact', 'like', "%{$request->search}%");
                    })
                    ->orWhereHas('product', function ($productQuery) use ($request) {
                        $productQuery->where('name', 'like', "%{$request->search}%")
                            ->orWhere('barcode', 'like', "%{$request->search}%");
                    })
                    ->orWhereHas('branch', function ($branchQuery) use ($request) {
                        $branchQuery->where('name', 'like', "%{$request->search}%");
                    })
                    ->orWhere('order_no', 'like', "%{$request->search}%");
                });
            })
            ->when($request->status, fn($q) => $q->where('status_id', $request->status))
            ->when($request->invoice_nos, fn($q) => $q->whereIn('invoice_no', explode(',', $request->invoice_nos)))
            ->when($days, fn($q) => $q->where('created_at', '>=', now()->subDays($days)))
            ->get();

        $data = $orders->map(fn($o) => [
            'Order No' => $o->order_no,
            'Product' => $o->product?->name ?? '-',
            'Client' => $o->client?->name ?? '-',
            'Branch' => $o->branch?->name ?? '-',
            'Qty' => $o->qty,
            'Price' => $o->price,
            'Status' => $o->status?->name ?? '-',
            'Created By' => $o->user?->name ?? '-',
            'Updated By' => $o->updatedBy?->name ?? '-',
            'Created At' => $o->created_at?->format('Y-m-d H:i:s') ?? '-',
            'Updated At' => $o->updated_at?->format('Y-m-d H:i:s') ?? '-',
        ])->toArray();

        $headings = ['Order No', 'Product', 'Client', 'Branch', 'Qty', 'Price', 'Status', 'Created By', 'Updated By', 'Created At', 'Updated At'];

        $filename = 'orders_' . now()->timestamp . '.xlsx';
        ExportData::dispatch($data, $headings, $filename, 'Orders', 'orders');

        return response()->json(['file' => $filename]);
    }

    public function download($filename)
    {
        $path = 'exports/' . $filename;
        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'File is not ready yet.'], 404);
        }
        return response()->download(
            Storage::disk('public')->path($path),
            $filename
        )->deleteFileAfterSend(true);
    }

    // #[OA\Get(
    //     path: "/users/edit/{id}",
    //     tags: ["Users"],
    //     summary: "Show specific user",
    //     parameters: [
    //         new OA\Parameter(
    //             name: "id",
    //             in: "path",
    //             required: true,
    //             description: "ID of the user",
    //             schema: new OA\Schema(type: "integer")
    //         )
    //     ],
    //     responses: [
    //         new OA\Response(response: 200, description: "User fetched successfully")
    //     ],
    //     security: [["bearerAuth" => []]]
    // )]
    // public function show(Request $request, $id)
    // {
    //     $users = User::with('role', 'area')
    //     ->when($request->search, function ($query) use ($request) {
    //         return $query->whereAny([
    //             'name',
    //             'contact',
    //             'email',
    //         ], 'like', '%' . $request->search . '%')
    //         ->orWhereHas('role', function ($areaQuery) use ($request) {
    //             $areaQuery->where('name', 'like', "%{$request->search}%");
    //         });
    //     })
    //     ->paginate(15)->onEachSide(0);

    //     $releaseUsers = User::with('role', 'area')
    //     ->where('status', 0)
    //     ->whereHas('area')
    //     ->get();

    //     $areaLessUsers = User::with('role', 'area')
    //     ->where('status', 0)
    //     ->whereDoesntHave('area')->get();

    //     $roles = Role::all();
    //     $areas = Area::all();

    //     $user = User::with('area')->findOrFail($id);

    //     Gate::authorize('edit-user', $user->id);

    //     return response()->json([
    //         'status' => 'success',
    //         'users' => $users,
    //         'user' => $user,
    //         'areas' => $areas,
    //         'roles' => $roles,
    //         'areaLessUsers' => $areaLessUsers,
    //         'releaseUsers' => $releaseUsers
    //     ]);
    // }

    #[OA\Post(
        path: "/orders",
        summary: "Create new order",
        tags: ["Orders"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["invoice_no", "status", "product", "product_price", "unit", "shipping_fee", "qty", "price", "vat"],
                properties: [
                    new OA\Property(property: "invoice_no", type: "string"),
                    new OA\Property(property: "status", type: "integer"),
                    new OA\Property(property: "client", type: "integer"),
                    new OA\Property(property: "product", type: "integer"),
                    new OA\Property(property: "product_price", type: "integer"),
                    new OA\Property(property: "unit", type: "integer"),
                    new OA\Property(property: "qty", type: "number", format: "float"),
                    new OA\Property(property: "shipping_fee", type: "number", format: "float"),
                    new OA\Property(property: "price", type: "number", format: "float"),
                    new OA\Property(property: "vat", type: "number", format: "float"),
                    new OA\Property(property: "discount", type: "number", format: "float"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Order created"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'invoice_no' => 'required|string|max:32',
            'status' => 'required',
            'product' => 'required',
            'product_price' => 'required',
            'unit' => 'required',
            'qty' => 'required|numeric|max:99999999.999',
            'shipping_fee' => 'required|numeric|max:99999999.999',
            'price' => 'required|numeric|max:9999999999.999',
            'vat' => 'required|numeric|max:9999999999.999',
            'discount' => 'numeric|max:999999999.999',
        ]);

        $order = new Order();
        $order->invoice_no = $request->invoice_no;
        $order->status_id = $request->status;
        $order->client_id = $request->client;
        $order->product_id = $request->product;
        $order->product_price_id = $request->product_price;
        $order->unit_id = $request->unit;
        $order->qty = $request->qty;
        $order->shipping_fee = $request->shipping_fee;
        $order->price = $request->price;
        $order->vat = $request->vat;
        $order->discount = $request->discount;
        $order->save();

        event(new OrderUpdate($order));

        return response()->json([
            'status' => 'success',
            'message' => 'Order created successfully',
            'order' => $order
        ]);
    }

    #[OA\Post(
        path: "/orders/update/{id}",
        summary: "Update order",
        tags: ["Orders"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["invoice_no", "status", "shipping_fee", "product", "product_price", "unit", "qty", "price", "vat"],
                properties: [
                    new OA\Property(property: "invoice_no", type: "string"),
                    new OA\Property(property: "status", type: "integer"),
                    new OA\Property(property: "client", type: "integer"),
                    new OA\Property(property: "product", type: "integer"),
                    new OA\Property(property: "product_price", type: "integer"),
                    new OA\Property(property: "unit", type: "integer"),
                    new OA\Property(property: "qty", type: "number", format: "float"),
                    new OA\Property(property: "shipping_fee", type: "number", format: "float"),
                    new OA\Property(property: "price", type: "number", format: "float"),
                    new OA\Property(property: "vat", type: "number", format: "float"),
                    new OA\Property(property: "discount", type: "number", format: "float"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Order updated"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]

    public function update (Request $request, int $id) {
        $request->validate([
            'invoice_no' => 'required|string|max:32',
            'status' => 'required',
            'product' => 'required',
            'product_price' => 'required',
            'unit' => 'required',
            'qty' => 'required|numeric|max:99999999.99',
            'shipping_fee' => 'required|numeric|max:99999999.99',
            'price' => 'required|numeric|max:9999999999.99',
            'vat' => 'required|numeric|max:9999999999.99',
            'discount' => 'numeric|max:999999999.99',
        ]);

        $order = Order::findOrFail($id);
        $order->invoice_no = $request->invoice_no;
        $order->status_id = $request->status;
        $order->client_id = $request->client;
        $order->product_id = $request->product;
        $order->product_price_id = $request->product_price;
        $order->unit_id = $request->unit;
        $order->user_id = Auth::id();
        $order->qty = $request->qty;
        $order->shipping_fee = $request->shipping_fee;
        $order->price = $request->price;
        $order->vat = $request->vat;
        $order->discount = $request->discount;
        $order->update();

        event(new OrderUpdate($order));

        return response()->json([
            'status' => 'success',
            'message' => 'Order updated successfully',
            'order' => $order
        ]);
    }

    #[OA\Post(
        path: "/orders/delete/{id}",
        tags: ["Orders"],
        summary: "Delete order",
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID of the order",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Order deleted successfully")
        ],
        security: [["bearerAuth" => []]]
    )]
    public function destroy(int $id)
    {  
        $order = Order::findOrFail($id);

        $orderData = $order->toArray($order);

        $order->delete();

        event(new OrderUpdate($orderData));

        return response()->json([
            'status' => 'success',
            'message' => 'Order deleted successfully'
        ]);
    }

    public function destroyBulk(Request $request)
    {
        $request->validate(['invoice_nos' => 'required|array', 'invoice_nos.*' => 'string']);

        Order::whereIn('invoice_no', $request->invoice_nos)->delete();

        foreach ($request->invoice_nos as $inv) {
            event(new \Modules\Order\Events\OrderUpdate(['invoice_no' => $inv]));
        }

        return response()->json([
            'status' => 'success',
            'message' => count($request->invoice_nos) . ' invoices deleted successfully'
        ]);
    }

    public function byInvoice(string $invoiceNo)
    {
        $orders = Order::with(['status', 'product', 'productPrice', 'client', 'user', 'unit', 'shippingCity'])
            ->where('invoice_no', $invoiceNo)
            ->get();

        return response()->json([
            'status' => 'success',
            'orders' => $orders
        ]);
    }

    #[OA\Post(
        path: "/orders/product/{id}",
        tags: ["Orders"],
        summary: "Get users by product ID",
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID of the product",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Product price fetched successfully"),
        ],
        security: [["bearerAuth" => []]]
    )]
    public function getProductPriceByProduct (int $id) {
        if (!$id) {
            return response()->json([]);
        }

        $productPrice = ProductPrice::where('product_id', $id)->first();

        if (!$productPrice) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product price not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'price' => $productPrice->price,
            'point' => $productPrice->point,
            'unit_name' => $productPrice->product->unit->name ?? null    
        ]);
    }
}

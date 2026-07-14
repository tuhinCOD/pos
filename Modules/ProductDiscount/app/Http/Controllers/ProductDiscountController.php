<?php

namespace Modules\ProductDiscount\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Branch\Models\Branch;
use Modules\Product\Models\Product;
use Modules\ProductDiscount\Events\ProductDiscountUpdate;
use Modules\ProductDiscount\Models\ProductDiscount;
use Modules\ProductPrice\Models\ProductPrice;
use Modules\Status\Models\Status;
use Modules\Unit\Models\Unit;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "ProductDiscounts")]
class ProductDiscountController extends Controller
{
    #[OA\Get(
        path: "/product_discounts",
        tags: ["ProductDiscounts"],
        summary: "List product discounts",
        description: "Get paginated product discounts, with optional search by product name, invoice_no",
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
                description: "Products discounts fetched successfully",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "user", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "product", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "unit", type: "array", items: new OA\Items(type: "object")),
                    ]
                )
            )
        ],
        security: [["bearerAuth" => []]]
    )]
    public function show(int $id)
    {
        $productDiscount = ProductDiscount::with(['status', 'branch', 'unit', 'product', 'productPrice', 'user', 'updatedBy'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'productDiscount' => $productDiscount
        ]);
    }

    public function index(Request $request)
    {
        $productDiscount = ProductDiscount::with(['status', 'branch', 'unit', 'product', 'productPrice', 'user', 'updatedBy'])
        ->when($request->search, function ($query) use ($request) {
            $query->where(function ($q) use ($request) {
                $q->orWhereHas('status', function ($statusQuery) use ($request) {
                    $statusQuery->where('name', 'like', "%{$request->search}%");
                })
                ->orWhereHas('branch', function ($branchQuery) use ($request) {
                    $branchQuery->where('name', 'like', "%{$request->search}%");
                })
                ->orWhereHas('unit', function ($unitQuery) use ($request) {
                    $unitQuery->where('name', 'like', "%{$request->search}%");
                })
                ->orWhereHas('user', function ($userQuery) use ($request) {
                    $userQuery->where('name', 'like', "%{$request->search}%")
                    ->orWhere('contact', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
                })
                ->orWhereHas('product', function ($productQuery) use ($request) {
                    $productQuery->where('name', 'like', "%{$request->search}%")
                    ->orWhere('barcode', 'like', "%{$request->search}%");
                });
            });
        })
        ->when($request->status, function ($query) use ($request) {
            $query->where('status_id', $request->status);
        })
        ->when($request->branch, function ($query) use ($request) {
            $query->where('branch_id', $request->branch);
        })
        ->when($request->products, function ($query) use ($request) {
            $productIds = is_array($request->products) ? $request->products : explode(',', $request->products);
            $query->whereIn('product_id', $productIds);
        })
        ->latest('id')
        ->paginate($request->perPage ?? 15)->onEachSide(0);

        $productStatus = Status::where('name', 'product')->first();
        $statuses = $productStatus ? Status::where('parent_id', $productStatus->id)->get() : [];
        $products = Product::all();
        $productPrice = ProductPrice::all();
        $branch = Branch::all();
        $unit = Unit::all();

        return response()->json([
            'status' => 'success',
            'productDiscount' => $productDiscount,
            'productPrice' => $productPrice,
            'statuses' => $statuses,
            'products' => $products,
            'branch' => $branch,
            'unit' => $unit
        ]);
    }

    #[OA\Post(
        path: "/product_discounts",
        summary: "Create new product discount",
        tags: ["ProductDiscounts"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["status", "branch", "product", "product_price", "unit", "qty", "discount"],
                properties: [
                    new OA\Property(property: "status", type: "integer"),
                    new OA\Property(property: "branch", type: "integer"),
                    new OA\Property(property: "product", type: "integer"),
                    new OA\Property(property: "product_price", type: "integer"),
                    new OA\Property(property: "unit", type: "integer"),
                    new OA\Property(property: "qty", type: "number", format: "float"),
                    new OA\Property(property: "discount", type: "number", format: "float"),
                    new OA\Property(property: "remarks", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Product discount created"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'status' => 'required',
            'branch' => 'required',
            'product' => 'required',
            'product_price' => 'required',
            'unit' => 'required',
            'qty' => 'required|numeric|max:999999999.999',
            'discount' => 'required|numeric|max:999999999.999',
            'remarks' => 'max:500'
        ]);

        $availableQty = $this->getStockQty($request->product, $request->branch);
        if ($availableQty < $request->qty) {
            return response()->json([
                'status' => 'error',
                'message' => 'Insufficient stock',
                'errors' => ['qty' => ["Not enough stock. Available: $availableQty, Required: $request->qty"]]
            ], 422);
        }

        $productDiscount = new ProductDiscount();
        $productDiscount->status_id = $request->status;
        $productDiscount->branch_id = $request->branch;
        $productDiscount->product_id = $request->product;
        $productDiscount->product_price_id = $request->product_price;
        $productDiscount->unit_id = $request->unit;
        $productDiscount->user_id = Auth::id();
        $productDiscount->qty = $request->qty;
        $productDiscount->discount = $request->discount;
        $productDiscount->remarks = $request->remarks;
        $productDiscount->save();

        event(new ProductDiscountUpdate($productDiscount));

        return response()->json([
            'status' => 'success',
            'message' => 'Product discount created successfully',
            'productDiscount' => $productDiscount
        ]);
    }

    #[OA\Post(
        path: "/product_discounts/update/{id}",
        summary: "Update product discount",
        tags: ["ProductDiscounts"],
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
                required: ["status", "branch", "product", "product_price", "unit", "qty", "discount"],
                properties: [
                    new OA\Property(property: "status", type: "integer"),
                    new OA\Property(property: "branch", type: "integer"),
                    new OA\Property(property: "product", type: "integer"),
                    new OA\Property(property: "product_price", type: "integer"),
                    new OA\Property(property: "unit", type: "integer"),
                    new OA\Property(property: "qty", type: "number", format: "float"),
                    new OA\Property(property: "discount", type: "number", format: "float"),
                    new OA\Property(property: "remarks", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Product discount updated"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]

    public function update (Request $request, int $id) {
        $request->validate([
            'status' => 'required',
            'branch' => 'required',
            'product' => 'required',
            'product_price' => 'required',
            'unit' => 'required',
            'qty' => 'required|numeric|max:99999999.999',
            'discount' => 'required|numeric|max:999999999.999',
            'remarks' => 'max:500'
        ]);

        $productDiscount = ProductDiscount::findOrFail($id);

        $mainQty = $this->getStockQty($request->product, $request->branch);
        if ($mainQty < $request->qty) {
            return response()->json([
                'status' => 'error',
                'message' => 'Insufficient stock',
                'errors' => ['qty' => ["Not enough stock. Available: $mainQty, Required: $request->qty"]]
            ], 422);
        }
        $productDiscount->status_id = $request->status;
        $productDiscount->branch_id = $request->branch;
        $productDiscount->product_id = $request->product;
        $productDiscount->product_price_id = $request->product_price;
        $productDiscount->unit_id = $request->unit;
        $productDiscount->updated_by = Auth::id();
        $productDiscount->qty = $request->qty;
        $productDiscount->discount = $request->discount;
        $productDiscount->remarks = $request->remarks;
        $productDiscount->save();

        event(new ProductDiscountUpdate($productDiscount));

        return response()->json([
            'status' => 'success',
            'message' => 'Product discount updated successfully',
            'productDiscount' => $productDiscount
        ]);
    }

    #[OA\Post(
        path: "/product_discounts/delete/{id}",
        tags: ["ProductDiscounts"],
        summary: "Delete product discount",
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID of the product discount",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Product discount deleted successfully")
        ],
        security: [["bearerAuth" => []]]
    )]
    public function destroy(int $id)
    {  
        $productDiscount = ProductDiscount::findOrFail($id);

        $productDiscountData = $productDiscount->toArray();

        $productDiscount->delete();

        event(new ProductDiscountUpdate($productDiscountData));

        return response()->json([
            'status' => 'success',
            'message' => 'Product discount deleted successfully'
        ]);
    }

    #[OA\Post(
        path: "/product_discounts/product/{id}",
        tags: ["ProductDiscounts"],
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

        $productPrice = ProductPrice::with('product.unit:id,name')
        ->where('product_id', $id)
        ->first();

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

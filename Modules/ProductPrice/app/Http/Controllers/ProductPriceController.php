<?php

namespace Modules\ProductPrice\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Product\Models\Product;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DataExport;
use App\Jobs\ExportData;
use Illuminate\Support\Facades\Storage;
use Modules\ProductPrice\Events\ProductPriceUpdate;
use Modules\ProductPrice\Models\ProductPrice;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "ProductPrices")]
class ProductPriceController extends Controller
{
    #[OA\Get(
        path: "/product_prices",
        tags: ["ProductPrices"],
        summary: "List product prices",
        description: "Get paginated product prices, with optional search by product name, invoice_no",
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
                description: "Products prices fetched successfully",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "user", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "product", type: "array", items: new OA\Items(type: "object")),
                    ]
                )
            )
        ],
        security: [["bearerAuth" => []]]
    )]
    public function index(Request $request)
    {
        $productPrices = ProductPrice::with(['product', 'user', 'updatedBy'])
        ->orderBy('id', 'desc')
        ->when($request->search, function ($query) use ($request) {
            $query->orWhereHas('user', function ($userQuery) use ($request) {
                $userQuery->where('name', 'like', "%{$request->search}%")
                ->orWhere('contact', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%");
            })
            ->orWhereHas('product', function ($productQuery) use ($request) {
                $productQuery->where('name', 'like', "%{$request->search}%")
                ->orWhere('barcode', 'like', "%{$request->search}%");
            });
        })
        ->paginate($request->perPage ?? 15)->onEachSide(0);

        $products = Product::with('unit')->get();

        return response()->json([
            'status' => 'success',
            'productPrices' => $productPrices,
            'products' => $products,
        ]);
    }

    public function export(Request $request)
    {
        $productPrices = ProductPrice::with(['product', 'user', 'updatedBy'])
            ->orderBy('id', 'desc')
            ->when($request->search, function ($query) use ($request) {
                $query->orWhereHas('user', function ($userQuery) use ($request) {
                    $userQuery->where('name', 'like', "%{$request->search}%")
                    ->orWhere('contact', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
                })
                ->orWhereHas('product', function ($productQuery) use ($request) {
                    $productQuery->where('name', 'like', "%{$request->search}%")
                    ->orWhere('barcode', 'like', "%{$request->search}%");
                });
            })
            ->when($request->ids, fn($q) => $q->whereIn('id', explode(',', $request->ids)))
            ->get();

        $data = $productPrices->map(fn($pp) => [
            'ID' => $pp->id,
            'Product' => $pp->product?->name ?? '-',
            'Price' => $pp->price,
            'Vat' => $pp->vat,
            'Point' => $pp->point ?? 0,
            'Remarks' => $pp->remarks ?? '-',
            'Created By' => $pp->user?->name ?? '-',
            'Updated By' => $pp->updatedBy?->name ?? '-',
        ])->toArray();

        $headings = ['ID', 'Product', 'Price', 'Vat', 'Point', 'Remarks', 'Created By', 'Updated By'];

        $filename = 'product_prices_' . now()->timestamp . '.xlsx';
        ExportData::dispatch($data, $headings, $filename, 'ProductPrices', 'product_prices');

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

    public function latest(int $productId)
    {
        $price = ProductPrice::where('product_id', $productId)
            ->latest('id')
            ->first();

        if (!$price) {
            return response()->json([
                'status' => 'success',
                'price' => null
            ]);
        }

        $product = $price->product;

        return response()->json([
            'status' => 'success',
            'price' => [
                'id' => $price->id,
                'product_id' => $price->product_id,
                'price' => $price->price,
                'vat' => $price->vat,
                'point' => $price->point,
            ]
        ]);
    }

    #[OA\Post(
        path: "/product_prices",
        summary: "Create new product price",
        tags: ["ProductPrices"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["product", "price", "vat"],
                properties: [
                    new OA\Property(property: "product", type: "integer"),
                    new OA\Property(property: "price", type: "number", format: "float"),
                    new OA\Property(property: "vat", type: "number", format: "float"),
                    new OA\Property(property: "point", type: "number", format: "float"),
                    new OA\Property(property: "remarks", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Product Price created"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'product' => 'required',
            'price' => 'required|numeric|max:9999999999.999',
            'vat' => 'required|numeric|max:9999999999.999',
            'point' => 'numeric|max:99999999.999',
            'remarks' => 'max:500'
        ]);

        $productPrice = new ProductPrice();
        $productPrice->user_id = Auth::id();
        $productPrice->product_id = $request->product;
        $productPrice->price = $request->price;
        $productPrice->vat = $request->vat;
        $productPrice->point = $request->point;
        $productPrice->remarks = $request->remarks;
        $productPrice->save();

        event(new ProductPriceUpdate($productPrice));

        return response()->json([
            'status' => 'success',
            'message' => 'Product price created successfully',
            'productPrice' => $productPrice
        ]);
    }

    #[OA\Post(
        path: "/product_prices/update/{id}",
        summary: "Update product price",
        tags: ["ProductPrices"],
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
                required: ["product", "price", "vat"],
                properties: [
                    new OA\Property(property: "product", type: "integer"),
                    new OA\Property(property: "price", type: "number", format: "float"),
                    new OA\Property(property: "vat", type: "number", format: "float"),
                    new OA\Property(property: "point", type: "number", format: "float"),
                    new OA\Property(property: "remarks", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Product price updated"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]

    public function update (Request $request, int $id) {
        $request->validate([
            'product' => 'required',
            'price' => 'required|numeric|max:9999999999.999',
            'vat' => 'required|numeric|max:9999999999.999',
            'point' => 'numeric|max:99999999.999',
            'remarks' => 'max:500'
        ]);

        $productPrice = ProductPrice::findOrFail($id);
        $productPrice->updated_by = Auth::id();
        $productPrice->product_id = $request->product;
        $productPrice->price = $request->price;
        $productPrice->vat = $request->vat;
        $productPrice->point = $request->point;
        $productPrice->remarks = $request->remarks;
        $productPrice->update();

        event(new ProductPriceUpdate($productPrice));

        return response()->json([
            'status' => 'success',
            'message' => 'Product Price updated successfully',
            'productPrice' => $productPrice
        ]);
    }

    #[OA\Post(
        path: "/product_prices/delete/{id}",
        tags: ["ProductPrices"],
        summary: "Delete product price",
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID of the product price",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Product Price deleted successfully")
        ],
        security: [["bearerAuth" => []]]
    )]
    public function destroy(int $id)
    {  
        $productPrice = ProductPrice::findOrFail($id);

        $productPriceData = $productPrice->toArray($productPrice);

        $productPrice->delete();

        event(new ProductPriceUpdate($productPriceData));

        return response()->json([
            'status' => 'success',
            'message' => 'Product price deleted successfully'
        ]);
    }

    public function destroyBulk(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|exists:product_prices,id']);
        ProductPrice::whereIn('id', $request->ids)->delete();
        foreach ($request->ids as $id) {
            event(new ProductPriceUpdate(['id' => $id]));
        }
        return response()->json([
            'status' => 'success',
            'message' => count($request->ids) . ' deleted successfully'
        ]);
    }
}

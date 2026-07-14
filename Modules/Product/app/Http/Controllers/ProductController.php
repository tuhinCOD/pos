<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Category\Models\Category;
use Modules\Product\Events\ProductUpdate;

use Modules\Product\Models\Product;
use Modules\ProductImage\Jobs\UploadProductImage;
use Modules\ProductImage\Models\ProductImage;
use Modules\Status\Models\Status;
use Modules\Unit\Models\Unit;
use App\Jobs\ExportData;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Products")]
class ProductController extends Controller
{
    #[OA\Get(
        path: "/products",
        tags: ["Products"],
        summary: "List products",
        description: "Get paginated products, with optional search by product name, barcode",
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
                description: "Products fetched successfully",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "status", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "user", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "category", type: "array", items: new OA\Items(type: "object")),
                    ]
                )
            )
        ],
        security: [["bearerAuth" => []]]
    )]
    public function index(Request $request)
    {
        $products = Product::with(['status', 'category', 'user', 'images', 'unit', 'updatedBy', 'productPrice'])
        ->orderBy('id', 'desc')
        ->when($request->search, function ($query) use ($request) {
            return $query->whereAny([
                'barcode',
                'name',
                'description',
                'attributes'
            ], 'like', '%' . $request->search . '%')
            ->orWhereHas('category', function ($categoryQuery) use ($request) {
                $categoryQuery->where('name', 'like', "%{$request->search}%");
            })
            ->orWhereHas('user', function ($userQuery) use ($request) {
                $userQuery->where('name', 'like', "%$request->search%")
                    ->orWhere('contact', 'like', "%$request->search%")
                    ->orWhere('email', 'like', "%$request->search%");
                // $userQuery->where(function ($q) use ($request){
                //      $q->where('name', 'like', "%$request->search%")
                //     ->orWhere('contact', 'like', "%$request->search%")
                //     ->orWhere('email', 'like', "%$request->search%");
                // });
            })
            ->orWhereHas('status', function ($statusQuery) use ($request) {
                $statusQuery->where('name', 'like', "%{$request->search}%");
            });
        })
        ->when($request->filled('categories'), function ($query) use ($request) {
            $query->whereIn('category_id', $request->categories);
        })
        ->when($request->filled('status'), function ($query) use ($request) {
            $query->where('status_id', $request->status);
        })
        ->paginate($request->perPage ?? 15)->onEachSide(0);

        $productStatus = Status::where('name', 'product')->first();
        $statuses = Status::where('parent_id', $productStatus->id)->get();
        $categories = Category::all();
        $units = Unit::all();

        return response()->json([
            'status' => 'success',
            'products' => $products,
            'statuses' => $statuses,
            'categories' => $categories,
            'units' => $units
        ]);
    }

    public function export(Request $request)
    {
        $filters = $request->only([
            'search',
            'categories',
            'status',
            'ids'
        ]);

        $products = Product::with(['status', 'category', 'user', 'images', 'unit', 'updatedBy', 'productPrice'])
            ->orderBy('id', 'desc')
            ->when($filters['search'] ?? null, function ($query) use ($filters) {
                $search = $filters['search'];
                return $query->whereAny([
                    'barcode', 'name', 'description', 'attributes'
                ], 'like', '%' . $search . '%')
                ->orWhereHas('category', fn($q) => $q->where('name', 'like', "%{$search}%"))
                ->orWhereHas('user', fn($q) => $q->where('name', 'like', "%$search%")
                    ->orWhere('contact', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%"))
                ->orWhereHas('status', fn($q) => $q->where('name', 'like', "%{$search}%"));
            })
            ->when($filters['categories'] ?? null, fn($q) => $q->whereIn('category_id', $filters['categories']))
            ->when($filters['status'] ?? null, fn($q) => $q->where('status_id', $filters['status']))
            ->when($filters['ids'] ?? null, fn($q) => $q->whereIn('id', explode(',', $filters['ids'])))
            ->get();

        $data = $products->map(fn($p) => [
            'ID' => $p->id,
            'Barcode' => $p->barcode,
            'Name' => $p->name,
            'Category' => $p->category?->name ?? '-',
            'Unit' => $p->unit?->name ?? '-',
            'Status' => $p->status?->name ?? '-',
            'Description' => $p->description ?? '-',
            'Price' => $p->productPrice?->price ?? '-',
            'Vat' => $p->productPrice?->vat ?? '-',
            'Point' => $p->productPrice?->point ?? '-',
            'Created By' => $p->user?->name ?? '-',
            'Updated By' => $p->updatedBy?->name ?? '-',
        ])->toArray();

        $headings = ['ID', 'Barcode', 'Name', 'Category', 'Unit', 'Status', 'Description', 'Price', 'Vat', 'Point', 'Created By', 'Updated By'];

        $filename = 'products_' . now()->timestamp . '.xlsx';

        ExportData::dispatch($data, $headings, $filename, 'Products', 'products');

        return response()->json([
            'message' => 'Export started successfully.',
            'file' => $filename
        ]);
    }

    public function download($filename)
    {
        $path = 'exports/' . $filename;

        if (!Storage::disk('public')->exists($path)) {
            return response()->json([
                'message' => 'File is not ready yet.'
            ], 404);
        }

        return response()->download(
            Storage::disk('public')->path($path),
            $filename
        )->deleteFileAfterSend(true);
    }

    #[OA\Post(
        path: "/products",
        summary: "Create new product",
        tags: ["Products"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["barcode", "name", "status", "category", "unit_id"],
                    properties: [
                        new OA\Property(property: "barcode", type: "string"),
                        new OA\Property(property: "name", type: "string"),
                        new OA\Property(property: "category", type: "integer"),
                        new OA\Property(property: "unit_id", type: "integer"),
                        new OA\Property(property: "status", type: "integer"),
                        new OA\Property(property: "attributes", type: "string", example: "{\"color\":\"red\",\"size\":\"XL\"}"),
                        new OA\Property(property: "description", type: "string"),
                        new OA\Property(property: "images[]", type: "array", items: new OA\Items(type: "string", format: "binary")),
                        new OA\Property(property: "title", type: "string"),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Product created"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request)
    {
        $rules = [
            'barcode' => 'required|string|max:16',
            'name' => 'required|string|max:150',
            'barcode_type' => 'nullable|in:single,piece,weight',
            'parent_category' => 'required',
            'category' => 'required',
            'unit_id' => 'required',
            'status' => 'nullable|required',
            'attributes' => 'nullable|json',
            'description' => 'nullable|max:500',
        ];

        if ($request->hasFile('images')) {
            $rules['images'] = 'array';
            $rules['images.*'] = 'image|mimes:jpg,jpeg,png|max:2048';
            $rules['title'] = 'max:500';
        }

        $request->validate($rules);

        $product = new Product();
        $product->barcode = $request->barcode;
        $product->barcode_type = $request->barcode_type ?? 'single';
        $product->name = $request->name;
        $product->category_id = $request->category;
        $product->unit_id = $request->unit_id;
        $product->status_id = $request->status;
        $product->user_id = Auth::id();
        $product->attributes = json_decode($request->input('attributes'), true);
        $product->description = $request->description;
        $product->save();

        if ($request->hasFile('images')) {

            $files = $request->file('images');

            if (!is_array($files)) {
                $files = [$files];
            }

            foreach ($files as $file) {
                $imagePath = $file->store('uploads/product_images', 'public');
                UploadProductImage::dispatch($imagePath, $product->id, $request->title);
            }
        }

        event(new ProductUpdate($product));

        return response()->json([
            'status' => 'success',
            'message' => 'Product created successfully',
            'product' => $product
        ]);
    }

    #[OA\Post(
        path: "/products/update/{id}",
        summary: "Update product",
        tags: ["Products"],
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
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["barcode", "name", "status", "category", "unit_id"],
                    properties: [
                        new OA\Property(property: "barcode", type: "string"),
                        new OA\Property(property: "name", type: "string"),
                        new OA\Property(property: "category", type: "integer"),
                        new OA\Property(property: "unit_id", type: "integer"),
                        new OA\Property(property: "status", type: "integer"),
                        new OA\Property(property: "attributes", type: "string", example: "{\"color\":\"red\",\"size\":\"XL\"}"),
                        new OA\Property(property: "description", type: "string"),
                        new OA\Property(property: "images[]", type: "array", items: new OA\Items(type: "string", format: "binary")),
                        new OA\Property(property: "title", type: "string"),
                    ]
                )    
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Product updated"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function update (Request $request, int $id) {
        $rules = [
            'barcode' => 'required|string|max:16',
            'name' => 'required|string|max:150',
            'barcode_type' => 'nullable|in:single,piece,weight',
            'parent_category' => 'required',
            'category' => 'required',
            'unit_id' => 'required',
            'status' => 'required',
            'attributes' => 'nullable|json',
            'description' => 'max:500',
        ];

        if ($request->hasFile('images')) {
            $rules['images'] = 'array';
            $rules['images.*'] = 'image|mimes:jpg,jpeg,png|max:2048';
            $rules['title'] = 'max:500';
        }

        $request->validate($rules);

        $product = Product::findOrFail($id);
        $product->barcode = $request->barcode;
        $product->barcode_type = $request->barcode_type ?? 'single';
        $product->name = $request->name;
        $product->category_id = $request->category;
        $product->unit_id = $request->unit_id;
        $product->status_id = $request->status;
        $product->updated_by = Auth::id();
        $product->attributes = json_decode($request->input('attributes'), true);
        $product->description = $request->description;
        $product->update();

        if ($request->hasFile('images')) {
            $files = $request->file('images');

            if (!is_array($files)) {
                $files = [$files];
            }

            foreach ($files as $file) {
                $imagePath = $file->store('uploads/product_images', 'public');

                UploadProductImage::dispatch($imagePath, $product->id, $request->title);
            }
        }

        event(new ProductUpdate($product));

        return response()->json([
            'status' => 'success',
            'message' => 'Product updated successfully',
            'product' => $product
        ]);
    }

    #[OA\Post(
        path: "/products/delete/{id}",
        tags: ["Products"],
        summary: "Delete Product",
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
            new OA\Response(response: 200, description: "Product deleted successfully")
        ],
        security: [["bearerAuth" => []]]
    )]
    public function destroy(int $id)
    {  
        $product = Product::with('images')->findOrFail($id);

        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image->image);
        }

        $productData = $product->toArray();

        $product->delete();

        event(new ProductUpdate($productData));

        return response()->json([
            'status' => 'success',
            'message' => 'Product deleted successfully'
        ]);
    }

    public function destroyBulk(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|exists:products,id']);
        $products = Product::with('images')->whereIn('id', $request->ids)->get();
        foreach ($products as $product) {
            foreach ($product->images as $image) {
                Storage::disk('public')->delete($image->image);
            }
            $product->delete();
            event(new ProductUpdate($product->toArray()));
        }
        return response()->json([
            'status' => 'success',
            'message' => count($request->ids) . ' deleted successfully'
        ]);
    }

    #[OA\Post(
        path: "/products/image/delete/{id}",
        tags: ["Products"],
        summary: "Delete Product image",
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID of the product image",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Product image deleted successfully")
        ],
        security: [["bearerAuth" => []]]
    )]
    public function destroyImage(int $id)
    {  
        $image = ProductImage::findOrFail($id);

        if (!$image) {
            return response()->json([
                'status' => false,
                'message' => 'Image not found'
            ], 404);
        }

        if (Storage::disk('public')->exists($image->image)) {
            Storage::disk('public')->delete($image->image);
        }

        $imageData = $image->toArray($image);
        
        $image->delete();

        event(new ProductUpdate($imageData));

        return response()->json([
            'status' => 'success',
            'message' => 'Product image deleted successfully'
        ]);
    }

}

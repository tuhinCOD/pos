<?php

namespace Modules\ProductReview\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Product\Models\Product;
use Modules\ProductReview\Events\ProductReviewUpdate;
use Modules\ProductReview\Models\ProductReview;
use Modules\ProductReviewImage\Jobs\UploadProductReviewImage;
use Modules\ProductReviewImage\Models\ProductReviewImage;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "ProductReviews")]
class ProductReviewController extends Controller
{
    #[OA\Get(
        path: "/product_reviews",
        tags: ["ProductReviews"],
        summary: "List product reviews",
        description: "Get paginated product reviews, with optional search by product",
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
                description: "Product Reviews fetched successfully",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "client", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "product", type: "array", items: new OA\Items(type: "object")),
                    ]
                )
            )
        ],
        security: [["bearerAuth" => []]]
    )]
    public function index(Request $request)
    {
        $reviews = ProductReview::with(['product', 'client', 'images'])
        ->when($request->search, function ($query) use ($request) {
            return $query->whereAny([
                'rating',
            ], 'like', '%' . $request->search . '%')
            ->orWhereHas('product', function ($productQuery) use ($request) {
                $productQuery->where('name', 'like', "%{$request->search}%")
                ->orWhere('barcode', 'like', "%{$request->search}%");
            })
            ->orWhereHas('client', function ($clientQuery) use ($request) {
                $clientQuery->where('name', 'like', "%{$request->search}%")
                ->orWhere('contact', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%");
            });
        })
        ->paginate(20)->onEachSide(0);

        $products = Product::all();

        return response()->json([
            'status' => 'success',
            'reviews' => $reviews,
            'products' => $products,
        ]);
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
        path: "/product_reviews",
        summary: "Create new product review",
        tags: ["ProductReviews"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: "multipart/form-data",
            schema: new OA\Schema(
                required: ["review", "rating", "product"],
                properties: [
                    new OA\Property(property: "client", type: "integer"),
                    new OA\Property(property: "product", type: "integer"),
                    new OA\Property(property: "rating", type: "number", format: "float"),
                    new OA\Property(property: "review", type: "string"),
                    new OA\Property(property: "images[]", type: "array", items: new OA\Items(type: "string", format: "binary")),
                ]
            )
        )
    ),
        responses: [
            new OA\Response(response: 200, description: "Product review created"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request)
    {
        $rules = [
            'rating' => 'required|string',
            'review' => 'required|string|max:16',
            'product' => 'required',
        ];

        if ($request->hasFile('images')) {
            $rules['images'] = 'array';
            $rules['images.*'] = 'image|mimes:jpg,jpeg,png|max:2048';
        }

        $request->validate($rules);

        $review = new ProductReview();
        $review->client_id = $request->client;
        $review->product_id = $request->product;
        $review->rating = $request->rating;
        $review->review = $request->review;
        $review->save();

        if ($request->hasFile('images')) {
            
            $files = $request->file('images');

            if (!is_array($files)) {
                $files = [$files];
            }

            foreach ($files as $file) {
                $imagePath = $file->store('uploads/product_review_images', 'public');

                UploadProductReviewImage::dispatch($imagePath, $review->id);
            }
        }

        event(new ProductReviewUpdate($review));

        return response()->json([
            'status' => 'success',
            'message' => 'Product review created successfully',
            'review' => $review
        ]);
    }

    #[OA\Post(
        path: "/product_reviews/update/{id}",
        summary: "Update product review",
        tags: ["ProductReviews"],
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
                required: ["review", "rating", "product"],
                properties: [
                    new OA\Property(property: "client", type: "integer"),
                    new OA\Property(property: "product", type: "integer"),
                    new OA\Property(property: "rating", type: "string"),
                    new OA\Property(property: "review", type: "string"),
                    new OA\Property(property: "images[]", type: "array", items: new OA\Items(type: "string", format: "binary")),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Product review updated"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]

    public function update (Request $request, int $id) {
        $rules = [
            'rating' => 'required|string',
            'review' => 'required|string|max:16',
            'product' => 'required',
        ];

        if ($request->hasFile('images')) {
            $rules['images'] = 'array';
            $rules['images.*'] = 'image|mimes:jpg,jpeg,png|max:2048';
        }

        $request->validate($rules);

        $review = ProductReview::findOrFail($id);
        $review->client_id = $request->client;
        $review->product_id = $request->product;
        $review->rating = $request->rating;
        $review->review = $request->review;
        $review->update();

        if ($request->hasFile('images')) {
            
            $files = $request->file('images');

            if (!is_array($files)) {
                $files = [$files];
            }

            foreach ($files as $file) {
                $imagePath = $file->store('uploads/product_review_images', 'public');

                UploadProductReviewImage::dispatch($imagePath, $review->id);
            }
        }

        event(new ProductReviewUpdate($review));

        return response()->json([
            'status' => 'success',
            'message' => 'Product review updated successfully',
            'review' => $review
        ]);
    }

    #[OA\Post(
        path: "/product_reviews/delete/{id}",
        tags: ["ProductReviews"],
        summary: "Delete product review",
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID of the product review",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Product Review deleted successfully")
        ],
        security: [["bearerAuth" => []]]
    )]
    public function destroy(int $id)
    {
        $review = ProductReview::with('images')->findOrFail($id);

        foreach ($review->images as $image) {
            Storage::disk('public')->delete($image->image);
        }

        $reviewData = $review->toArray();

        $review->delete();

        event(new ProductReviewUpdate($reviewData));

        return response()->json([
            'status' => 'success',
            'message' => 'Product Review deleted successfully'
        ]);
    }

    #[OA\Post(
        path: "/product_reviews/image/delete/{id}",
        tags: ["ProductReviews"],
        summary: "Delete Product review image",
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID of the product review image",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Product review image deleted successfully")
        ],
        security: [["bearerAuth" => []]]
    )]
    public function destroyImage(int $id)
    {  
        $image = ProductReviewImage::findOrFail($id);

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

        event(new ProductReviewUpdate($imageData));

        return response()->json([
            'status' => 'success',
            'message' => 'Product review image deleted successfully'
        ]);
    }
}

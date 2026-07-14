<?php

namespace Modules\Cart\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Cart\Models\Cart;
use Modules\Product\Models\Product;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Cart")]
class CartController extends Controller
{
    #[OA\Get(
        path: "/cart",
        tags: ["Cart"],
        summary: "List cart",
        description: "Get paginated cart, with optional search by product",
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
                description: "Cart fetched successfully",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "client", type: "array", items: new OA\Items(type: "object")),
                    ]
                )
            )
        ],
        security: [["bearerAuth" => []]]
    )]
    public function index(Request $request)
    {
        $cart = Cart::with(['product.images', 'product.productPrice', 'client'])
        ->where('client_id', Auth::id())
        ->when($request->search, function ($query) use ($request) {
            $query->orWhereHas('product', function ($productQuery) use ($request) {
                $productQuery->where('name', 'like', "%{$request->search}%")
                ->orWhere('barcode', 'like', "%{$request->search}%");
            });
        })
        ->orderBy('id')
        ->paginate(20)->onEachSide(0);

        return response()->json([
            'status' => 'success',
            'cart' => $cart,
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
        path: "/cart",
        summary: "Create new cart",
        tags: ["Cart"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["product", "qty"],
                properties: [
                    new OA\Property(property: "product", type: "integer"),
                    new OA\Property(property: "qty", type: "number", format: "float"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Cart created"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'product' => 'required',
            'qty' => 'required|numeric|max:99999999.999',
            'attributes' => 'nullable|string|max:500',
        ]);

        $attributes = $request->input('attributes');

        $cart = Cart::where('client_id', Auth::id())
            ->where('product_id', $request->product)
            ->where('attributes', $attributes)
            ->first();

        if ($cart) {
            $cart->qty += $request->qty;
        } else {
            $cart = new Cart();
            $cart->client_id = Auth::id();
            $cart->product_id = $request->product;
            $cart->qty = $request->qty;
            $cart->attributes = $attributes;
        }

        $cart->save();

        return response()->json([
            'status' => 'success',
            'message' => $cart->wasRecentlyCreated ? 'Cart created successfully' : 'Cart updated successfully',
            'cart' => $cart
        ]);
    }

    #[OA\Post(
        path: "/cart/update/{id}",
        summary: "Update cart",
        tags: ["Cart"],
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
                required: ["product", "qty"],
                properties: [
                    new OA\Property(property: "product", type: "integer"),
                    new OA\Property(property: "qty", type: "number", format: "float"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Sale updated"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]

    public function update (Request $request, int $id) {
        $request->validate([
            'product' => 'required',
            'qty' => 'required|numeric|max:99999999.999',
            'attributes' => 'nullable|string|max:500',
        ]);

        $cart = Cart::findOrFail($id);
        $cart->client_id = Auth::id();
        $cart->product_id = $request->product;
        $cart->qty = $request->qty;
        $cart->attributes = $request->input('attributes');
        $cart->update();

        return response()->json([
            'status' => 'success',
            'message' => 'Cart updated successfully',
            'cart' => $cart
        ]);
    }

    #[OA\Post(
        path: "/cart/delete/{id}",
        tags: ["Cart"],
        summary: "Delete cart",
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID of the cart",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Cart deleted successfully")
        ],
        security: [["bearerAuth" => []]]
    )]
    public function destroy(int $id)
    {  
        $cart = Cart::findOrFail($id);

        $cart->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Cart deleted successfully'
        ]);
    }
}

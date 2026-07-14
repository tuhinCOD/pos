<?php

namespace Modules\Coupon\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Modules\Coupon\Events\CouponUpdate;
use Modules\Coupon\Models\Coupon;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Coupons")]
class CouponController extends Controller
{
    #[OA\Get(
        path: "/coupons",
        tags: ["Coupons"],
        summary: "List coupons",
        description: "Get paginated Coupons, with optional search by code",
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
                description: "Coupons fetched successfully",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "coupons", type: "array", items: new OA\Items(type: "object")),
                    ]
                )
            )
        ],
        security: [["bearerAuth" => []]]
    )]
    public function index(Request $request)
    {
        $coupons = Coupon::with('user')
        ->when($request->search, function ($query) use ($request) {
            return $query->whereAny([
                'code',
            ], 'like', '%' . $request->search . '%');
        })
        ->paginate(15)->onEachSide(0);

        return response()->json([
            'status' => 'success',
            'coupons' => $coupons,
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
        path: "/coupons",
        summary: "Create new coupon",
        tags: ["Coupons"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["code", "discount", "expiry_date"],
                    properties: [
                        new OA\Property(property: "code", type: "string"),
                        new OA\Property(property: "discount", type: "number", format: "float"),
                        new OA\Property(property: "address", type: "string", format: "date-time"),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Coupon created"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('coupons'), 
            ],
            'discount' => 'required|numeric|max:99999999.999',
            'expiry_date' => 'required|date',
        ]);

        $coupon = new Coupon();
        $coupon->code = $request->code;
        $coupon->discount = $request->discount;
        $coupon->user_id = Auth::id();
        $coupon->expiry_date = $request->expiry_date;
        $coupon->save();

        event(new CouponUpdate($coupon));

        return response()->json([
            'status' => 'success',
            'message' => 'Coupon created successfully',
            'coupon' => $coupon
        ]);
    }

    #[OA\Post(
        path: "/coupons/update/{id}",
        summary: "Update coupon",
        tags: ["Coupons"],
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
                    type: "object",
                    required: ["code", "discount", "expiry_date"],
                    properties: [
                        new OA\Property(property: "code", type: "string"),
                        new OA\Property(property: "discount", type: "number", format: "float"),
                        new OA\Property(property: "address", type: "string", format: "date-time"),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Coupon updated"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]

    public function update (Request $request, int $id) {
        $request->validate([
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('coupons')->ignore($id), 
            ],
            'discount' => 'required|numeric|max:99999999.999',
            'expiry_date' => 'required|date',
        ]);

        $coupon = Coupon::findOrFail($id);
        $coupon->code = $request->code;
        $coupon->discount = $request->discount;
        $coupon->user_id = Auth::id();
        $coupon->expiry_date = $request->expiry_date;
        $coupon->update();

        event(new CouponUpdate($coupon));

        return response()->json([
            'status' => 'success',
            'message' => 'Coupon updated successfully',
            'coupon' => $coupon
        ]);
    }

    #[OA\Post(
        path: "/coupons/delete/{id}",
        tags: ["Coupons"],
        summary: "Delete coupon",
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID of the coupon",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Coupon deleted successfully")
        ],
        security: [["bearerAuth" => []]]
    )]
    public function destroy(int $id)
    {  
        $coupon = Coupon::findOrFail($id);

        $couponData = $coupon->toArray($coupon);

        $coupon->delete();

        event(new CouponUpdate($couponData));

        return response()->json([
            'status' => 'success',
            'message' => 'Coupon deleted successfully'
        ]);
    }
}

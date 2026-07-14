<?php

namespace Modules\Wishlist\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Wishlist\Models\Wishlist;

class WishlistController extends Controller
{
    public function index()
    {
        $wishlists = Wishlist::with('product.images', 'product.unit', 'product.category', 'product.productPrice')
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'wishlists' => $wishlists,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $exists = Wishlist::where('user_id', Auth::id())
            ->where('product_id', $request->product_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product already in wishlist',
            ], 422);
        }

        $wishlist = new Wishlist();
        $wishlist->user_id = Auth::id();
        $wishlist->product_id = $request->product_id;
        $wishlist->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Added to wishlist',
            'wishlist' => $wishlist->load('product.images', 'product.productPrice', 'product.category'),
        ]);
    }

    public function destroy(int $id)
    {
        $wishlist = Wishlist::where('user_id', Auth::id())->findOrFail($id);
        $wishlist->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Removed from wishlist',
        ]);
    }
}

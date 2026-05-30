<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;

class CartController extends Controller
{
    // Get user's cart with items and product details
    public function index(Request $request)
    {
        $cart = Cart::with(['items.product.images'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$cart) {
            return response()->json(['id' => null, 'items' => []]);
        }

        return response()->json($cart);
    }

    // Add a product to the cart (or increase quantity if already exists)
    public function addItem(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'integer|min:1|default:1'
        ]);

        $user = $request->user();
        $product = Product::findOrFail($request->product_id);

        // Get or create cart for this user
        $cart = Cart::firstOrCreate(['user_id' => $user->id]);

        // Check if product already in cart
        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $product->id)
            ->first();

        if ($cartItem) {
            // Increase quantity
            $cartItem->quantity += $request->quantity;
            $cartItem->save();
        } else {
            // Add new item
            $cartItem = CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'quantity' => $request->quantity
            ]);
        }

        return response()->json(['message' => 'Item added', 'item' => $cartItem], 201);
    }

    // Update quantity of a cart item
    public function updateItem(Request $request, int $itemId)

    {
        $request->validate(['quantity' => 'required|integer|min:1']);

        $cartItem = CartItem::findOrFail($itemId);
        // Ensure the cart belongs to the authenticated user
        $cart = Cart::where('id', $cartItem->cart_id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $cartItem->quantity = $request->quantity;
        $cartItem->save();

        return response()->json(['message' => 'Quantity updated', 'item' => $cartItem]);
    }

    // Remove a single item from cart
    public function removeItem(Request $request, int $itemId)

    {
        $cartItem = CartItem::findOrFail($itemId);
        $cart = Cart::where('id', $cartItem->cart_id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $cartItem->delete();

        return response()->json(['message' => 'Item removed']);
    }

    // Clear entire cart
    public function clear(Request $request)
    {
        $cart = Cart::where('user_id', $request->user()->id)->first();
        if ($cart) {
            $cart->items()->delete();
            $cart->delete();
        }
        return response()->json(['message' => 'Cart cleared']);
    }

}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    // List all orders for the authenticated user (both as buyer or seller)
    public function index(Request $request)
    {
        $user = $request->user();
        // Get orders where user is buyer OR seller (for simplicity, we show both)
        $orders = Order::where('buyer_id', $user->id)
            ->orWhere('seller_id', $user->id)
            ->with('items.product.images')
            ->latest()
            ->get();
        return response()->json($orders);
    }

    // Checkout: convert current user's cart into order(s)
    public function store(Request $request)
    {
        $user = $request->user();
        $cart = Cart::with('items.product')->where('user_id', $user->id)->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Your cart is empty'], 422);
        }

        // Group cart items by seller_id (because each order belongs to one seller)
        $itemsBySeller = $cart->items->groupBy(function ($item) {
            return $item->product->seller_id;
        });

        DB::beginTransaction();
        try {
            $orders = [];
            foreach ($itemsBySeller as $sellerId => $items) {
                $total = $items->sum(function ($item) {
                    $discount = $item->product->discount_price;
                    $itemPrice = ($discount && $discount > 0) ? $discount : $item->product->price;
                    return $itemPrice * $item->quantity;
                });

                $order = Order::create([
                    'buyer_id'     => $user->id,
                    'seller_id'    => $sellerId,
                    'total_amount' => $total,
                    'status'       => 'pending',
                ]);

                foreach ($items as $cartItem) {
                    $discount = $cartItem->product->discount_price;
                    $itemPrice = ($discount && $discount > 0) ? $discount : $cartItem->product->price;
                    OrderItem::create([
                        'order_id'           => $order->id,
                        'product_id'         => $cartItem->product_id,
                        'price_at_purchase'  => $itemPrice,
                        'quantity'           => $cartItem->quantity,
                    ]);
                }
                $orders[] = $order->load('items.product');
            }

            // Clear the cart
            $cart->items()->delete();
            $cart->delete();

            DB::commit();
            return response()->json(['orders' => $orders, 'message' => 'Order placed successfully'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Checkout failed: ' . $e->getMessage()], 500);
        }
    }
}

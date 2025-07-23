<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    /**
     * Get user's cart for a specific merchant
     */
    public function show($merchantId = null)
    {
        $user = Auth::user();

        $query = Cart::with(['items.menu.merchant', 'merchant'])
            ->forUser($user->id)
            ->active();

        if ($merchantId) {
            $query->forMerchant($merchantId);
            $cart = $query->first();
        } else {
            // Get all active carts if no merchant specified
            $carts = $query->get();
            return response()->json([
                'message' => 'Carts retrieved successfully',
                'data' => $carts,
                'total_carts' => $carts->count(),
                'total_items' => $carts->sum('total_items'),
                'total_amount' => $carts->sum('total_amount')
            ]);
        }

        if (!$cart) {
            return response()->json([
                'message' => 'Cart not found',
                'data' => null
            ]);
        }

        return response()->json([
            'message' => 'Cart retrieved successfully',
            'data' => $cart,
            'total_items' => $cart->total_items,
            'total_amount' => $cart->total_amount
        ]);
    }

    /**
     * Add item to cart
     */
    public function addItem(Request $request)
    {
        $request->validate([
            'menu_id' => 'required|exists:menus,id',
            'quantity' => 'required|integer|min:1|max:100',
            'notes' => 'nullable|string|max:255'
        ]);

        $user = Auth::user();
        $menu = Menu::findOrFail($request->menu_id);

        // Check if menu is available
        if (!$menu->is_available) {
            return response()->json([
                'message' => 'Menu is not available'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Get or create cart for this merchant
            $cart = Cart::firstOrCreate([
                'user_id' => $user->id,
                'merchant_id' => $menu->user_id,
                'status' => 'active'
            ], [
                'total_amount' => 0
            ]);

            // Check if item already exists in cart
            $cartItem = CartItem::where('cart_id', $cart->id)
                ->where('menu_id', $menu->id)
                ->first();

            if ($cartItem) {
                // Update quantity if item exists
                $cartItem->quantity += $request->quantity;
                $cartItem->notes = $request->notes;
                $cartItem->save();
            } else {
                // Create new cart item
                $cartItem = CartItem::create([
                    'cart_id' => $cart->id,
                    'menu_id' => $menu->id,
                    'quantity' => $request->quantity,
                    'unit_price' => $menu->price,
                    'notes' => $request->notes
                ]);
            }

            DB::commit();

            // Load fresh cart with relations
            $cart->load(['items.menu.merchant', 'merchant']);

            return response()->json([
                'message' => 'Item added to cart successfully',
                'data' => $cart,
                'added_item' => $cartItem->load('menu')
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Failed to add item to cart',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update cart item quantity
     */
    public function updateItem(Request $request, $itemId)
    {
        $request->validate([
            'quantity' => 'required|integer|min:0|max:100',
            'notes' => 'nullable|string|max:255'
        ]);

        $user = Auth::user();
        $cartItem = CartItem::whereHas('cart', function ($query) use ($user) {
            $query->where('user_id', $user->id)->where('status', 'active');
        })->findOrFail($itemId);

        if ($request->quantity == 0) {
            // Remove item if quantity is 0
            return $this->removeItem($itemId);
        }

        $cartItem->update([
            'quantity' => $request->quantity,
            'notes' => $request->notes
        ]);

        $cart = $cartItem->cart->load(['items.menu.merchant', 'merchant']);

        return response()->json([
            'message' => 'Cart item updated successfully',
            'data' => $cart,
            'updated_item' => $cartItem->load('menu')
        ]);
    }

    /**
     * Remove item from cart
     */
    public function removeItem($itemId)
    {
        $user = Auth::user();
        $cartItem = CartItem::whereHas('cart', function ($query) use ($user) {
            $query->where('user_id', $user->id)->where('status', 'active');
        })->findOrFail($itemId);

        $cart = $cartItem->cart;
        $cartItem->delete();

        // Delete cart if empty
        if ($cart->isEmpty()) {
            $cart->delete();
            return response()->json([
                'message' => 'Item removed and cart deleted (was empty)',
                'data' => null
            ]);
        }

        $cart->load(['items.menu.merchant', 'merchant']);

        return response()->json([
            'message' => 'Item removed from cart successfully',
            'data' => $cart
        ]);
    }

    /**
     * Clear entire cart
     */
    public function clear($merchantId = null)
    {
        $user = Auth::user();

        $query = Cart::forUser($user->id)->active();

        if ($merchantId) {
            $query->forMerchant($merchantId);
        }

        $deletedCount = $query->delete();

        return response()->json([
            'message' => $merchantId
                ? 'Cart cleared successfully'
                : "All carts cleared successfully ({$deletedCount} carts)",
            'cleared_carts' => $deletedCount
        ]);
    }

    /**
     * Convert cart to transaction
     */
    public function checkout(Request $request, $merchantId)
    {
        $request->validate([
            'payment_method_id' => 'required|exists:payment_methods,id',
            'notes' => 'nullable|string|max:500'
        ]);

        $user = Auth::user();
        $cart = Cart::with(['items.menu', 'merchant'])
            ->forUser($user->id)
            ->forMerchant($merchantId)
            ->active()
            ->first();

        if (!$cart || $cart->isEmpty()) {
            return response()->json([
                'message' => 'Cart is empty or not found'
            ], 400);
        }

        // Check if all items are still available
        foreach ($cart->items as $item) {
            if (!$item->menu->is_available) {
                return response()->json([
                    'message' => "Menu '{$item->menu->name}' is no longer available"
                ], 400);
            }
        }

        DB::beginTransaction();
        try {
            // Create transaction (you might want to move this to TransactionController)
            $transaction = \App\Models\Transaction::create([
                'user_id' => $user->id,
                'merchant_id' => $merchantId,
                'total_amount' => $cart->total_amount,
                'status' => 'pending',
                'payment_method_id' => $request->payment_method_id,
                'notes' => $request->notes
            ]);

            // Create transaction items from cart items
            foreach ($cart->items as $cartItem) {
                \App\Models\TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'menu_id' => $cartItem->menu_id,
                    'quantity' => $cartItem->quantity,
                    'unit_price' => $cartItem->unit_price,
                    'total_price' => $cartItem->total_price,
                    'notes' => $cartItem->notes
                ]);
            }

            // Mark cart as converted
            $cart->update(['status' => 'converted']);

            DB::commit();

            return response()->json([
                'message' => 'Checkout successful',
                'transaction' => $transaction->load(['items.menu', 'paymentMethod'])
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Checkout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

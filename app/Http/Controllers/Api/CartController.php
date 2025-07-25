<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CartController extends Controller
{
    /**
     * Get user's cart for a specific merchant
     */
    public function show($merchantId = null)
    {
        $user = Auth::user();

        $query = Cart::with(['cartItems.menu.merchant', 'merchant'])
            ->forUser($user->id)
            ->active();

        if ($merchantId) {
            $query->forMerchant($merchantId);
            $cart = $query->first();
        } else {
            // Get all active carts if no merchant specified
            $carts = $query->get();

            // Update image_url for all menus in all carts
            foreach ($carts as $cart) {
                foreach ($cart->cartItems as $item) {
                    if ($item->menu && $item->menu->image_url) {
                        $item->menu->image_url = asset('public_storage/' . $item->menu->image_url);
                    }
                }
            }

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

        // Update image_url for all menus in this cart
        foreach ($cart->cartItems as $item) {
            if ($item->menu && $item->menu->image_url) {
                $item->menu->image_url = asset('public_storage/' . $item->menu->image_url);
            }
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

        // Check if stock is sufficient
        if ($menu->stock < $request->quantity) {
            return response()->json([
                'message' => "Insufficient stock. Available: {$menu->stock}, Requested: {$request->quantity}"
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
                // Check if total quantity (existing + new) is available
                $totalQuantity = $cartItem->quantity + $request->quantity;
                if ($menu->stock < $totalQuantity) {
                    return response()->json([
                        'message' => "Insufficient stock. Available: {$menu->stock}, In cart: {$cartItem->quantity}, Requested: {$request->quantity}"
                    ], 400);
                }

                // Update quantity if item exists
                $cartItem->quantity = $totalQuantity;
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
            $cart->load(['cartItems.menu.merchant', 'merchant']);

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

        // Check if stock is sufficient for the new quantity
        if ($cartItem->menu->stock < $request->quantity) {
            return response()->json([
                'message' => "Insufficient stock. Available: {$cartItem->menu->stock}, Requested: {$request->quantity}"
            ], 400);
        }

        $cartItem->update([
            'quantity' => $request->quantity,
            'notes' => $request->notes
        ]);

        $cart = $cartItem->cart->load(['cartItems.menu.merchant', 'merchant']);

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
        if ($cart->cartItems()->count() == 0) {
            $cart->delete();
            return response()->json([
                'message' => 'Item removed and cart deleted (was empty)',
                'data' => null
            ]);
        }

        $cart->load(['cartItems.menu.merchant', 'merchant']);

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
        $cart = Cart::with(['cartItems.menu', 'merchant'])
            ->forUser($user->id)
            ->forMerchant($merchantId)
            ->active()
            ->first();

        if (!$cart || $cart->cartItems->count() == 0) {
            return response()->json([
                'message' => 'Cart is empty or not found'
            ], 400);
        }

        // Check if all items are still available and have sufficient stock
        foreach ($cart->cartItems as $item) {
            if (!$item->menu->is_available) {
                return response()->json([
                    'message' => "Menu '{$item->menu->name}' is no longer available"
                ], 400);
            }

            // Check if stock is sufficient
            if ($item->menu->stock < $item->quantity) {
                return response()->json([
                    'message' => "Insufficient stock for menu '{$item->menu->name}'. Available: {$item->menu->stock}, Requested: {$item->quantity}"
                ], 400);
            }
        }

        DB::beginTransaction();
        try {
            // Get actual merchant ID from cart items
            $actualMerchantId = $cart->cartItems->first()->menu->user_id;

            // Validate that all items belong to the same merchant
            foreach ($cart->cartItems as $item) {
                if ($item->menu->user_id !== $actualMerchantId) {
                    return response()->json([
                        'message' => 'Cart contains items from different merchants'
                    ], 400);
                }
            }

            // Create transaction (you might want to move this to TransactionController)
            $transaction = \App\Models\Transaction::create([
                'user_id' => $user->id,
                'cashier_id' => $actualMerchantId, // menggunakan merchant ID yang sebenarnya
                'total_price' => $cart->total_amount,
                'status' => 'unpaid',
                'payment_method' => null, // akan diset saat payment dibuat
                'notes' => $request->notes
            ]);

            // Create transaction items from cart items
            foreach ($cart->cartItems as $cartItem) {
                \App\Models\TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'menu_id' => $cartItem->menu_id,
                    'quantity' => $cartItem->quantity,
                    'price' => $cartItem->unit_price
                ]);
            }

            // Delete cart and cart items after successful checkout
            Log::info('Deleting cart items for cart ID: ' . $cart->id);
            $deletedItems = $cart->cartItems()->delete();
            Log::info('Deleted ' . $deletedItems . ' cart items');

            Log::info('Deleting cart ID: ' . $cart->id);
            $cart->delete();
            Log::info('Cart deleted successfully');

            DB::commit();

            return response()->json([
                'message' => 'Checkout successful',
                'transaction' => $transaction->load(['items.menu'])
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Checkout failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Checkout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

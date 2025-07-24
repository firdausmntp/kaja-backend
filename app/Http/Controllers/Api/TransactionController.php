<?php

namespace App\Http\Controllers\Api;

use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    public function index()
    {
        $transactions = Transaction::with([
            'items.menu:id,name,price,image_url,user_id',
            'items.menu.category:id,name',
            'items.menu.merchant:id,name',
            'merchant:id,name,email',
            'payment:id,transaction_id,amount,method,paid_at,proof,status,notes'
        ])
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($transaction) {
                // Add image URLs for menu items
                $transaction->items->each(function ($item) {
                    if ($item->menu && $item->menu->image_url) {
                        $item->menu->image_url = asset('storage/' . $item->menu->image_url);
                    }
                });

                // Add payment proof URL if exists
                if ($transaction->payment && $transaction->payment->proof) {
                    $transaction->payment->proof_url = asset('storage/' . $transaction->payment->proof);
                    // Remove raw proof path for cleaner response
                    unset($transaction->payment->proof);
                }

                return $transaction;
            });

        return response()->json($transactions);
    }

    public function store(Request $request)
    {
        $request->validate([
            'total_price' => 'required|numeric|min:0',
            'payment_method' => 'required|string|exists:payment_methods,name',
            'items' => 'required|array|min:1',
            'items.*.menu_id' => 'required|exists:menus,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
            'customer_name' => 'nullable|string|max:100',
            'customer_phone' => 'nullable|string|max:20',
            'order_type' => 'nullable|in:dine_in,takeaway,delivery',
        ]);

        // Get merchant/cashier ID from the first menu item
        $firstMenuItem = \App\Models\Menu::find($request->items[0]['menu_id']);
        $cashierId = $firstMenuItem ? $firstMenuItem->user_id : null;

        // Validate all items belong to the same merchant
        foreach ($request->items as $item) {
            $menu = \App\Models\Menu::find($item['menu_id']);
            if (!$menu || $menu->user_id !== $cashierId) {
                return response()->json([
                    'message' => 'Semua item harus dari merchant yang sama.',
                    'errors' => ['items' => ['All items must be from the same merchant']]
                ], 422);
            }
        }

        $transaction = Transaction::create([
            'user_id' => Auth::id(),
            'cashier_id' => $cashierId, // Set merchant/penjual ID as cashier
            'total_price' => $request->total_price,
            'payment_method' => $request->payment_method,
            'status' => 'pending',
            'notes' => $request->notes,
            'customer_name' => $request->customer_name,
            'customer_phone' => $request->customer_phone,
            'order_type' => $request->order_type ?? 'takeaway',
        ]);

        foreach ($request->items as $item) {
            TransactionItem::create([
                'transaction_id' => $transaction->id,
                'menu_id' => $item['menu_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
            ]);
        }

        return response()->json($transaction->load('items'), 201);
    }

    public function show($id)
    {
        $transaction = Transaction::with([
            'items.menu:id,name,price,image_url,user_id',
            'items.menu.category:id,name',
            'items.menu.merchant:id,name',
            'merchant:id,name,email',
            'payment:id,transaction_id,amount,method,paid_at,proof,status,notes'
        ])
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // Add image URLs for menu items
        $transaction->items->each(function ($item) {
            if ($item->menu && $item->menu->image_url) {
                $item->menu->image_url = asset('storage/' . $item->menu->image_url);
            }
        });

        // Add payment proof URL if exists
        if ($transaction->payment && $transaction->payment->proof) {
            $transaction->payment->proof_url = asset('storage/' . $transaction->payment->proof);
            // Remove raw proof path for cleaner response
            unset($transaction->payment->proof);
        }

        return response()->json($transaction);
    }

    public function update(Request $request, $id)
    {
        $transaction = Transaction::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $request->validate([
            'status' => 'in:pending,paid,cancelled'
        ]);

        $transaction->update([
            'status' => $request->status
        ]);

        return response()->json($transaction);
    }

    public function destroy($id)
    {
        $transaction = Transaction::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $transaction->delete();

        return response()->json(['message' => 'Transaction deleted']);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PenjualController extends Controller
{
    public function transactions()
    {
        if (Auth::user()->role !== 'penjual') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $transactions = Transaction::with([
            'user:id,name,email',
            'items.menu:id,name,price,image_url,user_id',
            'items.menu.category:id,name',
            'payment:id,transaction_id,amount,method,paid_at,proof,status,notes'
        ])
            ->where('cashier_id', Auth::id()) // Only transactions for this merchant
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

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,paid,confirmed,ready,completed,cancelled'
        ]);

        $transaction = Transaction::where('cashier_id', Auth::id())
            ->findOrFail($id); // Add ownership check

        $transaction->update([
            'status' => $request->status
        ]);

        $transaction->load([
            'user:id,name,email',
            'items.menu:id,name,price,image_url',
            'payment:id,transaction_id,amount,method,paid_at,proof,status,notes'
        ]);

        // Add payment proof URL if exists
        if ($transaction->payment && $transaction->payment->proof) {
            $transaction->payment->proof_url = asset('storage/' . $transaction->payment->proof);
            // Remove raw proof path for cleaner response
            unset($transaction->payment->proof);
        }

        return response()->json([
            'message' => 'Status transaksi berhasil diupdate',
            'transaction' => $transaction
        ]);
    }

    public function show($id)
    {
        $transaction = Transaction::with([
            'user:id,name,email',
            'items.menu:id,name,price,image_url,user_id',
            'items.menu.category:id,name',
            'payment:id,transaction_id,amount,method,paid_at,proof,status,notes'
        ])
            ->where('cashier_id', Auth::id()) // Add ownership check
            ->findOrFail($id);

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
}

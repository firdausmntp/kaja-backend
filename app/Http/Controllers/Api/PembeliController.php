<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Transaction;
use App\Models\TransactionItem;

class PembeliController extends Controller
{
    public function transactions()
    {
        $transactions = Transaction::with('items.menu')
            ->where('user_id', Auth::id())
            ->get();

        return response()->json($transactions);
    }

    /**
     * Get transactions for the current customer (pembeli)
     * GET /api/pembeli/transactions
     */
    public function myTransactions(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'pembeli') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $transactions = Transaction::with([
            'items.menu:id,name,price,image_url',
            'items.menu.category:id,name',
            'merchant:id,name,email',
            'payment:id,transaction_id,amount,method,status',
            'chats' => function ($query) use ($user) {
                $query->where('sender_id', '!=', $user->id)
                    ->where('is_read', false);
            }
        ])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($transaction) {
                // Add image URLs for menu items
                $transaction->items->each(function ($item) {
                    if ($item->menu && $item->menu->image_url) {
                        $item->menu->image_url = asset('storage/' . $item->menu->image_url);
                    }
                });

                // Add unread chat count
                $transaction->unread_chats_count = $transaction->chats->count();
                unset($transaction->chats); // Remove the chats data, we only need the count

                return $transaction;
            });

        return response()->json([
            'message' => 'Transactions retrieved successfully',
            'data' => $transactions
        ]);
    }

    /**
     * Upload payment proof for a transaction
     * POST /api/payments/proof
     */
    public function uploadProof(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'pembeli') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'transaction_id' => 'required|exists:transactions,id',
            'proof' => 'required|image|max:2048',
        ]);

        $transaction = Transaction::where('id', $request->transaction_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found or not owned by you'], 404);
        }

        $path = $request->file('proof')->store('payment-proofs', 'public');

        // Update or create payment record
        $payment = $transaction->payment;
        if ($payment) {
            $payment->update([
                'proof_url' => $path,
                'status' => 'pending_verification'
            ]);
        } else {
            $transaction->payment()->create([
                'transaction_id' => $transaction->id,
                'amount' => $transaction->total_price,
                'method' => $transaction->payment_method ?? 'transfer',
                'proof_url' => $path,
                'status' => 'pending_verification'
            ]);
        }

        return response()->json([
            'message' => 'Bukti pembayaran berhasil diupload',
            'proof_url' => asset('storage/' . $path)
        ]);
    }
}

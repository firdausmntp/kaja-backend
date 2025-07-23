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
        $transactions = Transaction::with(['user', 'items.menu', 'payment'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($transactions);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,paid,confirmed,ready,completed,cancelled'
        ]);

        $transaction = Transaction::findOrFail($id);

        $transaction->update([
            'status' => $request->status
        ]);

        return response()->json([
            'message' => 'Status transaksi berhasil diupdate',
            'transaction' => $transaction->load(['user', 'items.menu', 'payment'])
        ]);
    }

    public function show($id)
    {
        $transaction = Transaction::with(['user', 'items.menu', 'payment'])
            ->findOrFail($id);

        return response()->json($transaction);
    }
}

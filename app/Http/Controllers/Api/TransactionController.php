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
        $transactions = Transaction::where('user_id', Auth::id())->get();
        return response()->json($transactions);
    }

    public function store(Request $request)
    {
        $request->validate([
            'total_price' => 'required|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.menu_id' => 'required|exists:menus,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
            'customer_name' => 'nullable|string|max:100',
            'customer_phone' => 'nullable|string|max:20',
            'order_type' => 'nullable|in:dine_in,takeaway,delivery',
        ]);

        $transaction = Transaction::create([
            'user_id' => Auth::id(),
            'total_price' => $request->total_price,
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
        $transaction = Transaction::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

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

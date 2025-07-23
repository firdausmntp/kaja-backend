<?php

namespace App\Http\Controllers\Api;

use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'transaction_id' => 'required|exists:transactions,id',
            'amount' => 'required|numeric|min:0',
            'method' => 'required|string|exists:payment_methods,name',
            'proof' => 'nullable|image|max:2048',
        ]);

        $transaction = Transaction::where('id', $request->transaction_id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // Validate payment method is active
        $paymentMethod = PaymentMethod::where('name', $request->method)
            ->where('is_active', true)
            ->first();

        if (!$paymentMethod) {
            return response()->json(['message' => 'Metode pembayaran tidak tersedia'], 400);
        }

        $proofPath = null;
        if ($request->hasFile('proof')) {
            $proofPath = $request->file('proof')->store('proofs', 'public');
        }

        $payment = Payment::create([
            'transaction_id' => $transaction->id,
            'amount' => $request->amount,
            'method' => $request->method,
            'paid_at' => now(),
            'proof' => $proofPath,
        ]);

        $transaction->update(['status' => 'paid']);

        return response()->json([
            'message' => 'Pembayaran berhasil',
            'payment' => $payment->load('paymentMethod')
        ], 201);
    }

    public function show($id)
    {
        $payment = Payment::with(['transaction', 'paymentMethod'])
            ->where('id', $id)
            ->firstOrFail();

        if ($payment->proof) {
            $payment->proof = asset('storage/' . $payment->proof);
        }

        return response()->json($payment);
    }
}

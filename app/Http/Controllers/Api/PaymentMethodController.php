<?php

namespace App\Http\Controllers\Api;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class PaymentMethodController extends Controller
{
    public function index()
    {
        $paymentMethods = PaymentMethod::where('is_active', true)->get();

        return response()->json($paymentMethods);
    }

    public function store(Request $request)
    {
        // Hanya admin yang bisa menambah metode pembayaran
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Hanya admin yang dapat menambah metode pembayaran'], 403);
        }

        $request->validate([
            'name' => 'required|string|unique:payment_methods',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $paymentMethod = PaymentMethod::create([
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'message' => 'Metode pembayaran berhasil dibuat',
            'payment_method' => $paymentMethod
        ], 201);
    }

    public function show($id)
    {
        $paymentMethod = PaymentMethod::find($id);

        if (!$paymentMethod) {
            return response()->json(['message' => 'Metode pembayaran tidak ditemukan'], 404);
        }

        return response()->json($paymentMethod);
    }

    public function update(Request $request, $id)
    {
        // Hanya admin yang bisa mengupdate metode pembayaran
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Hanya admin yang dapat mengupdate metode pembayaran'], 403);
        }

        $paymentMethod = PaymentMethod::find($id);

        if (!$paymentMethod) {
            return response()->json(['message' => 'Metode pembayaran tidak ditemukan'], 404);
        }

        $request->validate([
            'name' => 'sometimes|required|string|unique:payment_methods,name,' . $id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $paymentMethod->update($request->only(['name', 'description', 'is_active']));

        return response()->json([
            'message' => 'Metode pembayaran berhasil diperbarui',
            'payment_method' => $paymentMethod
        ]);
    }

    public function destroy($id)
    {
        // Hanya admin yang bisa menghapus metode pembayaran
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Hanya admin yang dapat menghapus metode pembayaran'], 403);
        }

        $paymentMethod = PaymentMethod::find($id);

        if (!$paymentMethod) {
            return response()->json(['message' => 'Metode pembayaran tidak ditemukan'], 404);
        }

        $paymentMethod->delete();

        return response()->json(['message' => 'Metode pembayaran berhasil dihapus']);
    }
}

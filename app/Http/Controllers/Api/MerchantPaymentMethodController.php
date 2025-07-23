<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MerchantPaymentMethod;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MerchantPaymentMethodController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // For penjual - show only their own payment methods
        $merchantPaymentMethods = MerchantPaymentMethod::with('paymentMethod')
            ->where('user_id', $user->id)
            ->get();

        return response()->json([
            'message' => 'Payment methods retrieved successfully',
            'data' => $merchantPaymentMethods
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'payment_method_id' => 'required|exists:payment_methods,id',
            'is_active' => 'boolean',
            'details' => 'required|array',
            'details.account_number' => 'required_if:payment_method_id,2,3|string', // untuk bank transfer & e-wallet
            'details.account_name' => 'required_if:payment_method_id,2,3|string',
            'details.bank_name' => 'required_if:payment_method_id,2|string', // khusus bank transfer
            'details.instructions' => 'nullable|string',
        ]);

        $merchantPaymentMethod = MerchantPaymentMethod::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'payment_method_id' => $request->payment_method_id,
            ],
            [
                'is_active' => $request->is_active ?? true,
                'details' => $request->details,
            ]
        );

        return response()->json($merchantPaymentMethod->load('paymentMethod'), 201);
    }

    public function show($id)
    {
        $merchantPaymentMethod = MerchantPaymentMethod::with('paymentMethod')
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        return response()->json($merchantPaymentMethod);
    }

    public function update(Request $request, $id)
    {
        $merchantPaymentMethod = MerchantPaymentMethod::where('user_id', Auth::id())
            ->findOrFail($id);

        $request->validate([
            'is_active' => 'boolean',
            'details' => 'array',
            'details.account_number' => 'required_if:details,!=,null|string',
            'details.account_name' => 'required_if:details,!=,null|string',
            'details.bank_name' => 'nullable|string',
            'details.instructions' => 'nullable|string',
        ]);

        $merchantPaymentMethod->update($request->only(['is_active', 'details']));

        return response()->json($merchantPaymentMethod->load('paymentMethod'));
    }

    public function destroy($id)
    {
        $merchantPaymentMethod = MerchantPaymentMethod::where('user_id', Auth::id())
            ->findOrFail($id);

        $merchantPaymentMethod->delete();

        return response()->json(['message' => 'Payment method configuration deleted successfully']);
    }

    // Endpoint untuk pembeli melihat payment methods yang tersedia dari penjual
    public function getAvailableForMerchant($merchantId)
    {
        try {
            // Validate merchantId
            if (!$merchantId || !is_numeric($merchantId)) {
                return response()->json([
                    'message' => 'Invalid merchant ID',
                    'data' => []
                ], 400);
            }

            $availablePaymentMethods = MerchantPaymentMethod::with('paymentMethod')
                ->where('user_id', $merchantId)
                ->where('is_active', true)
                ->whereHas('paymentMethod', function ($query) {
                    $query->where('is_active', true);
                })
                ->get();

            return response()->json([
                'message' => 'Available payment methods retrieved successfully',
                'data' => $availablePaymentMethods,
                'merchant_id' => $merchantId
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving payment methods',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

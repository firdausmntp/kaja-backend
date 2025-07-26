<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class MerchantController extends Controller
{
    /**
     * Get list of all merchants (sellers)
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $merchants = User::where('role', 'penjual')
            ->select('id', 'name', 'email', 'created_at')
            ->withCount(['menus as total_menus'])
            ->with(['activePaymentMethods:id,user_id,payment_method_id,is_active'])
            ->get()
            ->map(function ($merchant) {
                return [
                    'id' => $merchant->id,
                    'name' => $merchant->name,
                    'email' => $merchant->email,
                    'total_menus' => $merchant->total_menus,
                    'payment_methods_count' => $merchant->activePaymentMethods->count(),
                    'joined_at' => $merchant->created_at->format('Y-m-d'),
                ];
            });

        return response()->json([
            'message' => 'Merchants retrieved successfully',
            'data' => $merchants,
            'total' => $merchants->count()
        ]);
    }

    /**
     * Get specific merchant details with their menus
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $merchant = User::where('role', 'penjual')
            ->where('id', $id)
            ->select('id', 'name', 'email', 'created_at')
            ->with([
                'menus' => function ($query) {
                    $query->select('id', 'user_id', 'name', 'description', 'price', 'stock', 'image_url', 'category_id')
                        ->with('category:id,name');
                },
                'activePaymentMethods.paymentMethod:id,name'
            ])
            ->first();

        if (!$merchant) {
            return response()->json([
                'message' => 'Merchant tidak ditemukan'
            ], 404);
        }

        // Transform menus to include full image URL
        $merchant->menus->transform(function ($menu) {
            $menu->image_url = $menu->image_url ? asset('public_storage/' . $menu->image_url) : null;
            return $menu;
        });

        $merchantData = [
            'id' => $merchant->id,
            'name' => $merchant->name,
            'email' => $merchant->email,
            'joined_at' => $merchant->created_at->format('Y-m-d H:i:s'),
            'total_menus' => $merchant->menus->count(),
            'menus' => $merchant->menus,
            'payment_methods' => $merchant->activePaymentMethods->map(function ($method) {
                return [
                    'id' => $method->paymentMethod->id,
                    'name' => $method->paymentMethod->name,
                ];
            })
        ];

        return response()->json([
            'message' => 'Merchant details retrieved successfully',
            'data' => $merchantData
        ]);
    }

    /**
     * Get menus by specific merchant
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function menus($id)
    {
        $merchant = User::where('role', 'penjual')
            ->where('id', $id)
            ->first();

        if (!$merchant) {
            return response()->json([
                'message' => 'Merchant tidak ditemukan'
            ], 404);
        }

        $menus = $merchant->menus()
            ->with(['category:id,name'])
            ->select('id', 'name', 'description', 'price', 'stock', 'image_url', 'category_id', 'user_id')
            ->get()
            ->map(function ($menu) {
                $menu->image_url = $menu->image_url ? asset('public_storage/' . $menu->image_url) : null;
                return $menu;
            });

        return response()->json([
            'message' => 'Merchant menus retrieved successfully',
            'merchant' => [
                'id' => $merchant->id,
                'name' => $merchant->name,
            ],
            'data' => $menus,
            'total' => $menus->count()
        ]);
    }
}

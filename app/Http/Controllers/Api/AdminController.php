<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    /**
     * Get all users in the system
     */
    public function listUsers(Request $request): JsonResponse
    {
        try {
            // Check if user is admin
            $user = Auth::user();
            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $perPage = $request->get('per_page', 15);
            $role = $request->get('role');
            $search = $request->get('search');

            $query = User::query();

            // Filter by role
            if ($role && in_array($role, ['admin', 'penjual', 'pembeli'])) {
                $query->where('role', $role);
            }

            // Search by name or email
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $users = $query->latest()->paginate($perPage);

            return response()->json([
                'message' => 'Users retrieved successfully',
                'data' => $users->items(),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all transactions in the system
     */
    public function listTransactions(Request $request): JsonResponse
    {
        try {
            // Check if user is admin
            $user = Auth::user();
            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $perPage = $request->get('per_page', 15);
            $status = $request->get('status');
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');
            $search = $request->get('search');

            $query = Transaction::with([
                'customer:id,name,email',
                'merchant:id,name,email',
                'items.menu:id,name,price',
                'payment:id,transaction_id,amount,method,status'
            ]);

            // Filter by status
            if ($status && in_array($status, ['pending', 'paid', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled'])) {
                $query->where('status', $status);
            }

            // Filter by date range
            if ($dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            }

            // Search by customer info
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%");
                });
            }

            $transactions = $query->latest()->paginate($perPage);

            // Add summary statistics
            $stats = [
                'total_transactions' => Transaction::count(),
                'total_revenue' => Transaction::where('status', '!=', 'cancelled')->sum('total_price'),
                'pending_transactions' => Transaction::where('status', 'pending')->count(),
                'completed_transactions' => Transaction::where('status', 'completed')->count(),
                'today_transactions' => Transaction::whereDate('created_at', today())->count(),
                'today_revenue' => Transaction::whereDate('created_at', today())
                    ->where('status', '!=', 'cancelled')
                    ->sum('total_price'),
            ];

            return response()->json([
                'message' => 'Transactions retrieved successfully',
                'data' => $transactions->items(),
                'meta' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                ],
                'statistics' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system dashboard statistics
     */
    public function getDashboardStats(): JsonResponse
    {
        try {
            // Check if user is admin
            $user = Auth::user();
            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $stats = [
                'users' => [
                    'total' => User::count(),
                    'admins' => User::where('role', 'admin')->count(),
                    'sellers' => User::where('role', 'penjual')->count(),
                    'customers' => User::where('role', 'pembeli')->count(),
                    'new_today' => User::whereDate('created_at', today())->count(),
                ],
                'transactions' => [
                    'total' => Transaction::count(),
                    'pending' => Transaction::where('status', 'pending')->count(),
                    'completed' => Transaction::where('status', 'completed')->count(),
                    'cancelled' => Transaction::where('status', 'cancelled')->count(),
                    'today' => Transaction::whereDate('created_at', today())->count(),
                ],
                'revenue' => [
                    'total' => Transaction::where('status', '!=', 'cancelled')->sum('total_price'),
                    'today' => Transaction::whereDate('created_at', today())
                        ->where('status', '!=', 'cancelled')
                        ->sum('total_price'),
                    'average_order' => Transaction::where('status', '!=', 'cancelled')->avg('total_price'),
                ],
                'menus' => [
                    'total' => \App\Models\Menu::count(),
                    'available' => \App\Models\Menu::where('stock', '>', 0)->count(),
                    'out_of_stock' => \App\Models\Menu::where('stock', '<=', 0)->count(),
                ]
            ];

            return response()->json([
                'message' => 'Dashboard statistics retrieved successfully',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve dashboard statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user details with relationships
     */
    public function getUserDetails($id): JsonResponse
    {
        try {
            // Check if user is admin
            $user = Auth::user();
            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $userDetail = User::with(['transactions.items.menu', 'menus.category'])
                ->withCount(['transactions', 'menus'])
                ->find($id);

            if (!$userDetail) {
                return response()->json([
                    'message' => 'User not found'
                ], 404);
            }

            return response()->json([
                'message' => 'User details retrieved successfully',
                'data' => $userDetail
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve user details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transaction details with all relationships
     */
    public function getTransactionDetails($id): JsonResponse
    {
        try {
            // Check if user is admin
            $user = Auth::user();
            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $transaction = Transaction::with([
                'customer:id,name,email,role',
                'merchant:id,name,email,role',
                'items.menu:id,name,price,image_url',
                'payment',
                'chats.sender:id,name,role'
            ])->find($id);

            if (!$transaction) {
                return response()->json([
                    'message' => 'Transaction not found'
                ], 404);
            }

            return response()->json([
                'message' => 'Transaction details retrieved successfully',
                'data' => $transaction
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve transaction details',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\MerchantPaymentMethodController;
use App\Http\Controllers\Api\PembeliController;
use App\Http\Controllers\Api\PenjualController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\CartController;

// === PUBLIC ===
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/payment-methods', [PaymentMethodController::class, 'index']);
Route::get('/merchants/{merchantId}/payment-methods', [MerchantPaymentMethodController::class, 'getAvailableForMerchant']);
Route::get('/menus', [MenuController::class, 'index']);
Route::get('/menus/{id}', [MenuController::class, 'show']);

// === PROTECTED ===
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/change-password', [AuthController::class, 'changePassword']);
    Route::delete('/delete-account', [AuthController::class, 'deleteAccount']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // === CHAT SYSTEM ===
    Route::get('/chats', [ChatController::class, 'chatList']);
    Route::get('/chats/unread-count', [ChatController::class, 'unreadCount']);
    Route::get('/transactions/{transaction}/chats', [ChatController::class, 'index']);
    Route::post('/transactions/{transaction}/chats', [ChatController::class, 'store']);
    Route::delete('/chats/{chat}', [ChatController::class, 'destroy']);

    // === PENJUAL ===
    Route::middleware('role:penjual')->group(function () {
        Route::get('/penjual/menus', [MenuController::class, 'myMenus']); // Get own menus
        Route::post('/menus', [MenuController::class, 'store']);
        Route::put('/menus/{id}', [MenuController::class, 'update']);
        Route::delete('/menus/{id}', [MenuController::class, 'destroy']);
        Route::get('/penjual/transactions', [PenjualController::class, 'transactions']);
        Route::get('/penjual/transactions/{id}', [PenjualController::class, 'show']);
        Route::put('/penjual/transactions/{id}/status', [PenjualController::class, 'updateStatus']);

        // Merchant Payment Methods Management
        Route::apiResource('merchant-payment-methods', MerchantPaymentMethodController::class);
    });    // === PEMBELI ===
    Route::middleware('role:pembeli')->group(function () {
        // Cart Management
        Route::get('/cart', [CartController::class, 'show']); // Get all carts
        Route::get('/cart/{merchantId}', [CartController::class, 'show']); // Get cart for specific merchant
        Route::post('/cart/add', [CartController::class, 'addItem']); // Add item to cart
        Route::put('/cart/items/{itemId}', [CartController::class, 'updateItem']); // Update cart item
        Route::delete('/cart/items/{itemId}', [CartController::class, 'removeItem']); // Remove cart item
        Route::delete('/cart', [CartController::class, 'clear']); // Clear all carts
        Route::delete('/cart/{merchantId}', [CartController::class, 'clear']); // Clear specific cart
        Route::post('/cart/{merchantId}/checkout', [CartController::class, 'checkout']); // Convert cart to transaction

        // Existing pembeli routes
        Route::post('/transactions', [TransactionController::class, 'store']);
        Route::get('/transactions/{id}', [TransactionController::class, 'show']);
        Route::delete('/transactions/{id}', [TransactionController::class, 'destroy']);
        Route::get('/pembeli/transactions', [PembeliController::class, 'myTransactions']);
        Route::post('/payments', [PaymentController::class, 'store']);
        Route::post('/payments/proof', [PembeliController::class, 'uploadProof']);

        // Allow pembeli to view merchant payment methods
        Route::get('/merchant-payment-methods', [MerchantPaymentMethodController::class, 'index']);
        Route::get('/merchant-payment-methods/{id}', [MerchantPaymentMethodController::class, 'show']);
    });

    // === ADMIN ===
    Route::middleware('role:admin')->group(function () {
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

        // Payment Methods Management
        Route::post('/payment-methods', [PaymentMethodController::class, 'store']);
        Route::get('/payment-methods/{id}', [PaymentMethodController::class, 'show']);
        Route::put('/payment-methods/{id}', [PaymentMethodController::class, 'update']);
        Route::delete('/payment-methods/{id}', [PaymentMethodController::class, 'destroy']);

        // Admin API Routes
        Route::get('/admin/users', [AdminController::class, 'listUsers']);
        Route::get('/admin/transactions', [AdminController::class, 'listTransactions']);
        Route::get('/admin/dashboard', [AdminController::class, 'getDashboardStats']);
        Route::get('/admin/users/{id}', [AdminController::class, 'getUserDetails']);
        Route::get('/admin/transactions/{id}', [AdminController::class, 'getTransactionDetails']);
    });
});

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            'items.menu:id,name,price,image_url,user_id',
            'items.menu.category:id,name',
            'items.menu.merchant:id,name', // Load merchant relationship untuk menu
            'merchant:id,name,email', // Load merchant relationship untuk transaction
            'payment:id,transaction_id,amount,method,paid_at,proof,status,notes',
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
                        $item->menu->image_url = asset('public_storage/' . $item->menu->image_url);
                    }
                });

                // Add payment proof URL if exists
                if ($transaction->payment && $transaction->payment->proof) {
                    $transaction->payment->proof_url = asset('public_storage/' . $transaction->payment->proof);
                    // Remove raw proof path for cleaner response
                    unset($transaction->payment->proof);
                }

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
            'proof' => 'required_without:proof_url|image|max:2048',
            'proof_url' => 'required_without:proof|url',
        ]);

        $transaction = Transaction::where('id', $request->transaction_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found or not owned by you'], 404);
        }

        // Handle file upload or URL download
        if ($request->hasFile('proof')) {
            // Upload from file
            $path = $request->file('proof')->store('payment-proofs', 'public');
        } elseif ($request->proof_url) {
            // Download from URL
            $path = $this->downloadAndSaveProof($request->proof_url);
            if (!$path) {
                return response()->json([
                    'message' => 'Gagal mengunduh bukti pembayaran dari URL',
                    'errors' => ['proof_url' => ['URL tidak valid atau file tidak dapat diunduh']]
                ], 422);
            }
        } else {
            return response()->json([
                'message' => 'Bukti pembayaran diperlukan',
                'errors' => ['proof' => ['Sertakan file atau URL bukti pembayaran']]
            ], 422);
        }

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
            'proof_url' => asset('public_storage/' . $path)
        ]);
    }

    /**
     * Mark transaction as paid (without proof)
     * POST /api/pembeli/transactions/{id}/mark-as-paid
     */
    public function markAsPaid(Request $request, $id)
    {
        $user = Auth::user();

        if ($user->role !== 'pembeli') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'payment_note' => 'nullable|string|max:255',
        ]);

        $transaction = Transaction::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found or not owned by you'], 404);
        }

        // Check if already marked as paid
        if ($transaction->status === 'paid') {
            return response()->json(['message' => 'Transaction sudah ditandai sebagai paid'], 400);
        }

        // Get old status before updating
        $oldStatus = $transaction->status;

        // Update transaction status
        $transaction->update(['status' => 'paid']);

        // Handle stock management with loaded items
        $transaction->load('items.menu');
        $transaction->handleStockManagement('paid', $oldStatus);

        // Create or update payment record
        $payment = $transaction->payment;
        if ($payment) {
            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'notes' => $request->payment_note
            ]);
        } else {
            $transaction->payment()->create([
                'transaction_id' => $transaction->id,
                'amount' => $transaction->total_price,
                'method' => $transaction->payment_method ?? 'cash',
                'status' => 'paid',
                'paid_at' => now(),
                'notes' => $request->payment_note
            ]);
        }

        return response()->json([
            'message' => 'Transaksi berhasil ditandai sebagai sudah dibayar',
            'data' => $transaction->load(['payment', 'items.menu'])
        ]);
    }

    /**
     * Download image from URL and save to storage
     */
    private function downloadAndSaveProof($proofUrl)
    {
        try {
            // Validate URL format
            if (!filter_var($proofUrl, FILTER_VALIDATE_URL)) {
                return null;
            }

            // Download image with timeout and size limit
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; KAJA-API/1.0)',
                ])
                ->get($proofUrl);

            // Check if request was successful
            if (!$response->successful()) {
                return null;
            }

            // Check content type
            $contentType = $response->header('Content-Type');
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

            if (!in_array($contentType, $allowedTypes)) {
                return null;
            }

            // Check file size (max 2MB = 2,097,152 bytes)
            $contentLength = $response->header('Content-Length');
            if ($contentLength && $contentLength > 2097152) {
                return null;
            }

            // Get file extension from content type
            $extension = match ($contentType) {
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                default => 'jpg'
            };

            // Generate unique filename
            $filename = 'proof_' . time() . '_' . Str::random(10) . '.' . $extension;
            $filepath = 'payment-proofs/' . $filename;

            // Save to storage (try symlink method first)
            $saved = Storage::disk('public')->put($filepath, $response->body());

            if ($saved) {
                // For shared hosting: also copy to public/storage directly
                $this->ensurePublicStorageExists($filepath, $response->body());
                return $filepath;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Ensure file exists in public/public_storage for shared hosting
     */
    private function ensurePublicStorageExists($relativePath, $content)
    {
        try {
            $publicPath = public_path('public_storage/' . $relativePath);
            $directory = dirname($publicPath);

            // Create directory if it doesn't exist
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            // Write file directly to public/public_storage
            file_put_contents($publicPath, $content);
        } catch (\Exception $e) {
            // Silent fail - symlink method should work
        }
    }
}

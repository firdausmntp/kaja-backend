<?php

namespace App\Http\Controllers\Api;

use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'transaction_id' => 'required|exists:transactions,id',
            'amount' => 'required|numeric|min:0',
            'method' => 'required|string|exists:payment_methods,name',
            'proof' => 'nullable|image|max:2048',
            'proof_url' => 'nullable|url',
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
        } elseif ($request->proof_url) {
            $proofPath = $this->downloadAndSaveProof($request->proof_url);
            if (!$proofPath) {
                return response()->json([
                    'message' => 'Gagal mengunduh bukti pembayaran dari URL',
                    'errors' => ['proof_url' => ['URL tidak valid atau file tidak dapat diunduh']]
                ], 422);
            }
        }

        $payment = Payment::create([
            'transaction_id' => $transaction->id,
            'amount' => $request->amount,
            'method' => $request->method,
            'paid_at' => now(),
            'proof' => $proofPath,
        ]);

        // Get old status before updating
        $oldStatus = $transaction->status;

        $transaction->update(['status' => 'paid']);

        // Handle stock management with loaded items
        $transaction->load('items.menu');
        $transaction->handleStockManagement('paid', $oldStatus);

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
            $payment->proof = asset('public_storage/' . $payment->proof);
        }

        return response()->json($payment);
    }

    /**
     * Download proof image from URL and save to storage
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
            $filepath = 'proofs/' . $filename;

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
     * Ensure file exists in public/storage for shared hosting
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

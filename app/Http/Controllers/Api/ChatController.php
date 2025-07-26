<?php

namespace App\Http\Controllers\Api;

use App\Models\Chat;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    /**
     * 💬 Get chat messages for a transaction
     * GET /api/transactions/{transaction}/chats
     */
    public function index(Request $request, Transaction $transaction)
    {
        // Validate user access to this transaction
        $user = $request->user();

        // Check if user is the customer (pembeli) - cast to int for comparison
        $isCustomer = (int)$transaction->user_id === (int)$user->id;

        // Check if user is the merchant (penjual) - cast to int for comparison
        $isMerchant = false;
        if ($transaction->cashier_id && (int)$transaction->cashier_id === (int)$user->id) {
            $isMerchant = true;
        } else {
            // If cashier_id is null, check if user owns any menu in transaction items
            $merchantCheck = $transaction->items()
                ->whereHas('menu', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->exists();
            $isMerchant = $merchantCheck;
        }

        if (!$isCustomer && !$isMerchant) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke chat ini.'
            ], 403);
        }

        // Get chat messages with sender info
        $chats = Chat::with('sender:id,name,role')
            ->where('transaction_id', $transaction->id)
            ->orderBy('created_at', 'asc')
            ->paginate(50);

        // Update attachment URLs to use public_storage
        $chats->getCollection()->transform(function ($chat) {
            if ($chat->attachment_url) {
                // Convert old storage paths to new public_storage paths
                if (strpos($chat->attachment_url, '/storage/') !== false) {
                    $relativePath = str_replace('/storage/', '', $chat->attachment_url);
                    $chat->attachment_url = asset('public_storage/' . $relativePath);
                } elseif (!str_contains($chat->attachment_url, 'http')) {
                    // If it's just a relative path without domain
                    $chat->attachment_url = asset('public_storage/' . $chat->attachment_url);
                }
            }
            return $chat;
        });

        // Mark messages as read for current user
        Chat::where('transaction_id', $transaction->id)
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);

        return response()->json([
            'transaction' => [
                'id' => $transaction->id,
                'status' => $transaction->status,
                'total_amount' => $transaction->total_amount,
                'pembeli' => $transaction->user->name,
                'penjual' => $transaction->merchant->name ?? 'Admin'
            ],
            'chats' => $chats,
            'unread_count' => 0 // Since we mark as read
        ]);
    }

    /**
     * 📤 Send a chat message
     * POST /api/transactions/{transaction}/chats
     */
    public function store(Request $request, Transaction $transaction)
    {
        $user = $request->user();

        // Check if user is the customer (pembeli) - cast to int for comparison
        $isCustomer = (int)$transaction->user_id === (int)$user->id;

        // Check if user is the merchant (penjual) - cast to int for comparison
        $isMerchant = false;
        if ($transaction->cashier_id && (int)$transaction->cashier_id === (int)$user->id) {
            $isMerchant = true;
        } else {
            // If cashier_id is null, check if user owns any menu in transaction items
            $merchantCheck = $transaction->items()
                ->whereHas('menu', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->exists();
            $isMerchant = $merchantCheck;
        }

        if (!$isCustomer && !$isMerchant) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke chat ini.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'message' => 'required_without:attachment|string|max:1000',
            'attachment' => 'required_without:message|file|max:10240', // 10MB max
            'message_type' => 'sometimes|in:text,image,document,system'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Data tidak valid.',
                'errors' => $validator->errors()
            ], 422);
        }

        $chatData = [
            'transaction_id' => $transaction->id,
            'sender_id' => $user->id,
            'message' => $request->message,
            'message_type' => $request->message_type ?? 'text'
        ];

        // Handle file attachment
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('chat-attachments', $fileName, 'public');

            // Use asset for consistent URL generation
            $chatData['attachment_url'] = asset('public_storage/' . $filePath);
            $chatData['attachment_type'] = $this->getAttachmentType($file->getClientOriginalExtension());
            $chatData['message_type'] = $chatData['attachment_type'];

            // For shared hosting: also copy to public/public_storage directly
            $this->ensurePublicStorageExists($filePath, file_get_contents($file->getRealPath()));
        }

        $chat = Chat::create($chatData);
        $chat->load('sender:id,name,role');

        // TODO: Broadcast to WebSocket channel
        // broadcast(new ChatMessageSent($chat))->toOthers();

        return response()->json([
            'message' => 'Pesan berhasil dikirim.',
            'chat' => $chat
        ], 201);
    }

    /**
     * 📊 Get unread message count for user
     * GET /api/chats/unread-count
     */
    public function unreadCount(Request $request)
    {
        $user = $request->user();

        // Get transactions where user is involved
        $transactionIds = Transaction::where('user_id', $user->id)
            ->orWhere('cashier_id', $user->id)
            ->pluck('id');

        $unreadCount = Chat::whereIn('transaction_id', $transactionIds)
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'unread_count' => $unreadCount
        ]);
    }

    /**
     * 📝 Get user's chat list (all transactions with latest message)
     * GET /api/chats
     */
    public function chatList(Request $request)
    {
        $user = $request->user();

        $transactions = Transaction::with(['user:id,name', 'merchant:id,name'])
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('cashier_id', $user->id);
            })
            ->whereHas('chats')
            ->get();

        $chatList = $transactions->map(function ($transaction) use ($user) {
            $latestChat = $transaction->chats()->latest()->first();
            $unreadCount = $transaction->chats()
                ->where('sender_id', '!=', $user->id)
                ->where('is_read', false)
                ->count();

            $otherUser = $transaction->user_id === $user->id
                ? $transaction->merchant
                : $transaction->user;

            return [
                'transaction_id' => $transaction->id,
                'transaction_status' => $transaction->status,
                'other_user' => [
                    'id' => $otherUser->id ?? null,
                    'name' => $otherUser->name ?? 'Admin',
                    'role' => $otherUser->role ?? 'admin'
                ],
                'latest_message' => $latestChat ? [
                    'message' => $latestChat->message,
                    'message_type' => $latestChat->message_type,
                    'sender_name' => $latestChat->sender->name,
                    'is_from_me' => $latestChat->sender_id === $user->id,
                    'created_at' => $latestChat->created_at,
                    'formatted_time' => $latestChat->formatted_time
                ] : null,
                'unread_count' => $unreadCount
            ];
        });

        return response()->json([
            'chats' => $chatList->sortByDesc('latest_message.created_at')->values()
        ]);
    }

    /**
     * 🗑️ Delete a chat message (only sender can delete)
     * DELETE /api/chats/{chat}
     */
    public function destroy(Chat $chat, Request $request)
    {
        $user = $request->user();

        if ($chat->sender_id !== $user->id) {
            return response()->json([
                'message' => 'Anda hanya bisa menghapus pesan sendiri.'
            ], 403);
        }

        // Delete attachment file if exists
        if ($chat->attachment_url) {
            // Handle both old /storage/ and new /public_storage/ paths
            $path = str_replace(['/storage/', '/public_storage/'], '', $chat->attachment_url);
            $path = str_replace(url('/'), '', $path); // Remove domain if present
            Storage::disk('public')->delete($path);

            // Also delete from public/public_storage if exists
            $publicStoragePath = public_path('public_storage/' . $path);
            if (file_exists($publicStoragePath)) {
                unlink($publicStoragePath);
            }
        }

        $chat->delete();

        return response()->json([
            'message' => 'Pesan berhasil dihapus.'
        ]);
    }

    /**
     * 🔍 Get attachment type from file extension
     */
    private function getAttachmentType($extension)
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $documentExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];

        $extension = strtolower($extension);

        if (in_array($extension, $imageExtensions)) {
            return 'image';
        } elseif (in_array($extension, $documentExtensions)) {
            return 'document';
        }

        return 'document'; // default
    }

    /**
     * Ensure file exists in public/public_storage for shared hosting
     * 
     * @param string $filepath
     * @param string $content
     */
    private function ensurePublicStorageExists($filepath, $content)
    {
        try {
            $publicStoragePath = public_path('public_storage/' . $filepath);
            $publicDir = dirname($publicStoragePath);

            // Create directory if it doesn't exist
            if (!is_dir($publicDir)) {
                mkdir($publicDir, 0755, true);
            }

            // Copy file to public/public_storage
            file_put_contents($publicStoragePath, $content);

            \Illuminate\Support\Facades\Log::info('File copied to public storage: ' . $publicStoragePath);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to copy file to public storage: ' . $e->getMessage());
        }
    }
}

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

        if ($transaction->user_id !== $user->id && $transaction->cashier_id !== $user->id) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke chat ini.'
            ], 403);
        }

        // Get chat messages with sender info
        $chats = Chat::with('sender:id,name,role')
            ->where('transaction_id', $transaction->id)
            ->orderBy('created_at', 'asc')
            ->paginate(50);

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

        // Validate user access
        if ($transaction->user_id !== $user->id && $transaction->cashier_id !== $user->id) {
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

            $chatData['attachment_url'] = Storage::url($filePath);
            $chatData['attachment_type'] = $this->getAttachmentType($file->getClientOriginalExtension());
            $chatData['message_type'] = $chatData['attachment_type'];
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
            $path = str_replace('/storage/', '', $chat->attachment_url);
            Storage::disk('public')->delete($path);
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
}

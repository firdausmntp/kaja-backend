<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'sender_id',
        'message',
        'attachment_url',
        'attachment_type',
        'message_type',
        'read_at',
        'is_read'
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'is_read' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // Check if message is from buyer
    public function isFromBuyer()
    {
        return $this->sender->role === 'pembeli';
    }

    // Check if message is from seller
    public function isFromSeller()
    {
        return $this->sender->role === 'penjual';
    }

    // Mark message as read
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now()
        ]);
    }

    // Get attachment file name
    public function getAttachmentFileName()
    {
        if (!$this->attachment_url) return null;
        return basename($this->attachment_url);
    }

    // Get formatted message time
    public function getFormattedTimeAttribute()
    {
        return $this->created_at->format('H:i');
    }

    // Get formatted message date
    public function getFormattedDateAttribute()
    {
        return $this->created_at->format('d M Y');
    }
}

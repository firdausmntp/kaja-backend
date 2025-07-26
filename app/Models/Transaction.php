<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'cashier_id',
        'total_price',
        'payment_method',
        'status',
        'notes',
        'customer_name',
        'customer_phone',
        'order_type',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function merchant()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function items()
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function penjual()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function chats()
    {
        return $this->hasMany(Chat::class);
    }

    /**
     * Handle stock management when transaction status changes
     * 
     * @param string $newStatus
     * @param string $oldStatus
     */
    public function handleStockManagement($newStatus, $oldStatus = null)
    {
        // Reduce stock when status changes to 'paid'
        if ($newStatus === 'paid' && $oldStatus !== 'paid') {
            $this->reduceStock();
        }

        // Restore stock when status changes to 'cancelled' AND was previously paid
        if ($newStatus === 'cancelled' && $oldStatus === 'paid') {
            $this->restoreStock();
        }

        // Restore stock when changing to cancelled from any non-cancelled status
        // but only if it wasn't paid before (to avoid double restoration)
        if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled' && $oldStatus !== 'paid') {
            // Only restore if the transaction was actually paid at some point
            // For now, we'll skip this to avoid complexity
            // In production, you might want to track payment history
        }
    }

    /**
     * Reduce stock for all items in transaction
     */
    private function reduceStock()
    {
        foreach ($this->items as $item) {
            if ($item->menu) {
                $item->menu->reduceStock($item->quantity);
            }
        }
    }

    /**
     * Restore stock for all items in transaction
     */
    private function restoreStock()
    {
        foreach ($this->items as $item) {
            if ($item->menu) {
                $item->menu->restoreStock($item->quantity);
            }
        }
    }
}

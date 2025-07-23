<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'merchant_id',
        'status',
        'total_amount',
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    // Helper methods
    public function recalculateTotal()
    {
        $this->total_amount = $this->cartItems()->sum('total_price');
        $this->save();

        return $this->total_amount;
    }

    public function getTotalItemsAttribute()
    {
        return $this->cartItems()->sum('quantity');
    }

    public function addItem($menuId, $quantity, $unitPrice, $notes = null)
    {
        $existingItem = $this->cartItems()->where('menu_id', $menuId)->first();

        if ($existingItem) {
            $existingItem->quantity += $quantity;
            $existingItem->notes = $notes;
            $existingItem->save();
            return $existingItem;
        }

        return $this->cartItems()->create([
            'menu_id' => $menuId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'notes' => $notes,
        ]);
    }

    public function removeItem($cartItemId)
    {
        $item = $this->cartItems()->find($cartItemId);
        if ($item) {
            $item->delete();
            return true;
        }
        return false;
    }

    public function clearCart()
    {
        $this->cartItems()->delete();
        $this->recalculateTotal();
    }

    // Query Scopes
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForMerchant($query, $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }
}

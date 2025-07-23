<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'menu_id',
        'quantity',
        'unit_price',
        'total_price',
        'notes',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'quantity' => 'integer',
    ];

    // Relationships
    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    // Helper methods
    public function updateTotalPrice()
    {
        $this->total_price = $this->quantity * $this->unit_price;
        $this->save();

        // Recalculate cart total
        if ($this->cart) {
            $this->cart->recalculateTotal();
        }

        return $this->total_price;
    }

    // Calculate total price
    public function calculateTotalPrice()
    {
        return $this->quantity * $this->unit_price;
    }

    // Events
    protected static function booted()
    {
        static::creating(function ($cartItem) {
            $cartItem->total_price = $cartItem->calculateTotalPrice();
        });

        static::updating(function ($cartItem) {
            if ($cartItem->isDirty(['quantity', 'unit_price'])) {
                $cartItem->total_price = $cartItem->calculateTotalPrice();
            }
        });

        static::saved(function ($cartItem) {
            if ($cartItem->cart) {
                $cartItem->cart->recalculateTotal();
            }
        });

        static::deleted(function ($cartItem) {
            // Load cart if not loaded
            $cart = $cartItem->cart ?? Cart::find($cartItem->cart_id);
            if ($cart) {
                $cart->recalculateTotal();
            }
        });
    }
}

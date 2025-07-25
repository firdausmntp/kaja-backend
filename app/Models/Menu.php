<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Menu extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'stock',
        'image_url',
        'category_id',
        'user_id', // untuk merchant/penjual yang membuat menu
    ];

    protected $appends = [
        'is_available',
        'merchant_name'
    ];

    public function transactionItems()
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function merchant()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Helper method untuk check availability
    public function getIsAvailableAttribute()
    {
        return $this->stock > 0;
    }

    // Helper method untuk get merchant name
    public function getMerchantNameAttribute()
    {
        return $this->merchant ? $this->merchant->name : 'Unknown Merchant';
    }

    /**
     * Reduce stock when order is paid
     * 
     * @param int $quantity
     * @return bool
     */
    public function reduceStock($quantity)
    {
        if ($this->stock >= $quantity) {
            $this->decrement('stock', $quantity);
            return true;
        }
        return false;
    }

    /**
     * Restore stock when order is cancelled
     * 
     * @param int $quantity
     * @return bool
     */
    public function restoreStock($quantity)
    {
        $this->increment('stock', $quantity);
        return true;
    }
}

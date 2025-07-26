<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role', // tambahkan ini
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed', // tetap pakai hashed
            'is_active' => 'boolean',
        ];
    }

    public function merchantPaymentMethods()
    {
        return $this->hasMany(MerchantPaymentMethod::class);
    }

    public function activePaymentMethods()
    {
        return $this->merchantPaymentMethods()->where('is_active', true)->with('paymentMethod');
    }

    // Menu relationships
    public function menus()
    {
        return $this->hasMany(Menu::class);
    }

    // Cart relationships
    public function carts()
    {
        return $this->hasMany(Cart::class);
    }

    public function activeCarts()
    {
        return $this->carts()->where('status', 'active');
    }

    public function cartForMerchant($merchantId)
    {
        return $this->carts()->where('merchant_id', $merchantId)->where('status', 'active')->first();
    }

    // Transaction relationships
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function merchantTransactions()
    {
        return $this->hasMany(Transaction::class, 'cashier_id');
    }
}

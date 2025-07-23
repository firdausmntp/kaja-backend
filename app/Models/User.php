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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed', // tetap pakai hashed
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
}

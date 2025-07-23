<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@mail.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'Penjual',
            'email' => 'penjual@mail.com',
            'password' => Hash::make('password'),
            'role' => 'penjual',
        ]);

        User::create([
            'name' => 'Pembeli',
            'email' => 'pembeli@mail.com',
            'password' => Hash::make('password'),
            'role' => 'pembeli',
        ]);
    }
}

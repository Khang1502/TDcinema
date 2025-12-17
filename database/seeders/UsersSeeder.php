<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\MembershipLevel;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        User::truncate();

        $basicLevel = MembershipLevel::where('name', 'Thành viên mới')->first();

        // Admin
        User::create([
            'name'               => 'Admin Name',
            'email'              => 'admin@example.com',
            'password'           => Hash::make('123456'),
            'role'               => 'admin',
            'membership_level_id'=> $basicLevel?->id,
            'status'             => 'active',
            'is_active'          => true,
        ]);

        // Manager
        User::create([
            'name'               => 'Manager User',
            'email'              => 'manager@example.com',
            'password'           => Hash::make('123456'),
            'role'               => 'manager',
            'membership_level_id'=> $basicLevel?->id,
            'status'             => 'active',
            'is_active'          => true,
        ]);

        // Staff
        User::create([
            'name'               => 'Staff User',
            'email'              => 'staff@example.com',
            'password'           => Hash::make('123456'),
            'role'               => 'staff',
            'membership_level_id'=> $basicLevel?->id,
            'status'             => 'active',
            'is_active'          => true,
        ]);

        // Khách hàng demo
        User::create([
            'name'               => 'Nguyễn Văn A',
            'email'              => 'customer@example.com',
            'password'           => Hash::make('123456'),
            'role'               => 'customer',
            'membership_level_id'=> $basicLevel?->id,
            'status'             => 'active',
            'is_active'          => true,
        ]);
    }
}

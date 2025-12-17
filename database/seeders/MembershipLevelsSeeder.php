<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MembershipLevel;

class MembershipLevelsSeeder extends Seeder
{
    public function run(): void
    {
        MembershipLevel::truncate();

        MembershipLevel::insert([
            [
                'name'            => 'Thành viên mới',
                'min_points'      => 0,
                'discount_percent'=> 0,
            ],
            [
                'name'            => 'Thành viên bạc',
                'min_points'      => 1000,
                'discount_percent'=> 5,
            ],
            [
                'name'            => 'Thành viên vàng',
                'min_points'      => 3000,
                'discount_percent'=> 10,
            ],
        ]);
    }
}


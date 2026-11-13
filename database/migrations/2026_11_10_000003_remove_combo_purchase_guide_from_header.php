<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        foreach (DB::table('header_settings')->select('id', 'menus')->get() as $setting) {
            $menus = json_decode($setting->menus, true);
            if (! is_array($menus) || ! is_array($menus['shop']['children'] ?? null)) {
                continue;
            }

            $children = $menus['shop']['children'];
            $menus['shop']['children'] = array_values(array_filter($children,
                fn ($child) => ! is_array($child) || ($child['label'] ?? null) !== 'Hướng dẫn mua combo'
            ));

            if (count($menus['shop']['children']) !== count($children)) {
                DB::table('header_settings')->where('id', $setting->id)->update([
                    'menus' => json_encode($menus, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Removed menu entries cannot be restored without overwriting later edits.
    }
};

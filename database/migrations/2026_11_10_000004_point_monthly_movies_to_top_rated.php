<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        foreach (DB::table('header_settings')->select('id', 'menus')->get() as $setting) {
            $menus = json_decode($setting->menus, true);
            if (! is_array($menus['corner']['children'] ?? null)) {
                continue;
            }

            $changed = false;
            foreach ($menus['corner']['children'] as &$child) {
                if (($child['label'] ?? null) === 'Phim hay tháng'
                    && ($child['url'] ?? null) === '/movies?status=now_showing') {
                    $child['url'] = '/movies?status=top_rated';
                    $child['description'] = 'Phim được xếp theo điểm đánh giá';
                    $changed = true;
                }
            }
            unset($child);

            if ($changed) {
                DB::table('header_settings')->where('id', $setting->id)->update([
                    'menus' => json_encode($menus, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Keep any menu edits made after this migration.
    }
};

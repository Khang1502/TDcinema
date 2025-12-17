<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Loại bỏ bản ghi trùng tên trước khi tạo unique
        $dups = DB::table('cinemas')
            ->select('name', DB::raw('COUNT(*) as total'))
            ->groupBy('name')
            ->having('total', '>', 1)
            ->get();

        foreach ($dups as $dup) {
            $ids = DB::table('cinemas')
                ->where('name', $dup->name)
                ->orderBy('id')
                ->pluck('id')
                ->toArray();

            // Giữ id nhỏ nhất, xóa các id còn lại
            array_shift($ids); // bỏ id đầu giữ lại
            if (!empty($ids)) {
                DB::table('cinemas')->whereIn('id', $ids)->delete();
            }
        }

        Schema::table('cinemas', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cinemas', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }
};

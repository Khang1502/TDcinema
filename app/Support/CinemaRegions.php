<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CinemaRegions
{
    public const LABELS = [
        'ho-chi-minh' => 'TP. Hồ Chí Minh',
        'ha-noi' => 'Hà Nội',
        'da-nang' => 'Đà Nẵng',
        'nha-trang' => 'Nha Trang',
        'can-tho' => 'Cần Thơ',
    ];

    private const CITIES = [
        'ho-chi-minh' => ['TP.HCM', 'TP. Hồ Chí Minh', 'Hồ Chí Minh', 'HCM', 'TPHCM', 'Sài Gòn'],
        'ha-noi' => ['Hà Nội', 'Hanoi'],
        'da-nang' => ['Đà Nẵng', 'Danang'],
        'nha-trang' => ['Nha Trang', 'Nhatrang'],
        'can-tho' => ['Cần Thơ', 'Cantho'],
    ];

    public static function infer(?string $city): string
    {
        $key = self::cityKey($city);
        foreach (self::CITIES as $region => $cities) {
            foreach ($cities as $candidate) {
                if ($key === self::cityKey($candidate)) {
                    return $region;
                }
            }
        }

        return 'other';
    }

    public static function cityKey(?string $city): string
    {
        $value = preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii(trim((string) $city))));

        return preg_replace('/^(thanhpho|tinh|tp)/', '', $value);
    }

    public static function groups(Collection $cinemas): Collection
    {
        $grouped = $cinemas->groupBy('region_key');

        return collect(self::LABELS)->map(function (string $label, string $key) use ($grouped) {
            $items = $grouped->get($key, collect());

            return ['key' => $key, 'label' => $label, 'cinemas' => $items, 'count' => $items->count()];
        });
    }

    public static function citySuggestions(Collection $cinemas): Collection
    {
        return $cinemas->pluck('city')->merge(['TP.HCM', 'Hà Nội', 'Đà Nẵng', 'Nha Trang', 'Cần Thơ'])
            ->filter()->unique()->sort()->values();
    }
}

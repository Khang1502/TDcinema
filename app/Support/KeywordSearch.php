<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class KeywordSearch
{
    public static function normalize(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/',' ',strtolower(Str::ascii($value,'vi'))));
    }

    public static function matchingIds(Collection $movies, string $keyword, array $fields=['title']): array
    {
        $normalized = self::normalize($keyword);
        if ($normalized === '') return [];
        $tokens = explode(' ',$normalized);
        return $movies->filter(function ($movie) use ($tokens,$fields) {
            $text = self::normalize(implode(' ',array_map(fn ($field)=>(string)$movie->{$field},$fields)));
            foreach ($tokens as $token) if (!str_contains($text,$token)) return false;
            return true;
        })->pluck('id')->all();
    }
}

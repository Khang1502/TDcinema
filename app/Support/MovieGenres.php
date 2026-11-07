<?php

namespace App\Support;

use App\Models\{Movie, MovieGenre};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MovieGenres
{
    public const COMMON = [
        'Hành động', 'Hoạt hình', 'Hài', 'Kinh dị', 'Tình cảm', 'Tâm lý',
        'Phiêu lưu', 'Khoa học viễn tưởng', 'Giả tưởng', 'Gia đình',
        'Chính kịch', 'Tài liệu', 'Âm nhạc', 'Chiến tranh', 'Thần thoại',
    ];

    public static function parse(mixed $value): array
    {
        $items = is_array($value) ? $value : (is_string($value) ? preg_split('/[,;]+/u', $value) : []);

        return collect($items)->filter(fn ($item) => is_string($item))
            ->map(fn ($item) => trim(preg_replace('/\s+/u', ' ', $item)))
            ->filter(fn ($item) => $item !== '')
            ->unique(fn ($item) => mb_strtolower($item))->values()->all();
    }

    public static function options(): array
    {
        return MovieGenre::where('is_active',true)->orderBy('sort_order')->orderBy('name')->pluck('name')->all();
    }

    public static function catalog(): Collection
    {
        return MovieGenre::orderBy('sort_order')->orderBy('name')->get();
    }

    public static function lookup(?Collection $catalog = null): array
    {
        $lookup = [];
        foreach ($catalog ?? self::catalog() as $genre) foreach ($genre->keys() as $key) $lookup[$key] = $genre;
        return $lookup;
    }

    public static function movieIds(string $name): array
    {
        $key = MovieGenre::key($name);
        $genre = self::lookup()[$key] ?? null;
        $keys = $genre?->keys() ?? [$key];
        return Movie::get(['id','genre'])->filter(fn ($movie)=>count(array_intersect($keys,
            array_map(MovieGenre::key(...),self::parse($movie->genre)))) > 0)->pluck('id')->all();
    }

    public static function usage(Collection $catalog): array
    {
        $lookup = self::lookup($catalog);
        $counts = $catalog->mapWithKeys(fn ($genre)=>[$genre->id=>0])->all();
        foreach (Movie::get(['id','genre']) as $movie) {
            $ids = [];
            foreach (self::parse($movie->genre) as $name) if ($genre = $lookup[MovieGenre::key($name)] ?? null) $ids[$genre->id] = true;
            foreach (array_keys($ids) as $id) $counts[$id]++;
        }
        return $counts;
    }

    public static function resolveAliases(?string $value): ?string
    {
        if ($value === null || $value === '' || ! Schema::hasTable('movie_genres')) return $value;
        $lookup = self::lookup();
        $changed = false;
        $names = array_map(function ($name) use ($lookup,&$changed) {
            $key = MovieGenre::key($name);
            $genre = $lookup[$key] ?? null;
            if ($genre && $key !== $genre->name_key) { $changed = true; return $genre->name; }
            return $name;
        },self::parse($value));
        if (! $changed) return $value;
        $resolved = implode(', ',self::parse($names));
        if (mb_strlen($resolved) > 100) throw ValidationException::withMessages(['genre'=>'Tên thể loại mới khiến danh sách thể loại của phim quá dài.']);
        return $resolved;
    }

    public static function register(?string $value): void
    {
        if ($value === null || $value === '' || ! Schema::hasTable('movie_genres')) return;
        $lookup = self::lookup();
        foreach (self::parse($value) as $name) {
            $key = MovieGenre::key($name);
            if (isset($lookup[$key])) continue;
            $genre = MovieGenre::firstOrCreate(['name_key'=>$key],['name'=>$name,'is_active'=>true,
                'sort_order'=>min(100000,(int)MovieGenre::max('sort_order')+10)]);
            $lookup[$key] = $genre;
        }
    }
}

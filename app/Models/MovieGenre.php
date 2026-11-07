<?php

namespace App\Models;

use App\Support\KeywordSearch;
use Illuminate\Database\Eloquent\Model;

class MovieGenre extends Model
{
    protected $fillable = ['name','name_key','description','is_active','sort_order','aliases'];

    protected function casts(): array
    {
        return ['is_active'=>'boolean','sort_order'=>'integer','aliases'=>'array'];
    }

    public static function key(string $name): string
    {
        return KeywordSearch::normalize($name) ?: mb_strtolower(trim(preg_replace('/\s+/u',' ',$name)));
    }

    public function keys(): array
    {
        return array_values(array_unique([$this->name_key,...array_map(self::key(...),$this->aliases ?? [])]));
    }
}

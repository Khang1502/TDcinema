<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlyMovieSetting extends Model
{
    public const DEFAULTS = ['min_rating' => 4.0, 'min_reviews' => 0];

    protected $fillable = ['min_rating', 'min_reviews'];

    protected function casts(): array
    {
        return ['min_rating' => 'float', 'min_reviews' => 'integer'];
    }

    public static function current(): self
    {
        return static::find(1) ?? new static(self::DEFAULTS);
    }
}

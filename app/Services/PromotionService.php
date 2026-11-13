<?php

namespace App\Services;

use App\Models\Promotion;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PromotionService
{
    public function findAvailable(string $code, bool $lock = false): Promotion
    {
        return $this->findAvailableForUser($code, auth()->user(), $lock);
    }

    public function findAvailableForUser(string $code, ?User $user, bool $lock = false): Promotion
    {
        $query = Promotion::available()->eligibleFor($user)->whereRaw('UPPER(code) = ?', [strtoupper(trim($code))]);
        $promotion = ($lock ? $query->lockForUpdate() : $query)->first();
        if (! $promotion) {
            throw ValidationException::withMessages(['promo_code' => 'Mã khuyến mãi không tồn tại, đã hết hạn hoặc hết lượt sử dụng.']);
        }

        return $promotion;
    }

    public function consume(Promotion $promotion): void
    {
        if ($promotion->usage_limit === null) return;
        if (! Promotion::whereKey($promotion->id)->where('usage_limit', '>', 0)->decrement('usage_limit')) {
            throw ValidationException::withMessages(['promo_code' => 'Mã khuyến mãi vừa hết lượt sử dụng. Vui lòng chọn mã khác.']);
        }
    }
}

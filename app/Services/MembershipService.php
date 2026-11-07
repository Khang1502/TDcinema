<?php

namespace App\Services;

use App\Models\{Invoice, MembershipLevel, MembershipSetting, MembershipTransaction, Promotion, User};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MembershipService
{
    private ?Collection $levels = null;
    private ?MembershipSetting $settings = null;

    public function levels(): Collection { return $this->levels ??= MembershipLevel::active()->get(); }
    public function settings(): MembershipSetting { return $this->settings ??= MembershipSetting::current(); }
    public function refresh(): void { $this->levels = null; $this->settings = null; }
    public function levelFor(int $points): ?MembershipLevel { return $this->levels()->last(fn ($level) => $level->min_points <= max(0, $points)); }

    public function summary(User $user): array
    {
        $points = max(0, (int) $user->points);
        $level = $this->levelFor($points);
        $next = $this->levels()->first(fn ($item) => $item->min_points > $points);
        $floor = $level?->min_points ?? 0;
        $progress = $next ? min(100, max(0, ($points - $floor) / max(1, $next->min_points - $floor) * 100)) : 100;
        return compact('points','level','next','progress') + ['remaining'=>$next ? max(0, $next->min_points - $points) : 0];
    }

    public function pageData(?User $user): array
    {
        return ['settings'=>$this->settings(),'levels'=>$this->levels(),'summary'=>$user ? $this->summary($user) : null,
            'memberGifts'=>Promotion::available()->where('is_member_gift',true)->orderBy('min_member_points')->orderBy('sort_order')->get(),
            'transactions'=>$user ? MembershipTransaction::where('user_id',$user->id)->latest('id')->paginate(8)->withQueryString() : null];
    }

    public function discount(User $user, float $total): int
    {
        if ($user->role !== 'customer' || ! $this->settings()->is_enabled) return 0;
        return (int) round(max(0, $total) * min(100, max(0, $this->levelFor((int) $user->points)?->discount_percent ?? 0)) / 100);
    }

    public function award(Invoice $invoice): int
    {
        if (! $this->settings()->is_enabled) return 0;
        return DB::transaction(function () use ($invoice) {
            $user = User::whereKey($invoice->user_id)->lockForUpdate()->first();
            if (! $user || $user->role !== 'customer' || MembershipTransaction::where('invoice_id', $invoice->id)->exists()) return 0;
            $earned = (int) floor(max(0, (float) $invoice->total_amount) / max(1, $this->settings()->earn_unit));
            $balance = min(4294967295, (int) $user->points + $earned);
            $earned = $balance - (int) $user->points;
            $user->forceFill(['points'=>$balance,'membership_level_id'=>$this->levelFor($balance)?->id,'total_spent'=>max(0, (float) $user->total_spent) + (float) $invoice->total_amount])->save();
            MembershipTransaction::create(['user_id'=>$user->id,'invoice_id'=>$invoice->id,'points'=>$earned,'balance_after'=>$balance,'description'=>'Tích Stars từ đơn '.$invoice->invoice_code]);
            $invoice->update(['points_earned'=>$earned]);
            return $earned;
        });
    }

    public function adjust(User $member, int $points, string $reason, User $actor): void
    {
        DB::transaction(function () use ($member, $points, $reason, $actor) {
            $user = User::whereKey($member->id)->where('role', 'customer')->lockForUpdate()->firstOrFail();
            $balance = (int) $user->points + $points;
            if ($points === 0 || $balance < 0 || $balance > 4294967295) throw ValidationException::withMessages(['points'=>'Điểm sau điều chỉnh phải từ 0 trở lên và trong giới hạn cho phép.']);
            $user->forceFill(['points'=>$balance,'membership_level_id'=>$this->levelFor($balance)?->id])->save();
            MembershipTransaction::create(['user_id'=>$user->id,'actor_id'=>$actor->id,'points'=>$points,'balance_after'=>$balance,'description'=>$reason]);
        });
    }
}

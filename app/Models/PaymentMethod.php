<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    public const DEFAULTS = [
        'onepay' => ['name'=>'OnePay', 'description'=>'Thẻ Visa, MasterCard, JCB, ATM và QR ngân hàng.', 'is_online'=>true, 'is_enabled'=>true],
        'shopeepay' => ['name'=>'ShopeePay', 'description'=>'Thanh toán bằng ví điện tử ShopeePay.', 'is_online'=>true, 'is_enabled'=>true],
        'momo' => ['name'=>'MoMo', 'description'=>'Thanh toán bằng ví điện tử MoMo.', 'is_online'=>true, 'is_enabled'=>true],
        'zalopay' => ['name'=>'ZaloPay', 'description'=>'Thanh toán bằng ví điện tử ZaloPay.', 'is_online'=>true, 'is_enabled'=>true],
        'payoo' => ['name'=>'Payoo', 'description'=>'Thanh toán qua thẻ và QR ngân hàng.', 'is_online'=>true, 'is_enabled'=>true],
        'fundiin' => ['name'=>'Fundiin', 'description'=>'Lựa chọn trả sau Fundiin.', 'is_online'=>true, 'is_enabled'=>true],
        'vnpay' => ['name'=>'VNPay', 'description'=>'Thanh toán qua VNPay và QR ngân hàng.', 'is_online'=>true, 'is_enabled'=>false],
        'cash' => ['name'=>'Tiền mặt', 'description'=>'Thanh toán khi nhân viên tạo vé tại quầy.', 'is_online'=>false, 'is_enabled'=>true],
    ];

    protected $fillable = ['name', 'description', 'instructions', 'badge', 'sort_order', 'is_enabled'];

    protected function casts(): array
    {
        return ['is_online'=>'boolean', 'is_enabled'=>'boolean', 'sort_order'=>'integer'];
    }

    public function scopeAvailableOnline(Builder $query): Builder
    {
        return $query->whereIn('code', array_keys(self::DEFAULTS))->where('is_online', true)->where('is_enabled', true)
            ->orderBy('sort_order')->orderBy('id');
    }

    public static function labels(): array
    {
        return static::orderBy('sort_order')->orderBy('id')->pluck('name', 'code')->all();
    }

    public static function onlineCodes(): array
    {
        return static::availableOnline()->pluck('code')->all();
    }

    public static function onlineEnabled(string $code): bool
    {
        return static::availableOnline()->where('code', $code)->exists();
    }

    public function isDemo(): bool
    {
        return $this->is_online && ($this->code !== 'momo'
            || (config('services.momo.mode') === 'demo' && app()->environment('local', 'testing')));
    }

    public function monogram(): string
    {
        return ['onepay'=>'1P', 'shopeepay'=>'SP', 'momo'=>'M', 'zalopay'=>'ZP', 'payoo'=>'PO', 'fundiin'=>'F', 'vnpay'=>'VNP', 'cash'=>'TM'][$this->code] ?? 'TT';
    }
}

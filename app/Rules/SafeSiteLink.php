<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeSiteLink implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) { $fail('Liên kết không hợp lệ.'); return; }
        $relative = preg_match('~^/(?!/)[^\s\\\\]*$~u', $value);
        $web = filter_var($value, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($value, PHP_URL_SCHEME) ?? ''), ['http','https'], true);
        $mail = str_starts_with($value, 'mailto:') && filter_var(substr($value, 7), FILTER_VALIDATE_EMAIL);
        if (!$relative && !$web && !$mail) $fail('Liên kết phải bắt đầu bằng /, http://, https:// hoặc mailto: hợp lệ.');
    }
}

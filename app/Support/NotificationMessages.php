<?php

namespace App\Support;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\ViewErrorBag;

class NotificationMessages
{
    public static function forPage(Session $session, ViewErrorBag $errors, array $extra = []): array
    {
        $messages = [];
        foreach (['success', 'error', 'warning', 'info'] as $type) {
            $message = $session->get($type);
            if (is_string($message) && trim($message) !== '') $messages[] = ['type' => $type, 'message' => $message];
        }

        $status = $session->get('status');
        if (is_string($status) && trim($status) !== '') {
            $messages[] = ['type' => $session->get('reset_link') ? 'warning' : 'success', 'message' => match ($status) {
                'profile-updated' => 'Đã cập nhật thông tin tài khoản.',
                'password-updated' => 'Đã cập nhật mật khẩu.',
                'notifications-read' => 'Đã đánh dấu tất cả thông báo là đã đọc.',
                'verification-link-sent' => 'Đã gửi lại email xác thực.',
                'passwords.sent', 'We have emailed your password reset link.' => 'Đã gửi liên kết đặt lại mật khẩu. Vui lòng kiểm tra email.',
                default => $status,
            }];
        }
        if (is_string($session->get('session_expired'))) {
            $messages[] = ['type' => 'warning', 'message' => $session->get('session_expired')];
        }

        $validation = [];
        foreach ($errors->getBags() as $bag) $validation = [...$validation, ...$bag->all()];
        $validation = array_values(array_unique($validation));
        if ($validation) {
            $messages[] = ['type' => 'error', 'title' => 'Vui lòng kiểm tra thông tin',
                'message' => count($validation) === 1 ? $validation[0] : 'Có '.count($validation).' mục cần điều chỉnh.',
                'details' => count($validation) > 1 ? $validation : []];
        }

        foreach (['seat_layout_skipped' => 'Các phòng chưa đổi sơ đồ ghế', 'seat_price_skipped' => 'Các phòng chưa đổi giá ghế'] as $key => $title) {
            if ($details = $session->get($key)) $messages[] = ['type' => 'warning', 'title' => $title,
                'message' => 'Những phòng này được giữ nguyên vì có đặt vé hoặc khách đang giữ ghế.', 'details' => array_values($details), 'duration' => 0];
        }
        $unique = [];
        foreach ([...$messages, ...$extra] as $message) {
            if (empty($message['message']) || !is_string($message['message'])) continue;
            $unique[$message['type'].':'.$message['message']] = $message;
        }

        return array_values($unique);
    }
}

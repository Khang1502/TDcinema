<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\TicketExpiryService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('tickets:expire', function () {
    $count = app(TicketExpiryService::class)->expireOverdue();
    $this->info("Đã chuyển {$count} vé quá hạn vào mục đã hủy.");
})->purpose('Hủy các vé chưa check-in sau khi suất chiếu kết thúc');

Schedule::command('tickets:expire')->everyMinute()->withoutOverlapping();

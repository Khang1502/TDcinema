<?php

namespace Tests\Unit;

use App\Services\BookingHoldService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingHoldServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        session()->flush();
        $this->travelTo(now()->startOfSecond());
    }

    public function test_same_hold_is_reused_without_resetting_expiry(): void
    {
        $service = app(BookingHoldService::class);

        $first = $service->start(4, 99, collect(['A1', 'A2']));
        $this->travel(2)->minutes();
        $second = $service->start(4, 99, collect(['A2', 'A1']));

        $this->assertSame($first['token'], $second['token']);
        $this->assertSame($first['expires_at'], $second['expires_at']);
        $this->assertLessThanOrEqual(480, $service->remainingSeconds($second));
    }

    public function test_a_seat_cannot_be_held_by_two_users(): void
    {
        $service = app(BookingHoldService::class);
        $service->start(4, 99, collect(['A1']));
        session()->flush();

        $this->expectException(ValidationException::class);
        $service->start(5, 99, collect(['A1']));
    }

    public function test_adding_a_seat_after_eight_minutes_uses_the_first_deadline_and_both_cache_keys_expire_together(): void
    {
        $service = app(BookingHoldService::class);
        $first = $service->start(4, 99, collect(['A1']));
        $this->travel(8)->minutes();
        $second = $service->start(4, 99, collect(['A1', 'A2']));

        $this->assertSame($first['expires_at'], $second['expires_at']);
        $this->assertSame(120, $service->remainingSeconds($second));
        foreach (['A1', 'A2'] as $code) {
            $this->assertSame($first['expires_at'], Cache::get("booking-hold:99:{$code}")['expires_at']);
        }

        $this->travel(119)->seconds();
        $this->assertNotNull($service->active(99));
        $this->assertNotNull(Cache::get('booking-hold:99:A2'));
        $this->travel(1)->seconds();
        $this->assertNull(Cache::get('booking-hold:99:A1'));
        $this->assertNull(Cache::get('booking-hold:99:A2'));
        $this->assertNull($service->active(99));
        $this->assertNull(session('booking.hold'));
    }

    public function test_replacing_or_removing_seats_keeps_the_deadline_and_releases_only_removed_seats(): void
    {
        $service = app(BookingHoldService::class);
        $first = $service->start(4, 99, collect(['A1', 'A2']));
        $this->travel(8)->minutes();
        $replacement = $service->start(4, 99, collect(['B1', 'B2']));
        $this->assertSame($first['expires_at'], $replacement['expires_at']);
        $this->assertNull(Cache::get('booking-hold:99:A1'));
        $this->assertNull(Cache::get('booking-hold:99:A2'));

        $this->travel(1)->minutes();
        $reduced = $service->start(4, 99, collect(['B1']));
        $this->assertSame($first['expires_at'], $reduced['expires_at']);
        $this->assertSame(60, $service->remainingSeconds($reduced));
        $this->assertNull(Cache::get('booking-hold:99:B2'));
        $this->assertNotNull(Cache::get('booking-hold:99:B1'));
        $this->assertSame('B1', session('booking.seats'));
    }

    public function test_a_failed_selection_change_preserves_the_original_hold_and_rolls_back_new_seats(): void
    {
        $service = app(BookingHoldService::class);
        $first = $service->start(4, 99, collect(['A1']));
        $this->travel(8)->minutes();
        $other = ['token' => 'another-customer', 'user_id' => 5, 'showtime_id' => 99, 'expires_at' => now()->addMinutes(10)->timestamp];
        Cache::put('booking-hold:99:A3', $other, now()->addMinutes(10));

        try {
            $service->start(4, 99, collect(['A1', 'A2', 'A3']));
            $this->fail('A seat held by another customer must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('seats', $exception->errors());
        }

        $this->assertSame($first, $service->active(99));
        $this->assertSame(120, $service->remainingSeconds($first));
        $this->assertNotNull(Cache::get('booking-hold:99:A1'));
        $this->assertNull(Cache::get('booking-hold:99:A2'));
        $this->assertSame($other, Cache::get('booking-hold:99:A3'));
    }

    public function test_a_successful_selection_change_invalidates_previous_checkout_amounts_and_pending_payment(): void
    {
        $service = app(BookingHoldService::class);
        $first = $service->start(4, 99, collect(['A1']));
        session()->put('booking.combo_total', 89000);
        session()->put('booking.promo_code', 'OLD-CODE');
        session()->put('booking.pending_payment', ['showtime_id' => 99]);
        $this->travel(8)->minutes();
        $second = $service->start(4, 99, collect(['A1', 'A2']));

        $this->assertSame($first['expires_at'], $second['expires_at']);
        $this->assertSame('A1,A2', session('booking.seats'));
        $this->assertFalse(session()->has('booking.combo_total'));
        $this->assertFalse(session()->has('booking.promo_code'));
        $this->assertFalse(session()->has('booking.pending_payment'));
    }

    public function test_a_new_booking_after_expiry_or_for_another_showtime_gets_a_new_deadline(): void
    {
        $service = app(BookingHoldService::class);
        $first = $service->start(4, 99, collect(['A1']));
        $this->travel(10)->minutes();
        $renewed = $service->start(4, 99, collect(['A1', 'A2']));
        $this->assertNotSame($first['token'], $renewed['token']);
        $this->assertSame(now()->addMinutes(10)->timestamp, $renewed['expires_at']);

        $this->travel(8)->minutes();
        $otherShowtime = $service->start(4, 100, collect(['A1']));
        $this->assertSame(600, $service->remainingSeconds($otherShowtime));
        $this->assertNull(Cache::get('booking-hold:99:A1'));
        $this->assertNull(Cache::get('booking-hold:99:A2'));
    }

    public function test_another_account_does_not_inherit_the_previous_accounts_deadline(): void
    {
        $service = app(BookingHoldService::class);
        $service->start(4, 99, collect(['A1']));
        $this->travel(8)->minutes();
        $otherUser = $service->start(5, 99, collect(['A1', 'A2']));
        $this->assertSame(5, $otherUser['user_id']);
        $this->assertSame(600, $service->remainingSeconds($otherUser));
    }

    public function test_expired_hold_is_not_active(): void
    {
        $service = app(BookingHoldService::class);
        $service->start(4, 99, collect(['A1']));

        $this->travel(11)->minutes();

        $this->assertNull($service->active(99));
        $this->assertNull(session('booking.hold'));
    }
}

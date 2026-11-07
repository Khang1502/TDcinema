<?php

namespace Tests\Feature;

use App\Models\{Cinema, Invoice, Movie, Payment, Promotion, Room, Seat, Showtime, Ticket, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, Http};
use Tests\TestCase;

class PromotionCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function promo(array $changes = []): Promotion
    {
        return Promotion::create(array_replace(['name' => 'Ưu đãi xem phim', 'code' => 'TD'.(Promotion::count() + 1), 'type' => 'percent', 'value' => 20,
            'start_date' => today(), 'end_date' => today()->addDays(30), 'status' => 'active', 'usage_limit' => 2], $changes));
    }

    private function booking(int $count = 1): Showtime
    {
        $cinema = Cinema::create(['name' => 'TD Cinema Test', 'address' => '01 Điện Ảnh', 'city' => 'TP.HCM']);
        $room = Room::create(['cinema_id' => $cinema->id, 'name' => 'Phòng 1', 'type' => '2D', 'total_seats' => $count]);
        for ($i = 1; $i <= $count; $i++) Seat::create(['room_id' => $room->id, 'seat_row' => 'A', 'seat_number' => $i, 'seat_type' => 'normal', 'extra_price' => 0, 'status' => 1]);
        $movie = Movie::create(['title' => 'Phim ưu đãi', 'duration' => 90, 'status' => 'now_showing']);
        $showtime = Showtime::create(['movie_id' => $movie->id, 'room_id' => $room->id, 'start_time' => now()->addDay(), 'end_time' => now()->addDay()->addHours(2), 'base_price' => 60000, 'status' => 'active']);
        $this->actingAs(User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]));
        $this->postJson(route('booking.hold', $showtime), ['seats' => array_map(fn ($i) => 'A'.$i, range(1, $count))])->assertOk();
        $this->get(route('booking.payment', ['showtime' => $showtime, 'combo_total' => 20000]))->assertOk();

        return $showtime;
    }

    public function test_public_promotions_only_show_real_available_or_upcoming_codes(): void
    {
        $active = $this->promo();
        $upcoming = $this->promo(['start_date' => today()->addDays(2)]);
        $expired = $this->promo(['end_date' => today()->subDay()]);
        $empty = $this->promo(['usage_limit' => 0]);
        $disabled = $this->promo(['status' => 'expired']);
        $this->get(route('promotions.index'))->assertOk()->assertSee($active->code)->assertDontSee($upcoming->code)->assertDontSee($expired->code)->assertDontSee($empty->code)->assertDontSee($disabled->code);
        $this->get(route('promotions.index', ['filter' => 'upcoming']))->assertOk()->assertSee($upcoming->code)->assertDontSee($active->code);
        $this->post(route('promotions.select', $active))->assertRedirect(route('movies.index'))->assertSessionHas('promotion.preferred_code', $active->code);
        $this->assertEquals(2, $active->fresh()->usage_limit);
        $this->post(route('promotions.select', $expired))->assertSessionHasErrors('promo_code');
    }

    public function test_payment_lists_every_available_code_ranked_by_actual_savings_and_keeps_selected_code(): void
    {
        for ($i = 0; $i < 12; $i++) $this->promo(['type' => 'fixed', 'value' => 1000 + $i]);
        $best = $this->promo(['type' => 'fixed', 'value' => 25000]);
        $expired = $this->promo(['end_date' => today()->subDay()]);
        $this->post(route('promotions.select', $best));
        $showtime = $this->booking();
        $this->get(route('booking.payment', $showtime))->assertOk()->assertViewHas('availablePromos', fn ($items) => $items->count() === 13 && $items->first()->id === $best->id)
            ->assertViewHas('appliedPromo', fn ($item) => $item->id === $best->id)->assertViewHas('promoDiscount', 25000)->assertDontSee('data-code="'.$expired->code.'"', false);
    }

    public function test_checkout_combines_general_promotions_with_only_eligible_member_vouchers(): void
    {
        $common = $this->promo(['code'=>'GENERAL']);
        $gift = $this->promo(['code'=>'MEMBER','is_member_gift'=>true,'min_member_points'=>100,'type'=>'fixed','value'=>10000]);
        $showtime = $this->booking();
        $this->get(route('booking.payment',$showtime))->assertOk()
            ->assertViewHas('availablePromos',fn ($items) => $items->contains('id',$common->id) && !$items->contains('id',$gift->id));
        $this->postJson(route('booking.promo.apply'),['code'=>$gift->code,'showtime_id'=>$showtime->id])->assertUnprocessable();
        $member = auth()->user();
        $member->forceFill(['points'=>100])->save();
        $this->actingAs($member->fresh())->get(route('booking.payment',$showtime))->assertOk()
            ->assertViewHas('availablePromos',fn ($items) => $items->contains('id',$common->id) && $items->contains('id',$gift->id));
        $this->postJson(route('booking.promo.apply'),['code'=>$gift->code,'showtime_id'=>$showtime->id])->assertOk()->assertJsonPath('discount',10000);
        $this->assertSame(100,(int)$member->fresh()->points);
    }

    public function test_preview_uses_server_totals_and_does_not_consume_usage_on_repeated_selection(): void
    {
        $promo = $this->promo(['value' => 35, 'usage_limit' => 1]);
        $showtime = $this->booking();
        for ($i = 0; $i < 3; $i++) $this->postJson(route('booking.promo.apply'), ['code' => ' '.strtolower($promo->code).' ', 'showtime_id' => $showtime->id, 'total' => 99999999])
            ->assertOk()->assertJsonPath('discount', 28000)->assertJsonPath('total', 52000)->assertSessionHas('booking.promo_code', $promo->code);
        $this->assertEquals(1, $promo->fresh()->usage_limit);
        $this->get(route('booking.payment', $showtime))->assertViewHas('promoDiscount', 28000);
        $this->deleteJson(route('booking.promo.remove'))->assertOk()->assertSessionMissing('booking.promo_code')->assertSessionMissing('promotion.preferred_code');
        $this->get(route('booking.payment', $showtime))->assertViewHas('promoDiscount', 0);
    }

    public function test_invalid_codes_keep_previous_selection_and_expired_holds_cannot_apply(): void
    {
        $valid = $this->promo();
        $showtime = $this->booking();
        $this->postJson(route('booking.promo.apply'), ['code' => $valid->code, 'showtime_id' => $showtime->id])->assertOk();
        foreach ([['usage_limit' => 0], ['status' => 'expired'], ['end_date' => today()->subDay()], ['start_date' => today()->addDay()]] as $data) {
            $promo = $this->promo($data);
            $this->postJson(route('booking.promo.apply'), ['code' => $promo->code, 'showtime_id' => $showtime->id])->assertUnprocessable()->assertSessionHas('booking.promo_code', $valid->code);
        }
        $this->travel(11)->minutes();
        $this->postJson(route('booking.promo.apply'), ['code' => $valid->code, 'showtime_id' => $showtime->id])->assertUnprocessable();
        $this->assertEquals(2, $valid->fresh()->usage_limit);
    }

    public function test_successful_payment_saves_discounted_invoice_and_payments_and_consumes_exactly_once(): void
    {
        $promo = $this->promo(['value' => 35, 'usage_limit' => 1]);
        $showtime = $this->booking(3);
        $response = $this->post(route('booking.payment.process', $showtime), ['payment_method' => 'onepay', 'promo_code' => $promo->code, 'total' => 1]);
        $url = $response->headers->get('Location');
        $this->get($url)->assertOk()->assertViewHas('success', true)->assertViewHas('promoDiscount', 70000)->assertViewHas('grandTotal', 130000);
        $this->assertDatabaseHas('invoices', ['promotion_id' => $promo->id, 'total_amount' => 130000]);
        $this->assertEquals(130000, Payment::sum('amount'));
        $this->assertDatabaseCount('tickets', 3);
        $this->assertEquals(0, $promo->fresh()->usage_limit);
        $this->get($url)->assertViewHas('success', false);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertEquals(0, $promo->fresh()->usage_limit);
    }

    public function test_expiring_or_exhausting_a_code_before_payment_stops_purchase_without_charging_full_price(): void
    {
        $promo = $this->promo(['usage_limit' => 1]);
        $showtime = $this->booking();
        $this->postJson(route('booking.promo.apply'), ['code' => $promo->code, 'showtime_id' => $showtime->id])->assertOk();
        $promo->update(['usage_limit' => 0]);
        $this->post(route('booking.payment.process', $showtime), ['payment_method' => 'onepay', 'promo_code' => $promo->code])->assertRedirect(route('booking.payment', $showtime))->assertSessionHas('error');
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_failed_gateway_and_exhaustion_after_redirect_do_not_create_tickets(): void
    {
        $promo = $this->promo(['usage_limit' => 1]);
        $showtime = $this->booking();
        $response = $this->post(route('booking.payment.process', $showtime), ['payment_method' => 'onepay', 'promo_code' => $promo->code]);
        $url = $response->headers->get('Location');
        $this->get(str_replace('resultCode=0', 'resultCode=1', $url))->assertViewHas('success', false);
        $this->assertEquals(1, $promo->fresh()->usage_limit);
        $promo->update(['usage_limit' => 0]);
        $this->get($url)->assertViewHas('success', false);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_momo_amount_uses_same_discount_without_consuming_usage_during_gateway_creation(): void
    {
        config(['services.momo.mode' => 'gateway', 'services.momo.partner_code' => 'test-partner', 'services.momo.access_key' => 'test-access', 'services.momo.secret_key' => 'test-secret']);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['resultCode' => 0, 'payUrl' => 'https://test-payment.momo.vn/pay/test'])]);
        $promo = $this->promo(['type' => 'fixed', 'value' => 25000, 'usage_limit' => 1]);
        $showtime = $this->booking();
        $this->post(route('booking.payment.process', $showtime), ['payment_method' => 'momo', 'promo_code' => $promo->code, 'combo_total' => 999999, 'total' => 1])->assertRedirect('https://test-payment.momo.vn/pay/test');
        Http::assertSent(fn ($request) => (float) $request['amount'] === 55000.0);
        $this->assertEquals(1, $promo->fresh()->usage_limit);
    }

    public function test_demo_momo_finishes_with_discount_without_contacting_or_charging_a_gateway(): void
    {
        config(['services.momo.mode' => 'demo', 'services.momo.partner_code' => '', 'services.momo.access_key' => '', 'services.momo.secret_key' => '']);
        Http::preventStrayRequests();
        Http::fake();
        $promo = $this->promo(['value' => 35, 'usage_limit' => 1]);
        $showtime = $this->booking(3);
        $this->get(route('booking.payment', $showtime))->assertOk()->assertSee('MoMo đang ở chế độ mô phỏng')->assertViewHas('momoDemo', true);
        $response = $this->post(route('booking.payment.process', $showtime), ['payment_method' => 'momo', 'promo_code' => $promo->code, 'total' => 1, 'momo_mode' => 'gateway']);
        $response->assertRedirect()->assertSessionHas('booking.pending_payment.mode', 'demo');
        Http::assertNothingSent();
        $this->assertEquals(1, $promo->fresh()->usage_limit);
        $this->get($response->headers->get('Location'))->assertOk()->assertViewHas('success', true)->assertViewHas('demoPayment', true)
            ->assertViewHas('grandTotal', 130000)->assertSee('Không có tiền được trừ');
        $this->assertDatabaseHas('invoices', ['promotion_id' => $promo->id, 'total_amount' => 130000]);
        $this->assertDatabaseHas('payments', ['method' => 'momo', 'status' => 'paid']);
        $this->assertEquals(130000, Payment::sum('amount'));
        $this->assertEquals(0, $promo->fresh()->usage_limit);
        $this->get($response->headers->get('Location'))->assertViewHas('success', false);
        $this->assertDatabaseCount('invoices', 1);
        Http::assertNothingSent();
    }

    public function test_gateway_errors_remain_failures_and_keep_the_hold_and_selected_promotion(): void
    {
        config(['services.momo.mode' => 'gateway', 'services.momo.partner_code' => 'test-partner', 'services.momo.access_key' => 'test-access', 'services.momo.secret_key' => 'test-secret']);
        Http::preventStrayRequests();
        $promo = $this->promo(['usage_limit' => 1]);
        $showtime = $this->booking();
        $this->postJson(route('booking.promo.apply'), ['code' => $promo->code, 'showtime_id' => $showtime->id])->assertOk();
        foreach ([
            ['resultCode' => 13, 'message' => 'The merchant configuration is incorrect or the account is inactive.'],
            ['resultCode' => 11, 'payUrl' => 'https://test-payment.momo.vn/pay/invalid'],
            ['resultCode' => 99, 'message' => 'Internal failure with private details'],
        ] as $body) {
            Http::fake(['*' => Http::response($body)]);
            $this->post(route('booking.payment.process', $showtime), ['payment_method' => 'momo', 'promo_code' => $promo->code])
                ->assertRedirect(route('booking.payment', $showtime))->assertSessionHas('error')->assertSessionMissing('booking.pending_payment')
                ->assertSessionHas('booking.promo_code', $promo->code);
            $this->get(route('booking.payment', $showtime))->assertOk()->assertViewHas('promoDiscount', 16000)->assertDontSee('The merchant configuration')->assertDontSee('private details');
            $this->assertNotNull(app(\App\Services\BookingHoldService::class)->active($showtime->id));
        }
        $this->assertEquals(1, $promo->fresh()->usage_limit);
        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_missing_momo_credentials_and_connection_failures_do_not_issue_tickets(): void
    {
        config(['services.momo.mode' => 'gateway', 'services.momo.partner_code' => '', 'services.momo.access_key' => '', 'services.momo.secret_key' => '']);
        Http::preventStrayRequests();
        Http::fake();
        $showtime = $this->booking();
        $this->post(route('booking.payment.momo', $showtime))->assertRedirect(route('booking.payment', $showtime))->assertSessionHas('error');
        Http::assertNothingSent();
        $this->get(route('booking.payment', $showtime))->assertSee('MoMo chưa được cấu hình đầy đủ');
        config(['services.momo.partner_code' => 'test-partner', 'services.momo.access_key' => 'test-access', 'services.momo.secret_key' => 'test-secret']);
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Sensitive connection details'));
        $this->post(route('booking.payment.momo', $showtime))->assertRedirect(route('booking.payment', $showtime))
            ->assertSessionHas('error')->assertSessionMissing('booking.pending_payment');
        $this->get(route('booking.payment', $showtime))->assertSee('Chưa kết nối được MoMo')->assertDontSee('Sensitive connection details');
        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_demo_mode_is_not_available_in_production(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        app()->instance('env', 'production');
        config(['app.env' => 'production', 'services.momo.mode' => 'demo', 'services.momo.partner_code' => '', 'services.momo.access_key' => '', 'services.momo.secret_key' => '']);
        Http::fake();
        $showtime = $this->booking();
        $this->get(route('booking.payment', $showtime))->assertViewHas('momoDemo', false)->assertDontSee('MoMo đang ở chế độ mô phỏng');
        $this->post(route('booking.payment.process', $showtime), ['payment_method' => 'momo'])->assertRedirect(route('booking.payment', $showtime))->assertSessionHas('error');
        $this->assertDatabaseCount('tickets', 0);
        Http::assertNothingSent();
    }

    public function test_a_usage_failure_rolls_back_all_ticket_invoice_and_payment_writes(): void
    {
        $promo = $this->promo(['usage_limit' => 1]);
        $showtime = $this->booking();
        $response = $this->post(route('booking.payment.process', $showtime), ['payment_method' => 'onepay', 'promo_code' => $promo->code]);
        $this->partialMock(\App\Services\PromotionService::class, function ($mock) {
            $mock->shouldReceive('consume')->once()->andThrow(\Illuminate\Validation\ValidationException::withMessages(['promo_code' => 'Mã vừa hết lượt.']));
        });
        $this->get($response->headers->get('Location'))->assertViewHas('success', false);
        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('food_orders', 0);
        $this->assertEquals(1, $promo->fresh()->usage_limit);
    }

    public function test_fixed_discount_is_capped_at_order_total_and_admin_validates_percent_and_normalizes_code(): void
    {
        $promo = $this->promo(['type' => 'fixed', 'value' => 100000]);
        $this->assertSame(80000, $promo->discountFor(80000));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $data = ['name' => 'Ưu đãi mới', 'code' => ' newcode ', 'type' => 'percent', 'value' => 101, 'start_date' => today()->toDateString(), 'end_date' => today()->addDay()->toDateString(), 'status' => 'active'];
        $this->post(route('admin.promotions.store'), $data)->assertSessionHasErrors('value');
        $this->post(route('admin.promotions.store'), array_replace($data, ['value' => 10]))->assertRedirect(route('admin.promotions.index'));
        $this->assertDatabaseHas('promotions', ['code' => 'NEWCODE', 'value' => 10]);
        $this->get(route('admin.promotions.index'))->assertOk()->assertSee('Dùng được khi thanh toán');
    }
}

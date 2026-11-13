<?php

namespace Tests\Feature;

use App\Models\{Cinema, Movie, Payment, PaymentMethod, Room, Seat, Showtime, Ticket, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    private function method(string $code = 'onepay'): PaymentMethod
    {
        return PaymentMethod::where('code', $code)->firstOrFail();
    }

    private function booking(): Showtime
    {
        $number = Cinema::count()+1;
        $cinema = Cinema::create(['name'=>'TD Cinema Method '.$number, 'address'=>'Test', 'city'=>'TP.HCM']);
        $room = Room::create(['cinema_id'=>$cinema->id, 'name'=>'Rạp 1', 'type'=>'2D', 'total_seats'=>10]);
        Seat::create(['room_id'=>$room->id, 'seat_row'=>'A', 'seat_number'=>1, 'seat_type'=>'normal', 'status'=>1]);
        $movie = Movie::create(['title'=>'Phim phương thức '.$number, 'duration'=>90, 'status'=>'now_showing']);
        $show = Showtime::create(['movie_id'=>$movie->id, 'room_id'=>$room->id, 'start_time'=>now()->addDay(), 'end_time'=>now()->addDay()->addHours(2), 'base_price'=>70000, 'status'=>'active']);
        $this->actingAs(User::factory()->create(['role'=>'customer']));
        $this->postJson(route('booking.hold', $show), ['seats'=>['A1']])->assertOk();

        return $show;
    }

    private function form(PaymentMethod $method, array $overrides = []): array
    {
        return array_replace($method->only('name','description','instructions','badge','sort_order','is_enabled'), $overrides);
    }

    public function test_default_catalog_and_admin_navigation_keep_online_and_counter_methods_separate(): void
    {
        $this->assertDatabaseCount('payment_methods', 8);
        $this->assertSame(['onepay','shopeepay','momo','zalopay','payoo','fundiin'], PaymentMethod::onlineCodes());
        $this->actingAs(User::factory()->create(['role'=>'admin']))->get(route('admin.payment-methods.index'))->assertOk()
            ->assertSee('Thanh toán trực tuyến')->assertSee('Thanh toán tại quầy')->assertSee('Chỉnh sửa MoMo')
            ->assertSee('Thêm phương thức')->assertSee(asset('assets/images/payment-methods/momo.svg'));
        $this->get(route('admin.payment-methods.create'))->assertOk()->assertSee('Mã phương thức');
        $this->get(route('admin.payment-methods.edit', $this->method('momo')))->assertOk()->assertSee('Mã phương thức');
    }

    public function test_admin_can_add_disabled_demo_method_with_image_then_enable_it_for_checkout(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role'=>'admin']);
        $this->actingAs($admin)->post(route('admin.payment-methods.store'), [
            'code'=>'new_wallet', 'name'=>'Ví thử nghiệm', 'description'=>'Thanh toán bằng ví thử nghiệm',
            'instructions'=>'Xác nhận tại trang thanh toán', 'badge'=>'Mới', 'sort_order'=>5,
            'logo'=>UploadedFile::fake()->image('wallet.png', 80, 80),
        ])->assertRedirectToRoute('admin.payment-methods.index')->assertSessionHas('success');

        $method = $this->method('new_wallet');
        $this->assertFalse($method->is_enabled);
        $this->assertTrue($method->is_online);
        $this->assertTrue($method->isDemo());
        Storage::disk('public')->assertExists($method->logo_path);
        $this->assertNotContains('new_wallet', PaymentMethod::onlineCodes());
        $this->get(route('admin.payment-methods.index'))->assertOk()->assertSee($method->logoUrl());

        $this->patch(route('admin.payment-methods.visibility', $method), ['is_enabled'=>true])->assertRedirect();
        $this->assertContains('new_wallet', PaymentMethod::onlineCodes());
        $show = $this->booking();
        $this->get(route('booking.payment', $show))->assertOk()
            ->assertSee('Ví thử nghiệm')->assertSee('Thanh toán bằng ví thử nghiệm')->assertSee($method->logoUrl());
        $result = $this->post(route('booking.payment.process', $show), ['payment_method'=>'new_wallet'])->assertRedirect();
        $this->get($result->headers->get('Location'))->assertOk()->assertViewHas('success', true);
        $this->assertSame('new_wallet', Payment::latest('id')->first()->method);
    }

    public function test_new_method_rejects_duplicate_or_invalid_code_and_invalid_image(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'admin']))
            ->postJson(route('admin.payment-methods.store'), [
                'code'=>'momo', 'name'=>'Ví khác', 'sort_order'=>-1,
                'logo'=>UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
            ])->assertUnprocessable()->assertJsonValidationErrors(['code','sort_order','logo']);
        $this->assertDatabaseCount('payment_methods', 8);
    }

    public function test_admin_edits_are_used_by_checkout_with_the_selected_display_order(): void
    {
        $method = $this->method('zalopay');
        $this->actingAs(User::factory()->create(['role'=>'admin']))->put(route('admin.payment-methods.update', $method), $this->form($method, [
            'name'=>'Ví Zalo ưu tiên', 'description'=>'Mô tả đã chỉnh', 'instructions'=>"Bước một\nBước hai", 'badge'=>'Tiện lợi', 'sort_order'=>0,
        ]))->assertRedirectToRoute('admin.payment-methods.index')->assertSessionHas('success');
        $show = $this->booking();
        $this->get(route('booking.payment', $show))->assertOk()->assertSee('Ví Zalo ưu tiên')->assertSee('Mô tả đã chỉnh')->assertSee('Bước một')->assertSee('Tiện lợi')
            ->assertViewHas('paymentMethods', fn ($rows) => $rows->first()['key'] === 'zalopay' && $rows->count() === 6);
    }

    public function test_disabling_method_hides_it_and_blocks_new_server_requests_without_losing_the_hold(): void
    {
        $method = $this->method();
        $this->actingAs(User::factory()->create(['role'=>'admin']))->patch(route('admin.payment-methods.visibility', $method), ['is_enabled'=>false])->assertRedirect();
        $show = $this->booking();
        $this->get(route('booking.payment', $show))->assertOk()->assertDontSee('value="onepay"', false);
        $this->postJson(route('booking.payment.process', $show), ['payment_method'=>'onepay'])->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('tickets', 0);
        $this->get(route('booking.payment', $show))->assertOk()->assertViewHas('selectedSeats', fn ($seats) => $seats->all() === ['A1']);
    }

    public function test_disabled_momo_cannot_be_called_through_the_direct_route(): void
    {
        Http::fake();
        $this->method('momo')->update(['is_enabled'=>false]);
        $show = $this->booking();
        $this->post(route('booking.payment.momo', $show))->assertRedirect(route('booking.payment', $show))->assertSessionHas('error');
        Http::assertNothingSent();
        $this->get(route('booking.payment', $show))->assertOk()->assertDontSee('value="momo"', false)->assertViewHas('momoDemo', false);
    }

    public function test_no_active_online_methods_has_an_empty_state_and_cannot_fall_back_to_cash(): void
    {
        PaymentMethod::where('is_online', true)->update(['is_enabled'=>false]);
        $show = $this->booking();
        $this->get(route('booking.payment', $show))->assertOk()->assertSee('Hiện chưa có phương thức thanh toán khả dụng')
            ->assertViewHas('paymentMethods', fn ($rows) => $rows->isEmpty());
        foreach (['onepay','cash','unknown'] as $code) {
            $this->postJson(route('booking.payment.process', $show), ['payment_method'=>$code])->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        }
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_new_payments_keep_the_exact_selected_provider_and_started_payments_can_finish_after_disabling(): void
    {
        $this->method('vnpay')->update(['is_enabled'=>true]);
        foreach (['onepay','shopeepay','zalopay','payoo','fundiin','vnpay'] as $code) {
            $show = $this->booking();
            $response = $this->post(route('booking.payment.process', $show), ['payment_method'=>$code])->assertRedirect();
            if ($code === 'onepay') $this->method($code)->update(['is_enabled'=>false]);
            $this->get($response->headers->get('Location'))->assertOk()->assertViewHas('success', true);
            $this->assertSame($code, Payment::latest('id')->first()->method);
        }
        $this->assertDatabaseCount('payments', 6);
    }

    public function test_cash_settings_are_enforced_for_manual_ticket_creation(): void
    {
        $this->method('cash')->update(['is_enabled'=>false]);
        $show = $this->booking();
        $customer = auth()->user();
        $seat = Seat::where('room_id', $show->room_id)->first();
        $this->actingAs(User::factory()->create(['role'=>'admin']))
            ->post(route('admin.tickets.store'), ['showtime_id'=>$show->id, 'seat_id'=>$seat->id, 'user_id'=>$customer->id])
            ->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_names_are_shared_by_historical_payment_lists_reports_and_csv_even_when_disabled(): void
    {
        $method = $this->method('cash');
        $method->update(['name'=>'Tiền mặt tại rạp', 'is_enabled'=>false]);
        $show = $this->booking();
        $ticket = Ticket::create(['user_id'=>auth()->id(), 'showtime_id'=>$show->id, 'seat_id'=>Seat::where('room_id',$show->room_id)->value('id'), 'price'=>70000, 'status'=>'confirmed', 'booked_at'=>now()]);
        $payment = Payment::create(['ticket_id'=>$ticket->id, 'method'=>'cash', 'amount'=>70000, 'status'=>'paid', 'paid_at'=>now()]);
        $this->actingAs(User::factory()->create(['role'=>'admin']))->get(route('admin.payments.index', ['method'=>'cash']))->assertOk()->assertSee('Tiền mặt tại rạp');
        $this->get(route('admin.payments.show', $payment))->assertOk()->assertSee('Tiền mặt tại rạp');
        $this->get(route('admin.reports.index', ['tab'=>'transactions']))->assertOk()->assertSee('Tiền mặt tại rạp');
        $csv = $this->get(route('admin.payments.export', ['method'=>'cash']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Tiền mặt tại rạp', $csv);
        $this->assertSame('cash', $payment->fresh()->method);
    }

    public function test_read_permission_does_not_grant_settings_access_and_granular_settings_permission_works(): void
    {
        $method = $this->method();
        $paths = [route('admin.payment-methods.index'), route('admin.payment-methods.create'), route('admin.payment-methods.edit', $method)];
        $this->get($paths[0])->assertRedirectToRoute('login');
        foreach ([['role'=>'customer'], ['role'=>'staff'], ['role'=>'staff', 'admin_permissions'=>['dashboard.view','payments.manage']]] as $attributes) {
            $this->actingAs(User::factory()->create($attributes));
            foreach ($paths as $path) $this->get($path)->assertForbidden();
            $this->post(route('admin.payment-methods.store'), ['code'=>'new_wallet','name'=>'Ví mới','sort_order'=>10])->assertForbidden();
            $this->put(route('admin.payment-methods.update', $method), $this->form($method))->assertForbidden();
            $this->patch(route('admin.payment-methods.visibility', $method), ['is_enabled'=>false])->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role'=>'manager']))->get($paths[0])->assertOk();
        $this->actingAs(User::factory()->create(['role'=>'staff','admin_permissions'=>['dashboard.view','payment-methods.manage']]))->get($paths[0])->assertOk();
        $this->patch(route('admin.payment-methods.visibility', $method), ['is_enabled'=>false])->assertRedirect();
        $this->assertFalse($method->fresh()->is_enabled);
    }

    public function test_validation_keeps_provider_code_channel_and_gateway_configuration_immutable(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        $method = $this->method('cash');
        $this->putJson(route('admin.payment-methods.update', $method), $this->form($method, ['name'=>'', 'sort_order'=>-1, 'badge'=>str_repeat('x',41), 'is_enabled'=>2]))
            ->assertUnprocessable()->assertJsonValidationErrors(['name','sort_order','badge','is_enabled']);
        $this->put(route('admin.payment-methods.update', $method), $this->form($method, ['code'=>'momo','is_online'=>true,'mode'=>'gateway','name'=>'Tên đã sửa']))->assertRedirect();
        $this->assertSame('cash', $method->fresh()->code);
        $this->assertFalse($method->fresh()->is_online);
        $this->assertSame('Tên đã sửa', $method->fresh()->name);
        $this->assertNotContains('cash', PaymentMethod::onlineCodes());
        $this->patchJson(route('admin.payment-methods.visibility', $method), [])->assertUnprocessable()->assertJsonValidationErrors('is_enabled');
    }
}

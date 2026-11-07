<?php

namespace Tests\Feature;

use App\Models\{Cinema, Invoice, Movie, Payment, Room, Seat, Showtime, Ticket, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPaymentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-17 12:00:00'));
    }

    private function ticket(?User $customer = null, ?Cinema $cinema = null, ?Movie $movie = null): Ticket
    {
        $customer ??= User::factory()->create(['role'=>'customer']);
        $cinema ??= Cinema::create(['name'=>'TD Cinema thử nghiệm '.(Cinema::count()+1), 'address'=>'Test', 'city'=>'TP.HCM', 'status'=>'active']);
        $movie ??= Movie::create(['title'=>'Phim thử nghiệm '.(Movie::count()+1), 'duration'=>100, 'status'=>'now_showing']);
        $room = Room::create(['cinema_id'=>$cinema->id, 'name'=>'Rạp '.(Room::count()+1), 'type'=>'2D', 'total_seats'=>10, 'status'=>'active']);
        $seat = Seat::create(['room_id'=>$room->id, 'seat_row'=>'A', 'seat_number'=>1, 'seat_type'=>'normal', 'status'=>1]);
        $show = Showtime::create(['movie_id'=>$movie->id, 'room_id'=>$room->id, 'start_time'=>'2026-10-17 18:00:00', 'end_time'=>'2026-10-17 20:00:00', 'base_price'=>70000, 'status'=>'active']);

        return Ticket::create(['user_id'=>$customer->id, 'showtime_id'=>$show->id, 'seat_id'=>$seat->id, 'price'=>70000, 'status'=>'confirmed', 'booked_at'=>now(), 'qr_code'=>'TICKET-'.($seat->id)]);
    }

    private function payment(Ticket $ticket, array $attributes = []): Payment
    {
        $payment = Payment::create(array_replace(['ticket_id'=>$ticket->id, 'amount'=>70000, 'method'=>'cash', 'status'=>'paid', 'paid_at'=>now()], $attributes));
        if (isset($attributes['created_at'])) $payment->forceFill(['created_at'=>$attributes['created_at']])->save();

        return $payment;
    }

    public function test_summary_counts_each_payment_once_and_keeps_failed_amounts_out_of_paid_totals(): void
    {
        $ticket = $this->ticket();
        $this->payment($ticket, ['amount'=>70000]);
        $this->payment($ticket, ['amount'=>40000, 'method'=>'momo']);
        $this->payment($ticket, ['amount'=>30000, 'status'=>'refunded']);
        $this->payment($ticket, ['amount'=>999999, 'status'=>'failed', 'method'=>'vnpay', 'paid_at'=>null]);
        foreach (['INV-1','INV-2'] as $code) Invoice::create(['ticket_id'=>$ticket->id, 'user_id'=>$ticket->user_id, 'invoice_code'=>$code, 'total_amount'=>70000]);

        $this->actingAs(User::factory()->create(['role'=>'admin']))->get(route('admin.payments.index'))
            ->assertOk()->assertSee('Quản lý thanh toán')->assertSee('Đã hoàn tiền')
            ->assertViewHas('summary', fn ($s) => (int)$s->total === 4 && (float)$s->paid_amount === 110000.0 && (float)$s->refunded_amount === 30000.0 && (int)$s->failed_count === 1)
            ->assertViewHas('payments', fn ($rows) => $rows->total() === 4);
        $this->assertSame(4, Payment::count());
    }

    public function test_search_is_grouped_with_status_method_cinema_movie_and_date_filters(): void
    {
        $customer = User::factory()->create(['name'=>'Khach tim kiem', 'phone'=>'0901234567']);
        $ticket = $this->ticket($customer);
        $cinema = $ticket->showtime->room->cinema;
        $movie = $ticket->showtime->movie;
        $match = $this->payment($ticket, ['method'=>'momo', 'paid_at'=>'2026-10-17 23:59:59']);
        $this->payment($ticket, ['method'=>'momo', 'status'=>'failed']);
        $this->payment($ticket, ['method'=>'cash']);
        $this->payment($ticket, ['method'=>'momo', 'paid_at'=>'2026-10-16 23:59:59']);
        $this->payment($this->ticket($customer, null, $movie), ['method'=>'momo']);
        $this->payment($this->ticket($customer, $cinema), ['method'=>'momo']);

        $this->actingAs(User::factory()->create(['role'=>'admin']))
            ->get(route('admin.payments.index', ['q'=>'0901234567', 'status'=>'paid', 'method'=>'momo', 'cinema_id'=>$cinema->id, 'movie_id'=>$movie->id, 'from'=>'2026-10-17', 'to'=>'2026-10-17']))
            ->assertOk()->assertViewHas('payments', fn ($rows) => $rows->pluck('id')->all() === [$match->id])
            ->assertViewHas('summary', fn ($s) => (int)$s->total === 1 && (float)$s->paid_amount === 70000.0);
    }

    public function test_invoice_and_numeric_ticket_search_do_not_break_other_filters(): void
    {
        $ticket = $this->ticket();
        Invoice::create(['ticket_id'=>$ticket->id, 'user_id'=>$ticket->user_id, 'invoice_code'=>'TD-UNIQUE-INVOICE', 'total_amount'=>70000]);
        $match = $this->payment($ticket, ['method'=>'momo']);
        $this->payment($ticket, ['status'=>'failed']);
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        foreach (['TD-UNIQUE-INVOICE', '#'.$ticket->id, $ticket->qr_code] as $term) {
            $this->get(route('admin.payments.index', ['q'=>$term, 'status'=>'paid', 'method'=>'momo']))->assertOk()
                ->assertViewHas('payments', fn ($rows) => $rows->pluck('id')->all() === [$match->id]);
        }
    }

    public function test_date_fallback_sorting_and_independent_date_bounds_work(): void
    {
        $ticket = $this->ticket();
        $fallback = $this->payment($ticket, ['paid_at'=>null, 'amount'=>20000, 'created_at'=>'2026-10-16 08:00:00']);
        $latest = $this->payment($ticket, ['paid_at'=>'2026-10-17 23:59:59', 'amount'=>90000]);
        $this->payment($ticket, ['paid_at'=>'2026-10-15 23:59:59']);
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        foreach (['oldest'=>[$fallback->id,$latest->id], 'latest'=>[$latest->id,$fallback->id], 'amount_asc'=>[$fallback->id,$latest->id], 'amount_desc'=>[$latest->id,$fallback->id]] as $sort=>$ids) {
            $this->get(route('admin.payments.index', ['from'=>'2026-10-16', 'to'=>'2026-10-17', 'sort'=>$sort]))
                ->assertOk()->assertViewHas('payments', fn ($rows) => $rows->pluck('id')->all() === $ids);
        }
        $this->get(route('admin.payments.index', ['to'=>'2026-10-16']))->assertOk()->assertViewHas('payments', fn ($rows) => $rows->total() === 2);
    }

    public function test_details_show_deleted_customer_and_only_the_same_tickets_history(): void
    {
        $ticket = $this->ticket();
        $paid = $this->payment($ticket);
        $failed = $this->payment($ticket, ['status'=>'failed', 'paid_at'=>null]);
        $unrelated = $this->payment($this->ticket());
        $customer = $ticket->user;
        $customer->delete();
        $this->actingAs(User::factory()->create(['role'=>'admin']))->get(route('admin.payments.show', $paid))
            ->assertOk()->assertSee($customer->email)->assertSee('Lịch sử thanh toán của vé #'.$ticket->id)
            ->assertSee(route('admin.tickets.show', $ticket))->assertDontSee(route('admin.users.show', $customer))
            ->assertViewHas('relatedPayments', fn ($rows) => $rows->pluck('id')->all() === [$failed->id,$paid->id] && !$rows->contains('id',$unrelated->id));
        $this->get(route('admin.payments.show', 999999))->assertNotFound();
    }

    public function test_export_includes_every_filtered_page_and_neutralizes_spreadsheet_formulas(): void
    {
        $customer = User::factory()->create(['name'=>'=1+1', 'phone'=>'+84901234567']);
        $ticket = $this->ticket($customer);
        for ($i=0; $i<25; $i++) $this->payment($ticket);
        $this->payment($ticket, ['status'=>'refunded']);
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        $params = ['status'=>'paid', 'method'=>'cash', 'ticket_id'=>$ticket->id, 'sort'=>'oldest'];
        $this->get(route('admin.payments.index', $params))->assertOk()
            ->assertViewHas('payments', fn ($rows) => $rows->total() === 25 && $rows->count() === 20 && str_contains($rows->url(2), 'status=paid'));
        $this->get(route('admin.payments.index', $params+['page'=>2]))->assertOk()
            ->assertViewHas('payments', fn ($rows) => $rows->count() === 5);
        $response = $this->get(route('admin.payments.export', $params+['page'=>2]))->assertOk()->assertDownload();
        $contents = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contents);
        $stream = fopen('php://memory', 'r+'); fwrite($stream, substr($contents,3)); rewind($stream);
        $rows = []; while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) $rows[] = $row; fclose($stream);
        $this->assertCount(26, $rows);
        $this->assertSame("'=1+1", $rows[1][3]);
        $this->assertSame("'+84901234567", $rows[1][5]);
        $this->assertSame('70000.00', $rows[1][12]);
        $this->assertSame(26, Payment::count());
    }

    public function test_payment_permission_protects_list_details_export_and_menu(): void
    {
        $payment = $this->payment($this->ticket());
        $this->get(route('admin.payments.index'))->assertRedirectToRoute('login');
        $paths = [route('admin.payments.index'), route('admin.payments.show', $payment), route('admin.payments.export')];
        foreach ([['role'=>'customer'], ['role'=>'staff'], ['role'=>'manager', 'admin_permissions'=>['dashboard.view']], ['role'=>'admin', 'status'=>'blocked']] as $attributes) {
            $this->actingAs(User::factory()->create($attributes));
            foreach ($paths as $path) $this->get($path)->assertForbidden();
        }
        foreach (['admin','manager'] as $role) {
            $this->actingAs(User::factory()->create(['role'=>$role]))->get($paths[0])->assertOk()->assertSee($paths[0]);
        }
        $this->actingAs(User::factory()->create(['role'=>'staff', 'admin_permissions'=>['dashboard.view','payments.manage']]))
            ->get($paths[1])->assertOk()->assertDontSee(route('admin.tickets.show', $payment->ticket_id))->assertDontSee(route('admin.reports.index'));
        $this->get($paths[2])->assertOk();
        $this->actingAs(User::factory()->create(['role'=>'staff']))->get(route('admin.dashboard'))->assertOk()->assertDontSee($paths[0]);
    }

    public function test_invalid_filters_are_rejected_and_empty_state_is_readable(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'admin']))->get(route('admin.payments.index'))->assertOk()
            ->assertSee('Không có giao dịch phù hợp')->assertViewHas('summary', fn ($s) => (int)$s->total === 0 && (float)$s->paid_amount === 0.0);
        $invalid = ['status'=>'pending', 'method'=>'invalid', 'cinema_id'=>9999, 'movie_id'=>9999, 'ticket_id'=>9999, 'from'=>'2026-10-17', 'to'=>'2026-10-16', 'sort'=>'unsafe'];
        foreach (['admin.payments.index', 'admin.payments.export'] as $route) {
            $this->getJson(route($route, $invalid))->assertUnprocessable()->assertJsonValidationErrors(['status','method','cinema_id','movie_id','ticket_id','to','sort']);
        }
    }
}

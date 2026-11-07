<?php

namespace Tests\Feature;

use App\Models\{Cinema, FoodOrder, Invoice, Movie, Payment, Room, Seat, Showtime, Ticket, User};
use App\Services\RevenueReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRevenueReportTest extends TestCase
{
    use RefreshDatabase;

    private function history(): array
    {
        $customer = User::factory()->create();
        $movies = collect(['now_showing', 'inactive'])->map(fn ($status) => Movie::create(['title'=>'Phim '.$status,'duration'=>90,'status'=>$status]));
        $cinemas = collect(['A','B'])->map(fn ($name) => Cinema::create(['name'=>'Rạp '.$name,'address'=>'Test','city'=>'HCM','status'=>'active']));
        $tickets = collect();
        foreach ([[0,0,'confirmed',100000.25,'paid'],[0,1,'used',200000.50,'paid'],[1,0,'cancelled',50000,'refunded'],[1,1,'pending',999999,'failed']] as $i=>[$m,$c,$status,$amount,$paymentStatus]) {
            $room = Room::create(['cinema_id'=>$cinemas[$c]->id,'name'=>'Rạp '.$i,'type'=>'2D','total_seats'=>1]);
            $seat = Seat::create(['room_id'=>$room->id,'seat_row'=>'A','seat_number'=>1,'seat_type'=>'normal','status'=>1,'extra_price'=>0]);
            $show = Showtime::create(['movie_id'=>$movies[$m]->id,'room_id'=>$room->id,'start_time'=>'2026-11-01 10:00:00','end_time'=>'2026-11-01 12:00:00','status'=>'active','base_price'=>70000]);
            $ticket = Ticket::create(['user_id'=>$customer->id,'showtime_id'=>$show->id,'seat_id'=>$seat->id,'price'=>70000,'status'=>$status,'booked_at'=>'2026-09-30 10:00:00']);
            Payment::create(['ticket_id'=>$ticket->id,'amount'=>$amount,'method'=>$c===0?'cash':'momo','status'=>$paymentStatus,'paid_at'=>'2026-10-05 12:00:00']);
            $tickets->push($ticket);
        }
        Payment::create(['ticket_id'=>$tickets[0]->id,'amount'=>999999,'method'=>'momo','status'=>'failed','paid_at'=>'2026-10-05 11:00:00']);
        Invoice::create(['user_id'=>$customer->id,'ticket_id'=>$tickets[0]->id,'total_amount'=>300000.75,'invoice_code'=>'REPORT-ONE']);
        FoodOrder::create(['user_id'=>$customer->id,'ticket_id'=>$tickets[0]->id,'total_price'=>30000,'status'=>'completed']);
        FoodOrder::create(['user_id'=>$customer->id,'ticket_id'=>$tickets[0]->id,'total_price'=>999999,'status'=>'pending']);
        return compact('movies','cinemas','tickets');
    }

    private function filters(array $extra = []): array
    {
        return array_merge(['from'=>'2026-10-01','to'=>'2026-10-31','movie_id'=>null,'cinema_id'=>null],$extra);
    }

    public function test_money_refunds_and_ticket_counts_reconcile_without_invoice_or_combo_duplication(): void
    {
        $this->history();
        $report = app(RevenueReportService::class)->build($this->filters());
        $summary = $report['summary'];
        $this->assertEquals(350000.75,$summary['received']);
        $this->assertEquals(50000,$summary['refunded']);
        $this->assertEquals(300000.75,$summary['revenue']);
        $this->assertEquals(2,$summary['sold_tickets']);
        $this->assertEquals(1,$summary['used_tickets']);
        $this->assertEquals(2,$summary['failed_transactions']);
        $this->assertEquals(0,$summary['booked_tickets']);
        $this->assertEquals(1,$summary['combo_orders']);
        $this->assertEquals(30000,$summary['combo_value']);
        foreach (['movies','cinemas','daily','matrix','methods'] as $key) $this->assertEqualsWithDelta($summary['revenue'],$report[$key]->sum('revenue'),0.001);
        $this->assertSame('inactive',$report['movies'][1]['status']);
    }

    public function test_combined_filters_and_payment_date_fallback_include_archived_movies(): void
    {
        ['movies'=>$movies,'cinemas'=>$cinemas,'tickets'=>$tickets] = $this->history();
        Payment::where('ticket_id',$tickets[2]->id)->update(['paid_at'=>null,'created_at'=>'2026-10-06 14:00:00']);
        $report = app(RevenueReportService::class)->build($this->filters(['movie_id'=>$movies[1]->id,'cinema_id'=>$cinemas[0]->id]));
        $this->assertEquals(50000,$report['summary']['received']);
        $this->assertEquals(50000,$report['summary']['refunded']);
        $this->assertEquals(0,$report['summary']['revenue']);
        $this->assertSame('2026-10-06',$report['daily'][0]->date);
        $this->assertCount(1,$report['movies']);
        $this->assertCount(1,$report['cinemas']);
        $this->assertCount(1,$report['matrix']);
        $this->actingAs(User::factory()->create(['role'=>'manager']))->get(route('admin.reports.index',$this->filters(['movie_id'=>$movies[1]->id,'tab'=>'movies'])))
            ->assertOk()->assertSee('Phim inactive')->assertSee('Đã lưu trữ')->assertSee('Phim tại từng rạp');
    }

    public function test_empty_report_and_pagination_preserve_filters_and_totals(): void
    {
        foreach (range(1,18) as $i) Movie::create(['title'=>sprintf('Phim %02d',$i),'duration'=>90,'status'=>'inactive']);
        $this->actingAs(User::factory()->create(['role'=>'admin']))->get(route('admin.reports.index',$this->filters(['tab'=>'movies','movies_page'=>2])))
            ->assertOk()->assertViewHas('movieRows',fn ($rows) => $rows->currentPage()===2 && $rows->total()===18 && $rows->count()===3 && str_contains($rows->url(1),'from=2026-10-01'))
            ->assertViewHas('report',fn ($report) => $report['summary']['revenue']===0.0 && $report['summary']['combo_orders']===0);
    }

    public function test_report_and_export_require_permission_and_validate_dates(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'staff']));
        $this->get(route('admin.reports.index'))->assertForbidden();
        $this->get(route('admin.reports.export'))->assertForbidden();
        $this->get(route('admin.dashboard'))->assertOk()->assertDontSee(route('admin.reports.index'));
        $this->actingAs(User::factory()->create(['role'=>'manager']));
        $this->get(route('admin.reports.index',['from'=>'2026-10-10','to'=>'2026-10-01']))->assertSessionHasErrors('to');
        $this->get(route('admin.reports.index',['movie_id'=>99999]))->assertSessionHasErrors('movie_id');
        $this->get(route('admin.reports.index',['from'=>'invalid']))->assertSessionHasErrors('from');
    }

    public function test_export_has_seven_sheets_numeric_amounts_and_all_transactions(): void
    {
        ['tickets'=>$tickets] = $this->history();
        for ($i=0;$i<21;$i++) Payment::create(['ticket_id'=>$tickets[0]->id,'amount'=>1.25,'method'=>'cash','status'=>'failed','paid_at'=>'2026-10-05 12:00:00']);
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        $response = $this->get(route('admin.reports.export',$this->filters()))->assertOk()->assertDownload();
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive(); $this->assertTrue($zip->open($path));
        try {
            $workbook = $zip->getFromName('xl/workbook.xml');
            $xml = simplexml_load_string($workbook);
            $this->assertCount(7,$xml->sheets->sheet);
            $this->assertStringContainsString('Chi tiết giao dịch',$workbook);
            $details = simplexml_load_string($zip->getFromName('xl/worksheets/sheet7.xml'));
            $this->assertCount(27,$details->sheetData->row);
            $this->assertStringContainsString('<v>100000.25</v>',$zip->getFromName('xl/worksheets/sheet7.xml'));
            $summary = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringContainsString('<v>300000.75</v>',$summary);
        } finally { $zip->close(); if (is_file($path)) unlink($path); }
    }

    public function test_all_time_link_includes_older_payments(): void
    {
        ['tickets'=>$tickets] = $this->history();
        Payment::where('ticket_id',$tickets[0]->id)->where('status','paid')->update(['paid_at'=>'2024-01-01 10:00:00']);
        $this->actingAs(User::factory()->create(['role'=>'admin']))->get(route('admin.reports.index',['period'=>'all']))->assertOk()
            ->assertViewHas('filters',fn ($filters) => $filters['from']==='2024-01-01');
    }
}

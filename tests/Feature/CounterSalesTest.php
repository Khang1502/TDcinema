<?php

namespace Tests\Feature;

use App\Models\{Cinema, FoodItem, FoodOrder, Invoice, Movie, Payment, PaymentMethod, Promotion, Room, Seat, Showtime, Ticket, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use App\Services\RevenueReportService;
use Tests\TestCase;

class CounterSalesTest extends TestCase
{
    use RefreshDatabase;

    private function show(): array
    {
        $cinema = Cinema::create(['name'=>'Rạp thử '.(Cinema::count()+1),'address'=>'A','city'=>'HCM']);
        $room = Room::create(['cinema_id'=>$cinema->id,'name'=>'Phòng 1','type'=>'2D','total_seats'=>2]);
        $movie = Movie::create(['title'=>'Phim thử','duration'=>90,'status'=>'now_showing']);
        $show = Showtime::create(['movie_id'=>$movie->id,'room_id'=>$room->id,'start_time'=>now()->addDay(), 'end_time'=>now()->addDay()->addHours(2),'base_price'=>70000,'status'=>'active']);
        $seats = collect([1,2])->map(fn ($number) => Seat::create(['room_id'=>$room->id,'seat_row'=>'A','seat_number'=>$number,'seat_type'=>'normal','extra_price'=>$number === 2 ? 10000 : 0,'status'=>1]));
        return [$show, $seats];
    }

    private function promotion(array $changes = []): Promotion
    {
        return Promotion::create(array_replace([
            'name'=>'Khuyến mãi tại quầy', 'code'=>'COUNTER'.(Promotion::count() + 1),
            'type'=>'percent', 'value'=>20, 'start_date'=>today(), 'end_date'=>today()->addWeek(),
            'status'=>'active', 'usage_limit'=>2,
        ], $changes));
    }

    public function test_guest_and_member_counter_sales_are_idempotent_and_use_server_prices(): void
    {
        [$show,$seats] = $this->show();
        $staff = User::factory()->create(['role'=>'staff']);
        $this->actingAs($staff);
        $token = (string) Str::uuid();
        $form = ['showtime_id'=>$show->id,'seat_ids'=>$seats->pluck('id')->all(),'sale_token'=>$token,'price'=>1];
        $this->post(route('admin.tickets.store'),$form)->assertRedirect();
        $this->post(route('admin.tickets.store'),$form)->assertRedirect();
        $this->assertDatabaseCount('tickets',2);
        $this->assertEquals(150000,Payment::sum('amount'));
        $this->assertTrue(Ticket::whereNull('user_id')->where('sold_by',$staff->id)->where('sales_channel','counter')->count() === 2);
        $this->assertSame('POS-'.$token, Ticket::first()->qr_code);
        $this->assertSame(1, Ticket::where('showtime_id', $show->id)->distinct()->count('qr_code'));
        $this->post(route('admin.tickets.store'),$form + ['seat_id'=>$seats[0]->id])->assertRedirect();
        $customer = User::factory()->create(['role'=>'customer']);
        [$otherShow,$otherSeats] = $this->show();
        $this->post(route('admin.tickets.store'),['showtime_id'=>$otherShow->id,'seat_ids'=>[$otherSeats[0]->id], 'user_id'=>$customer->id,'sale_token'=>(string)Str::uuid()])->assertRedirect();
        $this->assertDatabaseHas('tickets',['user_id'=>$customer->id,'sold_by'=>$staff->id,'showtime_id'=>$otherShow->id]);
    }

    public function test_counter_rejects_invalid_showtime_and_held_or_sold_seat(): void
    {
        [$show,$seats] = $this->show();
        $this->actingAs(User::factory()->create(['role'=>'staff']));
        $form = ['showtime_id'=>$show->id,'seat_ids'=>[$seats[0]->id],'sale_token'=>(string)Str::uuid()];
        $this->post(route('admin.tickets.store'),$form)->assertRedirect();
        $this->post(route('admin.tickets.store'),array_replace($form,['sale_token'=>(string)Str::uuid()]))->assertSessionHasErrors('seat_ids');
        $show->update(['status'=>'cancelled']);
        $this->post(route('admin.tickets.store'),array_replace($form,['seat_ids'=>[$seats[1]->id],'sale_token'=>(string)Str::uuid()]))->assertSessionHasErrors('showtime_id');
    }

    public function test_counter_ticket_limits_match_online_seats_and_combo_quantity(): void
    {
        [$show, $seats] = $this->show();
        $show->room->update(['total_seats' => 9]);
        foreach (range(3, 9) as $number) {
            $seats->push(Seat::create([
                'room_id' => $show->room_id, 'seat_row' => 'A', 'seat_number' => $number,
                'seat_type' => 'normal', 'extra_price' => 0, 'status' => 1,
            ]));
        }
        $combo = FoodItem::create(['name' => 'Combo giới hạn', 'price' => 59000, 'status' => 'available']);
        $this->actingAs(User::factory()->create(['role' => 'staff']));

        $this->get(route('admin.tickets.create', ['showtime_id' => $show->id]))
            ->assertOk()->assertSee('data-max-seats="8"', false)->assertSee('max="10"', false);
        $this->post(route('admin.tickets.store'), [
            'showtime_id' => $show->id, 'seat_ids' => $seats->pluck('id')->all(),
            'items' => [$combo->id => 1], 'sale_token' => (string) Str::uuid(),
        ])->assertSessionHasErrors('seat_ids');
        $this->post(route('admin.tickets.store'), [
            'showtime_id' => $show->id, 'seat_ids' => [$seats[0]->id],
            'items' => [$combo->id => 11], 'sale_token' => (string) Str::uuid(),
        ])->assertSessionHasErrors('items.'.$combo->id);
        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('food_orders', 0);

        $this->post(route('admin.tickets.store'), [
            'showtime_id' => $show->id, 'seat_ids' => $seats->take(8)->pluck('id')->all(),
            'items' => [$combo->id => 10], 'sale_token' => (string) Str::uuid(),
        ])->assertRedirect();
        $this->assertDatabaseCount('tickets', 8);
        $this->assertDatabaseHas('food_order_items', ['food_item_id' => $combo->id, 'quantity' => 10]);
    }

    public function test_standalone_food_quantity_uses_online_combo_limit(): void
    {
        $combo = FoodItem::create(['name' => 'Combo giới hạn', 'price' => 59000, 'status' => 'available']);
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->get(route('admin.food-orders.create'))->assertOk()->assertSee('max="10"', false);

        $this->post(route('admin.food-orders.store'), [
            'sale_token' => (string) Str::uuid(), 'items' => [$combo->id => 11],
        ])->assertSessionHasErrors('items.'.$combo->id);
        $this->assertDatabaseCount('food_orders', 0);

        $this->post(route('admin.food-orders.store'), [
            'sale_token' => (string) Str::uuid(), 'items' => [$combo->id => 10],
        ])->assertRedirect();
        $this->assertDatabaseHas('food_order_items', ['food_item_id' => $combo->id, 'quantity' => 10]);
    }

    public function test_counter_can_sell_optional_combo_with_tickets_once_and_use_server_prices(): void
    {
        [$show, $seats] = $this->show();
        $combo = FoodItem::create(['name'=>'Combo bắp nước','price'=>59000,'status'=>'available']);
        $staff = User::factory()->create(['role'=>'staff']);
        $this->actingAs($staff);
        $form = [
            'showtime_id'=>$show->id, 'seat_ids'=>$seats->pluck('id')->all(),
            'customer_type'=>'guest', 'items'=>[$combo->id=>2],
            'sale_token'=>(string) Str::uuid(), 'total_price'=>1,
        ];
        $this->post(route('admin.tickets.store'), $form)->assertRedirect();
        $this->post(route('admin.tickets.store'), $form)->assertRedirect();

        $this->assertDatabaseCount('tickets', 2);
        $this->assertDatabaseCount('food_orders', 1);
        $order = FoodOrder::firstOrFail();
        $this->assertSame(Ticket::first()->id, $order->ticket_id);
        $this->assertSame($staff->id, $order->sold_by);
        $this->assertDatabaseHas('food_order_items', ['food_order_id'=>$order->id, 'food_item_id'=>$combo->id, 'quantity'=>2, 'price'=>59000]);
        $this->assertEquals(118000, $order->payment->amount);
        $this->assertEquals(268000, Invoice::first()->total_amount);
        $report = app(RevenueReportService::class)->build(['from'=>now()->toDateString(),'to'=>now()->toDateString(),'movie_id'=>null,'cinema_id'=>null]);
        $this->assertEquals(150000, $report['summary']['revenue']);
        $this->assertEquals(118000, $report['summary']['food_revenue']);
        $this->assertEquals(268000, $report['summary']['total_revenue']);
        $this->get(route('admin.tickets.show', Ticket::first()))
            ->assertOk()
            ->assertSee('Combo bắp nước')
            ->assertSee('268.000')
            ->assertSeeInOrder(['ticket-detail__topbar', 'Quay về Quản lý vé', 'ticket-detail__header'], false)
            ->assertSee('Check-in cả lượt (2 ghế)')
            ->assertDontSee('ticket-booking-title');
    }

    public function test_counter_rejects_unavailable_combo_without_creating_tickets(): void
    {
        [$show, $seats] = $this->show();
        $combo = FoodItem::create(['name'=>'Combo hết hàng','price'=>59000,'status'=>'unavailable']);
        $this->actingAs(User::factory()->create(['role'=>'staff']));
        $this->post(route('admin.tickets.store'), [
            'showtime_id'=>$show->id, 'seat_ids'=>[$seats[0]->id],
            'items'=>[$combo->id=>1], 'sale_token'=>(string) Str::uuid(),
        ])->assertSessionHasErrors('items');
        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('food_orders', 0);
    }

    public function test_member_counter_sale_applies_existing_discount_and_awards_stars_once(): void
    {
        [$show,$seats] = $this->show();
        $member = User::factory()->create(['role'=>'customer']);
        $member->forceFill(['points'=>3000])->save();
        $this->actingAs(User::factory()->create(['role'=>'staff']));
        $form = ['showtime_id'=>$show->id,'seat_ids'=>[$seats[0]->id],'user_id'=>$member->id,'sale_token'=>(string)Str::uuid()];
        $this->post(route('admin.tickets.store'),$form)->assertRedirect();
        $this->post(route('admin.tickets.store'),$form)->assertRedirect();
        $this->assertEquals(63000,Ticket::first()->price);
        $this->assertEquals(63000,Payment::first()->amount);
        $this->assertEquals(3063,$member->fresh()->points);
    }

    public function test_member_promotion_list_only_contains_available_and_eligible_codes(): void
    {
        [$show] = $this->show();
        $member = User::factory()->create(['role'=>'customer']);
        $member->forceFill(['points'=>3000])->save();
        $general = $this->promotion(['code'=>'GENERAL']);
        $gift = $this->promotion(['code'=>'MEMBERGIFT', 'is_member_gift'=>true, 'min_member_points'=>1000]);
        $locked = $this->promotion(['code'=>'LOCKEDGIFT', 'is_member_gift'=>true, 'min_member_points'=>5000]);
        $expired = $this->promotion(['code'=>'EXPIRED', 'end_date'=>today()->subDay()]);
        $exhausted = $this->promotion(['code'=>'EXHAUSTED', 'usage_limit'=>0]);
        $future = $this->promotion(['code'=>'FUTURE', 'start_date'=>today()->addDay()]);
        $this->actingAs(User::factory()->create(['role'=>'staff']));

        $url = route('admin.tickets.member-promotions', ['user_id'=>$member->id]);
        $this->getJson($url)->assertOk()->assertJsonCount(2, 'promotions')
            ->assertJsonFragment(['code'=>$general->code])->assertJsonFragment(['code'=>$gift->code])
            ->assertJsonMissing(['code'=>$locked->code])->assertJsonMissing(['code'=>$expired->code])
            ->assertJsonMissing(['code'=>$exhausted->code])->assertJsonMissing(['code'=>$future->code])
            ->assertJsonPath('membership.discount_percent', 10);

        foreach ([$general, $gift] as $promotion) $promotion->update(['status'=>'expired']);
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'promotions');
        $this->get(route('admin.tickets.create', ['showtime_id'=>$show->id]))
            ->assertOk()->assertSee('Không có khuyến mãi nào khả dụng.');
        $this->actingAs(User::factory()->create(['role'=>'customer']))->getJson($url)->assertForbidden();
    }

    public function test_counter_promotion_replaces_member_discount_and_discounts_tickets_and_combo_once(): void
    {
        [$show, $seats] = $this->show();
        $combo = FoodItem::create(['name'=>'Combo thử', 'price'=>50000, 'status'=>'available']);
        $member = User::factory()->create(['role'=>'customer']);
        $member->forceFill(['points'=>3000])->save();
        $promotion = $this->promotion(['code'=>'SAVE20', 'usage_limit'=>1]);
        $this->actingAs(User::factory()->create(['role'=>'staff']));
        $form = ['showtime_id'=>$show->id, 'seat_ids'=>$seats->pluck('id')->all(),
            'customer_type'=>'member', 'user_id'=>$member->id, 'promo_code'=>$promotion->code,
            'items'=>[$combo->id=>1], 'sale_token'=>(string) Str::uuid(), 'total_price'=>1];

        $this->post(route('admin.tickets.store'), $form)->assertRedirect();
        $this->post(route('admin.tickets.store'), $form)->assertRedirect();
        $this->assertDatabaseCount('invoices', 1);
        $this->assertEquals(160000, Invoice::first()->total_amount);
        $this->assertSame($promotion->id, Invoice::first()->promotion_id);
        $this->assertEquals(0, Invoice::first()->membership_discount);
        $this->assertEquals(120000, Ticket::sum('price'));
        $this->assertEquals(40000, FoodOrder::first()->total_price);
        $this->assertEquals(160000, Payment::sum('amount'));
        $this->assertSame(0, $promotion->fresh()->usage_limit);
        $this->assertSame(3160, $member->fresh()->points);
        $this->get(route('admin.tickets.show', Ticket::first()))->assertOk()->assertSee('SAVE20');
    }

    public function test_counter_rejects_member_promotion_that_is_ineligible_or_exhausted(): void
    {
        [$show, $seats] = $this->show();
        $member = User::factory()->create(['role'=>'customer']);
        $promotion = $this->promotion(['code'=>'POINTS100', 'is_member_gift'=>true, 'min_member_points'=>100]);
        $this->actingAs(User::factory()->create(['role'=>'staff']));
        $form = ['showtime_id'=>$show->id, 'seat_ids'=>[$seats[0]->id],
            'customer_type'=>'member', 'user_id'=>$member->id, 'promo_code'=>$promotion->code,
            'sale_token'=>(string) Str::uuid()];
        $this->post(route('admin.tickets.store'), $form)->assertSessionHasErrors('promo_code');
        $member->forceFill(['points'=>100])->save();
        $promotion->update(['usage_limit'=>0]);
        $this->post(route('admin.tickets.store'), $form)->assertSessionHasErrors('promo_code');
        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_counter_respects_online_hold_and_preserves_published_showtime_with_tickets(): void
    {
        [$show,$seats] = $this->show();
        $staff = User::factory()->create(['role'=>'staff']);
        $this->actingAs($staff);
        Cache::put('booking-hold:'.$show->id.':A1', ['token'=>'other', 'expires_at'=>now()->addMinutes(5)->timestamp], now()->addMinutes(5));
        $this->post(route('admin.tickets.store'), ['showtime_id'=>$show->id,'seat_ids'=>[$seats[0]->id],'sale_token'=>(string)Str::uuid()])->assertSessionHasErrors('seat_ids');
        Cache::forget('booking-hold:'.$show->id.':A1');
        $this->post(route('admin.tickets.store'), ['showtime_id'=>$show->id,'seat_ids'=>[$seats[0]->id],'sale_token'=>(string)Str::uuid()])->assertRedirect();
        $this->actingAs(User::factory()->create(['role'=>'admin']))->put(route('admin.showtimes.update', $show), [
            'movie_id'=>$show->movie_id,'room_id'=>$show->room_id,'start_time'=>$show->start_time->format('Y-m-d\TH:i'),
            'end_time'=>$show->end_time->format('Y-m-d\TH:i'),'base_price'=>90000,'status'=>'active',
        ])->assertSessionHasErrors('showtime');
        $this->assertEquals(70000,$show->fresh()->base_price);
    }

    public function test_standalone_food_sale_is_paid_once_and_fulfilled_once(): void
    {
        $water = FoodItem::create(['name'=>'Nước suối','price'=>12000,'status'=>'available']);
        $popcorn = FoodItem::create(['name'=>'Bắp','price'=>35000,'status'=>'available']);
        $staff = User::factory()->create(['role'=>'staff']);
        $this->actingAs($staff);
        $form = ['sale_token'=>(string)Str::uuid(),'items'=>[$water->id=>2,$popcorn->id=>1],'total_price'=>1];
        $this->post(route('admin.food-orders.store'),$form)->assertRedirect();
        $this->post(route('admin.food-orders.store'),$form)->assertRedirect();
        $order = FoodOrder::firstOrFail();
        $this->assertDatabaseCount('food_orders',1);
        $this->assertNull($order->ticket_id);
        $this->assertNull($order->user_id);
        $this->assertEquals(59000,$order->total_price);
        $this->assertEquals(59000,$order->payment->amount);
        $this->assertSame(2,$order->items()->count());
        $report = app(RevenueReportService::class)->build(['from'=>now()->toDateString(),'to'=>now()->toDateString(),'movie_id'=>null,'cinema_id'=>null]);
        $this->assertEquals(59000,$report['summary']['food_revenue']);
        $this->assertEquals(59000,$report['summary']['total_revenue']);
        $this->assertEquals(0,$report['summary']['revenue']);
        $this->post(route('admin.food-orders.fulfill',$order))->assertSessionHas('success');
        $this->post(route('admin.food-orders.fulfill',$order))->assertSessionHas('error');
        $this->assertNotNull($order->fresh()->fulfilled_at);
        $popcorn->update(['status'=>'unavailable']);
        $this->post(route('admin.food-orders.store'),['sale_token'=>(string)Str::uuid(),'items'=>[$popcorn->id=>1]])->assertSessionHasErrors('items');
    }

    public function test_food_management_combines_all_channels_and_checkin_is_separate_from_ticket(): void
    {
        [$show, $seats] = $this->show();
        $staff = User::factory()->create(['role' => 'staff']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = FoodItem::create(['name' => 'Bắp thử', 'price' => 35000, 'status' => 'available']);
        $ticket = Ticket::create([
            'user_id' => $customer->id, 'showtime_id' => $show->id, 'seat_id' => $seats[0]->id,
            'price' => 70000, 'status' => 'confirmed', 'booked_at' => now(),
        ]);
        Payment::create(['ticket_id' => $ticket->id, 'amount' => 105000, 'method' => 'momo', 'status' => 'paid', 'paid_at' => now()]);
        $online = FoodOrder::create([
            'user_id' => $customer->id, 'ticket_id' => $ticket->id,
            'total_price' => 35000, 'pickup_code' => 'FONLINE', 'status' => 'completed',
        ]);
        $online->items()->create(['food_item_id' => $product->id, 'quantity' => 1, 'price' => 35000]);

        $counterTicket = Ticket::create([
            'user_id' => null, 'sold_by' => $staff->id, 'sales_channel' => 'counter',
            'showtime_id' => $show->id, 'seat_id' => $seats[1]->id,
            'price' => 80000, 'status' => 'confirmed', 'booked_at' => now(),
        ]);
        $counterWithTicket = FoodOrder::create([
            'user_id' => null, 'sold_by' => $staff->id, 'ticket_id' => $counterTicket->id,
            'total_price' => 35000, 'pickup_code' => 'FWITH', 'status' => 'completed',
        ]);
        Payment::create(['food_order_id' => $counterWithTicket->id, 'amount' => 35000, 'method' => 'cash', 'status' => 'paid', 'paid_at' => now()]);
        $standalone = FoodOrder::create([
            'user_id' => null, 'sold_by' => $staff->id,
            'total_price' => 35000, 'pickup_code' => 'FONLY', 'status' => 'completed',
        ]);
        Payment::create(['food_order_id' => $standalone->id, 'amount' => 35000, 'method' => 'cash', 'status' => 'paid', 'paid_at' => now()]);
        $unpaid = FoodOrder::create([
            'user_id' => null, 'sold_by' => $staff->id,
            'total_price' => 35000, 'pickup_code' => 'FUNPAID', 'status' => 'completed',
        ]);

        $this->actingAs($staff);
        $this->get(route('admin.food-orders.index'))->assertOk()
            ->assertSee('FONLINE')->assertSee('FWITH')->assertSee('FONLY')->assertDontSee('FUNPAID');
        $this->get(route('admin.food-orders.index', ['view' => 'all', 'channel' => 'online']))
            ->assertOk()->assertSee('FONLINE')->assertDontSee('FWITH')->assertDontSee('FONLY');
        $this->get(route('admin.food-orders.index', ['view' => 'all', 'channel' => 'counter_ticket']))
            ->assertOk()->assertSee('FWITH')->assertDontSee('FONLINE')->assertDontSee('FONLY');
        $this->get(route('admin.food-orders.index', ['view' => 'all', 'q' => 'FONLY']))
            ->assertOk()->assertSee('FONLY')->assertDontSee('FONLINE');
        $this->get(route('admin.food-orders.show', $online))->assertOk()
            ->assertSee('Bắp thử')->assertSee('Check-in toàn bộ 1 phần còn lại');

        $this->post(route('admin.food-orders.fulfill', $unpaid))->assertSessionHas('error');
        $this->post(route('admin.food-orders.items.fulfill', [$online, $online->items()->firstOrFail()]), ['quantity' => 1])
            ->assertSessionHas('success');
        $this->post(route('admin.food-orders.fulfill', $online))->assertSessionHas('error');
        $this->assertNotNull($online->fresh()->fulfilled_at);
        $this->assertSame('confirmed', $ticket->fresh()->status);
        $this->get(route('admin.food-orders.index', ['view' => 'history']))
            ->assertOk()->assertSee('FONLINE')->assertDontSee('FWITH');
    }

    public function test_food_combos_can_be_checked_in_separately_then_all_remaining_in_one_order(): void
    {
        $popcorn = FoodItem::create(['name' => 'Combo bắp', 'price' => 35000, 'status' => 'available']);
        $drink = FoodItem::create(['name' => 'Combo nước', 'price' => 22000, 'status' => 'available']);
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->post(route('admin.food-orders.store'), [
            'sale_token' => (string) Str::uuid(),
            'items' => [$popcorn->id => 2, $drink->id => 1],
        ])->assertRedirect();
        $order = FoodOrder::with('items')->firstOrFail();
        $popcornLine = $order->items->firstWhere('food_item_id', $popcorn->id);
        $drinkLine = $order->items->firstWhere('food_item_id', $drink->id);

        $this->get(route('admin.food-orders.show', $order))->assertOk()
            ->assertSee('Đã giao 0/2')->assertSee('Check-in toàn bộ 3 phần còn lại');
        $this->post(route('admin.food-orders.items.fulfill', [$order, $popcornLine]), ['quantity' => 1])
            ->assertSessionHas('success');
        $this->assertSame(1, $popcornLine->fresh()->fulfilled_quantity);
        $this->assertNull($order->fresh()->fulfilled_at);
        $this->get(route('admin.food-orders.index'))->assertOk()->assertSee('Đã giao 1/3 phần');

        $this->post(route('admin.food-orders.items.fulfill', [$order, $popcornLine]), ['quantity' => 2])
            ->assertSessionHas('error');
        $this->assertSame(1, $popcornLine->fresh()->fulfilled_quantity);
        $this->post(route('admin.food-orders.fulfill', $order))->assertSessionHas('success');
        $this->assertSame(2, $popcornLine->fresh()->fulfilled_quantity);
        $this->assertSame(1, $drinkLine->fresh()->fulfilled_quantity);
        $this->assertNotNull($popcornLine->fresh()->fulfilled_at);
        $this->assertNotNull($order->fresh()->fulfilled_at);
        $this->post(route('admin.food-orders.items.fulfill', [$order, $drinkLine]), ['quantity' => 1])
            ->assertSessionHas('error');
        $this->post(route('admin.food-orders.fulfill', $order))->assertSessionHas('error');
    }

    public function test_last_individual_combo_checkin_completes_order_and_rejects_other_orders_item(): void
    {
        $item = FoodItem::create(['name' => 'Combo thử', 'price' => 30000, 'status' => 'available']);
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        foreach (range(1, 2) as $number) {
            $this->post(route('admin.food-orders.store'), [
                'sale_token' => (string) Str::uuid(), 'items' => [$item->id => 1],
            ])->assertRedirect();
        }
        [$first, $second] = FoodOrder::with('items')->orderBy('id')->get()->all();
        $this->post(route('admin.food-orders.items.fulfill', [$first, $second->items->first()]), ['quantity' => 1])
            ->assertNotFound();
        $this->assertNull($first->fresh()->fulfilled_at);
        $this->post(route('admin.food-orders.items.fulfill', [$first, $first->items->first()]), ['quantity' => 1])
            ->assertSessionHas('success');
        $this->assertNotNull($first->fresh()->fulfilled_at);
        $this->assertNull($second->fresh()->fulfilled_at);
    }

    public function test_food_sale_requires_permission(): void
    {
        $item = FoodItem::create(['name'=>'Nước','price'=>10000,'status'=>'available']);
        $order = FoodOrder::create(['user_id' => null, 'total_price' => 10000, 'pickup_code' => 'FNOACCESS', 'status' => 'completed']);
        $orderItem = $order->items()->create(['food_item_id' => $item->id, 'quantity' => 1, 'price' => 10000]);
        $this->actingAs(User::factory()->create(['role'=>'customer']))->get(route('admin.food-orders.index'))->assertForbidden();
        $this->get(route('admin.food-orders.show', $order))->assertForbidden();
        $this->post(route('admin.food-orders.fulfill', $order))->assertForbidden();
        $this->post(route('admin.food-orders.items.fulfill', [$order, $orderItem]), ['quantity' => 1])->assertForbidden();
        $this->actingAs(User::factory()->create(['role'=>'customer']))->post(route('admin.food-orders.store'),['sale_token'=>(string)Str::uuid(),'items'=>[$item->id=>1]])->assertForbidden();
        $this->assertDatabaseCount('food_orders',1);
    }

    public function test_single_drink_popcorn_and_combo_can_each_be_sold_without_a_ticket(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'staff']));
        foreach (['Nước suối'=>12000,'Bắp rang'=>35000,'Combo bắp nước'=>59000] as $name=>$price) {
            $product = FoodItem::create(['name'=>$name,'price'=>$price,'status'=>'available']);
            $this->post(route('admin.food-orders.store'),['sale_token'=>(string)Str::uuid(),'items'=>[$product->id=>1]])->assertRedirect();
            $this->assertDatabaseHas('food_order_items',['food_item_id'=>$product->id,'quantity'=>1,'price'=>$price]);
        }
        $this->assertSame(3,FoodOrder::whereNull('ticket_id')->count());
        PaymentMethod::where('code','cash')->update(['is_enabled'=>false]);
        $this->post(route('admin.food-orders.store'),['sale_token'=>(string)Str::uuid(),'items'=>[$product->id=>1]])->assertSessionHas('error');
        $this->assertDatabaseCount('food_orders',3);
    }

    public function test_staff_can_open_counter_pages_and_receipt(): void
    {
        [$show] = $this->show();
        FoodItem::create(['name'=>'Nước suối','price'=>12000,'status'=>'available']);
        $this->actingAs(User::factory()->create(['role'=>'staff']));
        $this->get(route('admin.tickets.create', ['showtime_id'=>$show->id]))
            ->assertOk()->assertSee('Bán vé tại quầy')->assertSee('1. Khách vãng lai')
            ->assertSee('2. Thành viên')->assertSee('data-filter="movie"', false)
            ->assertSee('data-filter="cinema"', false)->assertSee('data-filter="date"', false)
            ->assertSee('data-movie-search', false)
            ->assertSeeInOrder(['data-movie-search', 'data-movie-menu'], false)
            ->assertDontSee('data-movie-trigger', false)
            ->assertSee('counter-seat-map')->assertSee('data-member-input', false);
        $this->get(route('admin.food-orders.create'))->assertOk()->assertSee('Nước suối');
        $this->get(route('admin.food-orders.index'))->assertOk()->assertSee('Bán tại quầy')->assertSee('Lịch sử bắp nước');
    }

    public function test_counter_showtime_picker_only_returns_now_showing_movies_and_sellable_sessions(): void
    {
        [$show, $seats] = $this->show();
        $room = $show->room;
        $upcomingMovie = Movie::create(['title'=>'Phim chưa chiếu', 'duration'=>90, 'status'=>'upcoming']);
        $upcomingShow = Showtime::create(['movie_id'=>$upcomingMovie->id, 'room_id'=>$room->id,
            'start_time'=>now()->addDays(2), 'end_time'=>now()->addDays(2)->addHours(2), 'base_price'=>70000, 'status'=>'active']);
        $emptyMovie = Movie::create(['title'=>'Phim không có suất', 'duration'=>90, 'status'=>'now_showing']);
        $farMovie = Movie::create(['title'=>'Phim chỉ có lịch tháng sau', 'duration'=>90, 'status'=>'now_showing']);
        $farShow = Showtime::create(['movie_id'=>$farMovie->id, 'room_id'=>$room->id,
            'start_time'=>now()->addDays(8), 'end_time'=>now()->addDays(8)->addHours(2), 'base_price'=>70000, 'status'=>'active']);
        $cancelled = Showtime::create(['movie_id'=>$show->movie_id, 'room_id'=>$room->id,
            'start_time'=>now()->addDay()->addHour(), 'end_time'=>now()->addDay()->addHours(3), 'base_price'=>70000, 'status'=>'cancelled']);
        Showtime::create(['movie_id'=>$show->movie_id, 'room_id'=>$room->id,
            'start_time'=>now()->subHour(), 'end_time'=>now()->addHour(), 'base_price'=>70000, 'status'=>'active']);

        $this->actingAs(User::factory()->create(['role'=>'staff']));
        $this->get(route('admin.tickets.create'))->assertOk()
            ->assertSee($show->movie->title)->assertDontSee($upcomingMovie->title)
            ->assertDontSee($emptyMovie->title)->assertDontSee($farMovie->title);
        $this->get(route('admin.tickets.create', ['showtime_id'=>$upcomingShow->id]))
            ->assertOk()->assertDontSee('counter-showtime-summary', false);
        $this->get(route('admin.tickets.create', ['showtime_id'=>$cancelled->id]))
            ->assertOk()->assertDontSee('counter-showtime-summary', false);
        $this->get(route('admin.tickets.create', ['showtime_id'=>$farShow->id]))
            ->assertOk()->assertDontSee('counter-showtime-summary', false);
        $this->post(route('admin.tickets.store'), ['showtime_id'=>$farShow->id, 'seat_ids'=>[$seats[0]->id],
            'sale_token'=>(string) Str::uuid()])->assertSessionHasErrors('showtime_id');

        $url = route('admin.tickets.showtime-options');
        $this->getJson($url.'?'.http_build_query(['stage'=>'cinemas', 'movie_id'=>$show->movie_id]))
            ->assertOk()->assertJsonCount(1, 'options')->assertJsonPath('options.0.value', $room->cinema_id);
        $this->getJson($url.'?'.http_build_query(['stage'=>'rooms', 'movie_id'=>$show->movie_id, 'cinema_id'=>$room->cinema_id]))
            ->assertOk()->assertJsonCount(1, 'options')->assertJsonPath('options.0.value', $room->id);
        $this->getJson($url.'?'.http_build_query(['stage'=>'dates', 'movie_id'=>$show->movie_id,
            'cinema_id'=>$room->cinema_id, 'room_id'=>$room->id]))
            ->assertOk()->assertJsonCount(1, 'options');
        $this->getJson($url.'?'.http_build_query(['stage'=>'showtimes', 'movie_id'=>$show->movie_id,
            'cinema_id'=>$room->cinema_id, 'room_id'=>$room->id, 'date'=>$show->start_time->format('Y-m-d')]))
            ->assertOk()->assertJsonCount(1, 'options')->assertJsonPath('options.0.value', $show->id);
        $this->getJson($url.'?'.http_build_query(['stage'=>'cinemas', 'movie_id'=>$upcomingMovie->id]))
            ->assertOk()->assertJsonCount(0, 'options');
        $this->actingAs(User::factory()->create(['role'=>'customer']))
            ->getJson($url.'?'.http_build_query(['stage'=>'cinemas', 'movie_id'=>$show->movie_id]))->assertForbidden();
    }

    public function test_selected_counter_showtime_displays_movie_poster_and_screening_details(): void
    {
        [$show] = $this->show();
        $show->movie->update([
            'poster_path' => 'https://example.test/poster.jpg',
            'genre' => 'Hành động',
            'age_rating' => 'T13',
            'description' => 'Nội dung phim thử.',
            'director' => 'Đạo diễn thử',
        ]);

        $this->actingAs(User::factory()->create(['role'=>'staff']))
            ->get(route('admin.tickets.create', ['showtime_id' => $show->id]))
            ->assertOk()
            ->assertSee('counter-showtime-summary__poster', false)
            ->assertSee('src="https://example.test/poster.jpg"', false)
            ->assertSee('alt="Poster phim Phim thử"', false)
            ->assertSee('Hành động')
            ->assertSee('T13')
            ->assertSee('90 phút')
            ->assertSee('Suất chiếu</dt>', false)
            ->assertSee($show->room->cinema->name)
            ->assertSee($show->start_time->format('d/m/Y'))
            ->assertSee($show->start_time->format('H:i'))
            ->assertDontSee('Nội dung phim thử.')
            ->assertDontSee('Đạo diễn thử')
            ->assertDontSee('Phòng chiếu</dt>', false)
            ->assertDontSee('Giá vé cơ bản</dt>', false);
    }

    public function test_member_suggestions_are_limited_to_active_customers_and_require_ticket_permission(): void
    {
        $staff = User::factory()->create(['role'=>'staff']);
        $match = User::factory()->create(['role'=>'customer','name'=>'Nguyễn An','email'=>'an.member@example.test','phone'=>'0901234567']);
        $blocked = User::factory()->create(['role'=>'customer','name'=>'Nguyễn Bình','status'=>'blocked']);
        $inactive = User::factory()->create(['role'=>'customer','name'=>'Nguyễn Chi','is_active'=>false]);
        $manager = User::factory()->create(['role'=>'manager','name'=>'Nguyễn Quản lý']);

        $this->actingAs($staff)
            ->getJson(route('admin.tickets.member-search', ['q'=>'Nguyễn']))
            ->assertOk()->assertJsonPath('members.0.id', $match->id)
            ->assertJsonMissing(['id'=>$blocked->id])
            ->assertJsonMissing(['id'=>$inactive->id])
            ->assertJsonMissing(['id'=>$manager->id]);
        $this->getJson(route('admin.tickets.member-search', ['q'=>'090123']))
            ->assertOk()->assertJsonPath('members.0.id', $match->id);
        $this->getJson(route('admin.tickets.member-search', ['q'=>'N']))
            ->assertOk()->assertJsonCount(0, 'members');

        User::factory()->count(10)->create(['role'=>'customer','name'=>'Nguyễn Thêm']);
        $this->getJson(route('admin.tickets.member-search', ['q'=>'Nguyễn']))
            ->assertOk()->assertJsonCount(8, 'members');
        $this->actingAs(User::factory()->create(['role'=>'customer']))
            ->getJson(route('admin.tickets.member-search', ['q'=>'Nguyễn']))->assertForbidden();
    }

    public function test_member_choice_requires_a_valid_account_and_guest_choice_ignores_member_id(): void
    {
        [$show, $seats] = $this->show();
        $member = User::factory()->create(['role'=>'customer']);
        $blocked = User::factory()->create(['role'=>'customer', 'status'=>'blocked']);
        $this->actingAs(User::factory()->create(['role'=>'staff']));

        $this->post(route('admin.tickets.store'), [
            'showtime_id'=>$show->id, 'seat_ids'=>[$seats[0]->id],
            'customer_type'=>'member', 'sale_token'=>(string) Str::uuid(),
        ])->assertSessionHasErrors('user_id');

        $this->post(route('admin.tickets.store'), [
            'showtime_id'=>$show->id, 'seat_ids'=>[$seats[0]->id],
            'customer_type'=>'member', 'user_id'=>$blocked->id, 'sale_token'=>(string) Str::uuid(),
        ])->assertSessionHasErrors('user_id');

        $this->post(route('admin.tickets.store'), [
            'showtime_id'=>$show->id, 'seat_ids'=>[$seats[0]->id],
            'customer_type'=>'guest', 'user_id'=>$member->id, 'sale_token'=>(string) Str::uuid(),
        ])->assertRedirect();
        $this->assertDatabaseHas('tickets', ['seat_id'=>$seats[0]->id, 'user_id'=>null]);
    }
}

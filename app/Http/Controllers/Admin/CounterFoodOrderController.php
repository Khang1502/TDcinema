<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FoodItem;
use App\Models\FoodOrder;
use App\Models\FoodOrderItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CounterFoodOrderController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'view' => ['nullable', 'in:ready,all,history'],
            'channel' => ['nullable', 'in:online,counter_ticket,counter_only'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $filters = [
            'q' => trim($data['q'] ?? ''),
            'view' => $data['view'] ?? 'ready',
            'channel' => $data['channel'] ?? '',
            'from' => $data['from'] ?? '',
            'to' => $data['to'] ?? '',
        ];

        $base = FoodOrder::query();
        if ($filters['q'] !== '') {
            $term = $filters['q'];
            $base->where(function ($query) use ($term) {
                $query->where('pickup_code', 'like', '%'.$term.'%')
                    ->orWhereHas('user', fn ($users) => $users->where('name', 'like', '%'.$term.'%')
                        ->orWhere('email', 'like', '%'.$term.'%')
                        ->orWhere('phone', 'like', '%'.$term.'%'))
                    ->orWhereHas('items.foodItem', fn ($items) => $items->where('name', 'like', '%'.$term.'%'))
                    ->orWhereHas('ticket.showtime.movie', fn ($movies) => $movies->where('title', 'like', '%'.$term.'%'));
                if (ctype_digit($term)) $query->orWhereKey((int) $term);
            });
        }
        if ($filters['channel'] === 'online') {
            $base->whereNull('sold_by')->whereNotNull('ticket_id');
        } elseif ($filters['channel'] === 'counter_ticket') {
            $base->whereNotNull('sold_by')->whereNotNull('ticket_id');
        } elseif ($filters['channel'] === 'counter_only') {
            $base->whereNull('ticket_id');
        }
        if ($filters['from']) $base->whereDate('created_at', '>=', $filters['from']);
        if ($filters['to']) $base->whereDate('created_at', '<=', $filters['to']);

        $counts = [
            'all' => (clone $base)->count(),
            'ready' => (clone $base)->readyForPickup()->count(),
            'history' => (clone $base)->where(fn ($query) => $query->whereNotNull('fulfilled_at')->orWhere('status', 'cancelled'))->count(),
            'online' => (clone $base)->whereNull('sold_by')->whereNotNull('ticket_id')->count(),
        ];
        $orders = (clone $base)->with(['items.foodItem', 'payment', 'ticket.payment', 'ticket.showtime.movie', 'ticket.showtime.room.cinema', 'user', 'seller']);
        if ($filters['view'] === 'ready') {
            $orders->readyForPickup();
        } elseif ($filters['view'] === 'history') {
            $orders->where(fn ($query) => $query->whereNotNull('fulfilled_at')->orWhere('status', 'cancelled'));
        }
        return view('admin.food-orders.index', [
            'orders' => $orders->orderByDesc('created_at')->orderByDesc('id')->paginate(15)->withQueryString(),
            'counts' => $counts,
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        return view('admin.food-orders.create', [
            'products' => FoodItem::where('status', 'available')->orderBy('name')->get(),
            'saleToken' => old('sale_token', (string) Str::uuid()),
        ]);
    }

    public function store(Request $request)
    {
        $maxFoodQuantity = (int) config('booking_limits.quantity_per_food_item');
        $data = $request->validate([
            'sale_token' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['nullable', 'integer', 'min:0', 'max:'.$maxFoodQuantity],
        ], [
            'items.*.max' => 'Mỗi combo hoặc sản phẩm chỉ được chọn tối đa '.$maxFoodQuantity.' phần.',
        ]);
        $quantities = collect($data['items'])->filter(fn ($quantity) => (int) $quantity > 0);
        if ($quantities->isEmpty()) throw ValidationException::withMessages(['items' => 'Chọn ít nhất một sản phẩm.']);
        if (! PaymentMethod::where('code', 'cash')->where('is_enabled', true)->exists()) {
            return back()->withInput()->with('error', 'Thanh toán tiền mặt tại quầy đang tạm dừng.');
        }
        $orderId = DB::transaction(function () use ($data, $quantities, $request) {
            $existing = FoodOrder::where('sale_token', $data['sale_token'])->first();
            if ($existing) return $existing->id;
            $products = FoodItem::whereIn('id', $quantities->keys())->where('status', 'available')->lockForUpdate()->get()->keyBy('id');
            if ($products->count() !== $quantities->count()) throw ValidationException::withMessages(['items' => 'Có sản phẩm đã ngừng bán. Vui lòng tải lại trang.']);
            $total = $quantities->reduce(fn ($sum, $quantity, $id) => $sum + (float) $products[$id]->price * (int) $quantity, 0);
            $order = FoodOrder::create(['user_id' => null, 'ticket_id' => null, 'sold_by' => $request->user()->id,
                'sale_token' => $data['sale_token'], 'total_price' => $total, 'status' => 'completed']);
            $order->update(['pickup_code' => 'F'.$order->id]);
            foreach ($quantities as $id => $quantity) {
                $order->items()->create(['food_item_id' => $id, 'quantity' => (int) $quantity, 'price' => $products[$id]->price]);
            }
            Payment::create(['food_order_id' => $order->id, 'amount' => $total, 'method' => 'cash', 'status' => 'paid', 'paid_at' => now()]);
            return $order->id;
        });
        return redirect()->route('admin.food-orders.show', $orderId)->with('success', 'Đã thu tiền và tạo đơn bắp nước.');
    }

    public function show(FoodOrder $foodOrder)
    {
        return view('admin.food-orders.show', [
            'order' => $foodOrder->load(['items.foodItem', 'payment', 'ticket.payment', 'ticket.showtime.movie', 'ticket.showtime.room.cinema', 'ticket.seat', 'user', 'seller']),
        ]);
    }

    public function fulfill(FoodOrder $foodOrder)
    {
        $updated = DB::transaction(function () use ($foodOrder) {
            $order = FoodOrder::with(['payment', 'ticket.payment'])->whereKey($foodOrder->id)->lockForUpdate()->firstOrFail();
            if (! $order->isReadyForPickup()) return false;

            $now = now();
            FoodOrderItem::where('food_order_id', $order->id)
                ->whereColumn('fulfilled_quantity', '<', 'quantity')
                ->update(['fulfilled_quantity' => DB::raw('quantity'), 'fulfilled_at' => $now]);
            $order->update(['fulfilled_at' => $now]);
            return true;
        });

        return back()->with($updated ? 'success' : 'error', $updated
            ? 'Đã check-in toàn bộ bắp nước còn lại trong lượt đặt.'
            : 'Đơn đã giao hoặc chưa thanh toán.');
    }

    public function fulfillItem(Request $request, FoodOrder $foodOrder, FoodOrderItem $item)
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1']]);
        $updated = DB::transaction(function () use ($foodOrder, $item, $data) {
            $order = FoodOrder::with(['payment', 'ticket.payment'])->whereKey($foodOrder->id)->lockForUpdate()->firstOrFail();
            $orderItem = FoodOrderItem::where('food_order_id', $order->id)
                ->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $quantity = (int) $data['quantity'];
            if (! $order->isReadyForPickup() || $quantity > $orderItem->remainingQuantity()) return false;

            $fulfilled = (int) $orderItem->fulfilled_quantity + $quantity;
            $orderItem->update([
                'fulfilled_quantity' => $fulfilled,
                'fulfilled_at' => $fulfilled === (int) $orderItem->quantity ? now() : null,
            ]);
            if (! FoodOrderItem::where('food_order_id', $order->id)
                ->whereColumn('fulfilled_quantity', '<', 'quantity')->exists()) {
                $order->update(['fulfilled_at' => now()]);
            }
            return true;
        });

        return back()->with($updated ? 'success' : 'error', $updated
            ? 'Đã check-in '.$data['quantity'].' phần bắp nước.'
            : 'Combo đã giao đủ hoặc đơn chưa sẵn sàng nhận đồ.');
    }
}

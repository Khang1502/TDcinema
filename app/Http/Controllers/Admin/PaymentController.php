<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\RevenueReportService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    private const RELATIONS = ['ticket.user', 'ticket.showtime.movie', 'ticket.showtime.room.cinema', 'ticket.seat', 'ticket.invoice'];

    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $query = $this->query($filters);
        // Aggregate payments directly: joining invoices or payment attempts would multiply amounts.
        $summary = (clone $query)->selectRaw("COUNT(*) as total,
            COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) as paid_amount,
            COALESCE(SUM(CASE WHEN status = 'refunded' THEN amount ELSE 0 END), 0) as refunded_amount,
            SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
            SUM(CASE WHEN status = 'refunded' THEN 1 ELSE 0 END) as refunded_count,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count")->first();

        return view('admin.payments.index', [
            'filters' => $filters,
            'summary' => $summary,
            'payments' => $this->ordered($query, $filters['sort'])->with(self::RELATIONS)->paginate(20)->withQueryString(),
            'cinemas' => Cinema::orderBy('name')->get(['id', 'name']),
            'movies' => Movie::orderBy('title')->get(['id', 'title', 'status']),
            'methods' => PaymentMethod::labels(),
            'statuses' => RevenueReportService::STATUSES,
        ]);
    }

    public function show(Request $request, Payment $payment)
    {
        $payment->load(self::RELATIONS);

        return view('admin.payments.show', [
            'payment' => $payment,
            'filters' => $this->filters($request),
            'relatedPayments' => Payment::where('ticket_id', $payment->ticket_id)->latest('id')->limit(10)->get(),
            'methods' => PaymentMethod::labels(),
            'statuses' => RevenueReportService::STATUSES,
        ]);
    }

    public function export(Request $request)
    {
        $filters = $this->filters($request);
        $query = $this->ordered($this->query($filters), $filters['sort'])->with(self::RELATIONS);
        $methodLabels = PaymentMethod::labels();

        return response()->streamDownload(function () use ($query, $methodLabels) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Mã giao dịch', 'Mã vé', 'Hóa đơn', 'Khách hàng', 'Email', 'Điện thoại', 'Phim', 'Rạp', 'Phòng', 'Ghế', 'Phương thức', 'Trạng thái', 'Số tiền (VND)', 'Ngày ghi nhận', 'Ngày thanh toán', 'Ngày tạo'], ',', '"', '');

            foreach ($query->lazy(500) as $payment) {
                $ticket = $payment->ticket;
                $row = [
                    '#'.$payment->id, $ticket ? '#'.$ticket->id : '', $ticket?->invoice?->invoice_code ?? '',
                    $ticket?->user?->name ?? '', $ticket?->user?->email ?? '', $ticket?->user?->phone ?? '',
                    $ticket?->showtime?->movie?->title ?? '', $ticket?->showtime?->room?->cinema?->name ?? '',
                    $ticket?->showtime?->room?->name ?? '', $ticket?->seat ? $ticket->seat->seat_row.$ticket->seat->seat_number : '',
                    $methodLabels[$payment->method] ?? $payment->method,
                    RevenueReportService::STATUSES[$payment->status] ?? $payment->status,
                    number_format((float) $payment->amount, 2, '.', ''),
                    $this->date($payment->paid_at ?? $payment->created_at), $this->date($payment->paid_at), $this->date($payment->created_at),
                ];
                // Customer-supplied names and emails must remain text when opened in Excel.
                $row = array_map(fn ($value) => preg_match('/^[\s]*[=+\-@]|^[\t\r\n]/u', (string) $value) ? "'".$value : $value, $row);
                fputcsv($stream, $row, ',', '"', '');
            }

            fclose($stream);
        }, 'thanh-toan-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filters(Request $request): array
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(array_keys(RevenueReportService::STATUSES))],
            'method' => ['nullable', Rule::in(array_keys(PaymentMethod::labels()))],
            'cinema_id' => ['nullable', 'integer', 'exists:cinemas,id'],
            'movie_id' => ['nullable', 'integer', 'exists:movies,id'],
            'ticket_id' => ['nullable', 'integer', 'exists:tickets,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => array_filter(['nullable', 'date_format:Y-m-d', $request->filled('from') ? 'after_or_equal:from' : null]),
            'sort' => ['nullable', Rule::in(['latest', 'oldest', 'amount_desc', 'amount_asc'])],
        ]);

        $filters = array_replace(array_fill_keys(['q', 'status', 'method', 'cinema_id', 'movie_id', 'ticket_id', 'from', 'to'], ''),
            array_map(fn ($value) => is_string($value) ? trim($value) : ($value ?? ''), $data));
        $filters['sort'] = $filters['sort'] ?? 'latest';
        if ($filters['sort'] === '') $filters['sort'] = 'latest';

        return $filters;
    }

    private function query(array $filters): Builder
    {
        $query = Payment::query();

        foreach (['status', 'method', 'ticket_id'] as $field) {
            if ($filters[$field] !== '') $query->where($field, $filters[$field]);
        }
        if ($filters['cinema_id']) $query->whereHas('ticket.showtime.room', fn ($q) => $q->where('cinema_id', $filters['cinema_id']));
        if ($filters['movie_id']) $query->whereHas('ticket.showtime', fn ($q) => $q->where('movie_id', $filters['movie_id']));

        $recordedAt = DB::raw('COALESCE(payments.paid_at, payments.created_at)');
        if ($filters['from']) $query->whereDate($recordedAt, '>=', $filters['from']);
        if ($filters['to']) $query->whereDate($recordedAt, '<=', $filters['to']);

        if ($filters['q'] !== '') {
            $term = $filters['q'];
            $query->where(function (Builder $query) use ($term) {
                $number = ltrim($term, '#');
                if (ctype_digit($number)) $query->orWhere('payments.id', $number)->orWhere('ticket_id', $number);
                $query->orWhereHas('ticket.user', fn ($user) => $user->where(function ($user) use ($term) {
                    $user->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%')->orWhere('phone', 'like', '%'.$term.'%');
                }))->orWhereHas('ticket.showtime.movie', fn ($movie) => $movie->where('title', 'like', '%'.$term.'%'))
                    ->orWhereHas('ticket.invoice', fn ($invoice) => $invoice->where('invoice_code', 'like', '%'.$term.'%'))
                    ->orWhereHas('ticket', fn ($ticket) => $ticket->where('qr_code', 'like', '%'.$term.'%'));
            });
        }

        return $query;
    }

    private function ordered(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'oldest' => $query->orderByRaw('COALESCE(payments.paid_at, payments.created_at) ASC')->orderBy('id'),
            'amount_desc' => $query->orderByDesc('amount')->orderByDesc('id'),
            'amount_asc' => $query->orderBy('amount')->orderBy('id'),
            default => $query->orderByRaw('COALESCE(payments.paid_at, payments.created_at) DESC')->orderByDesc('id'),
        };
    }

    private function date($value): string
    {
        return $value ? Carbon::parse($value)->format('Y-m-d H:i:s') : '';
    }
}

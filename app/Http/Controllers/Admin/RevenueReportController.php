<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Cinema, Movie};
use App\Services\RevenueReportService;
use Box\Spout\Writer\Common\Creator\{WriterEntityFactory, Style\StyleBuilder};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RevenueReportController extends Controller
{
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable','date_format:Y-m-d'], 'to' => ['nullable','date_format:Y-m-d'],
            'movie_id' => ['nullable','integer','exists:movies,id'], 'cinema_id' => ['nullable','integer','exists:cinemas,id'],
            'tab' => ['nullable','in:overview,movies,cinemas,transactions'], 'period' => ['nullable','in:all'],
        ]);
        $first = $request->input('period') === 'all' ? DB::table('payments')->min(DB::raw('COALESCE(paid_at,created_at)')) : null;
        $from = $data['from'] ?? ($first ? Carbon::parse($first)->toDateString() : now()->startOfMonth()->toDateString());
        $to = $data['to'] ?? now()->toDateString();
        if ($to < $from) throw \Illuminate\Validation\ValidationException::withMessages(['to'=>'Ngày kết thúc phải bằng hoặc sau ngày bắt đầu.']);
        return ['from'=>$from,'to'=>$to,'movie_id'=>$data['movie_id'] ?? null,'cinema_id'=>$data['cinema_id'] ?? null,'tab'=>$data['tab'] ?? 'overview'];
    }

    public function index(Request $request, RevenueReportService $service)
    {
        $filters = $this->filters($request);
        $report = $service->build($filters);
        $transactions = $service->details($filters)->paginate(20, ['*'], 'transactions_page')->withQueryString();
        $paginator = fn ($items, $pageName) => (new LengthAwarePaginator($items->forPage(LengthAwarePaginator::resolveCurrentPage($pageName), 15)->values(), $items->count(), 15, null, ['path'=>route('admin.reports.index'),'pageName'=>$pageName]))->appends($request->query());
        return view('admin.reports.index', [
            'filters'=>$filters, 'report'=>$report, 'transactions'=>$transactions,
            'movieRows'=>$paginator($report['movies'], 'movies_page'), 'cinemaRows'=>$paginator($report['cinemas'], 'cinemas_page'),
            'movieOptions'=>Movie::orderBy('title')->get(['id','title','status']), 'cinemaOptions'=>Cinema::orderBy('name')->get(['id','name']),
        ]);
    }

    public function export(Request $request, RevenueReportService $service)
    {
        $filters = $this->filters($request);
        $report = $service->build($filters);
        $path = storage_path('app/revenue-'.Str::uuid().'.xlsx');
        $methodLabels = \App\Models\PaymentMethod::labels();
        $writer = WriterEntityFactory::createXLSXWriter();
        $header = (new StyleBuilder())->setFontBold()->setBackgroundColor('E5EEF9')->build();
        $number = (new StyleBuilder())->setFormat('#,##0.00')->build();
        $add = function (array $values, bool $heading = false) use ($writer, $header, $number) {
            $cells = array_map(fn ($value) => WriterEntityFactory::createCell($value, is_float($value) ? $number : null), $values);
            $writer->addRow(WriterEntityFactory::createRow($cells, $heading ? $header : null));
        };
        $sheet = function (string $name, array $columns) use ($writer, $add) { $writer->addNewSheetAndMakeItCurrent()->setName($name); $add($columns, true); };
        try {
            $writer->openToFile($path);
            $writer->getCurrentSheet()->setName('Tổng quan');
            $add(['BÁO CÁO DOANH THU TD CINEMA'], true);
            $add(['Từ ngày', $filters['from'], 'Đến ngày', $filters['to']]);
            $add(['Phim', $filters['movie_id'] ? Movie::find($filters['movie_id'])->title : 'Tất cả phim, gồm phim lưu trữ']);
            $add(['Rạp', $filters['cinema_id'] ? Cinema::find($filters['cinema_id'])->name : 'Tất cả rạp']);
            $add(['Ngày xuất', now()->format('d/m/Y H:i')]);
            $add(['Cách tính', 'Theo ngày thanh toán (ngày tạo nếu thiếu) và trạng thái hiện tại. Tổng thu gồm giao dịch đã thanh toán và đã hoàn. Doanh thu = tổng thu - hoàn tiền.']);
            $add(['Combo', 'Giá trị combo trước giảm giá là thông tin đối chiếu; đã nằm trong thanh toán, không cộng thêm vào doanh thu.']);
            $add(['Chỉ số', 'Giá trị'], true);
            foreach (['received'=>'Tổng thu vé (VND)','refunded'=>'Tiền vé đã hoàn (VND)','revenue'=>'Doanh thu vé (VND)','sold_tickets'=>'Vé đã bán còn hiệu lực','used_tickets'=>'Vé đã check-in','settled_tickets'=>'Vé có giao dịch đã thu / hoàn','paid_transactions'=>'Giao dịch vé đã thanh toán','refunded_transactions'=>'Giao dịch hoàn tiền','failed_transactions'=>'Giao dịch thất bại','booked_tickets'=>'Vé tạo trong kỳ','cancelled_tickets'=>'Vé tạo trong kỳ đã hủy','pending_tickets'=>'Vé tạo trong kỳ chờ thanh toán','combo_orders'=>'Đơn combo hoàn tất kèm vé trong báo cáo','combo_value'=>'Giá trị combo trước giảm giá (VND)','food_orders'=>'Đơn bắp nước độc lập đã thanh toán','food_revenue'=>'Doanh thu bắp nước độc lập (VND)','total_revenue'=>'Tổng doanh thu vé và bắp nước (VND)'] as $key=>$label) $add([$label,in_array($key,['received','refunded','revenue','combo_value','food_revenue','total_revenue'],true) ? (float)$report['summary'][$key] : (int)$report['summary'][$key]]);
            $columns = ['Tổng thu (VND)','Hoàn tiền (VND)','Doanh thu còn lại (VND)','Vé đã bán','Vé check-in','Số giao dịch'];
            $metrics = fn ($row) => [(float)$row['received'],(float)$row['refunded'],(float)$row['revenue'],(int)$row['sold_tickets'],(int)$row['used_tickets'],(int)$row['transactions']];
            $sheet('Theo phim', array_merge(['ID phim','Phim','Trạng thái'], $columns));
            foreach ($report['movies'] as $row) $add(array_merge([$row['id'],$row['title'],$row['status']], $metrics($row)));
            $sheet('Theo rạp', array_merge(['ID rạp','Rạp','Thành phố'], $columns));
            foreach ($report['cinemas'] as $row) $add(array_merge([$row['id'],$row['name'],$row['city']], $metrics($row)));
            $sheet('Phim tại từng rạp', array_merge(['Phim','Rạp'], $columns));
            foreach ($report['matrix'] as $row) $add(array_merge([$row->title ?? 'Không còn thông tin',$row->name ?? 'Không còn thông tin'], $metrics((array)$row)));
            $sheet('Theo ngày', array_merge(['Ngày'], $columns));
            foreach ($report['daily'] as $row) $add(array_merge([$row->date], $metrics((array)$row)));
            $sheet('Phương thức thanh toán', array_merge(['Phương thức'], $columns));
            foreach ($report['methods'] as $row) $add(array_merge([$methodLabels[$row->method] ?? $row->method], $metrics((array)$row)));
            $sheet('Chi tiết giao dịch', ['ID giao dịch','ID vé','Mã đặt vé','Ngày ghi nhận','Khách hàng','Phim','Rạp','Phòng','Ghế','Suất chiếu','Phương thức','Trạng thái thanh toán','Trạng thái vé','Giá vé trước giảm (VND)','Số tiền giao dịch (VND)']);
            foreach ($service->details($filters)->cursor() as $row) $add([$row->id,$row->ticket_id,$row->qr_code,$row->recorded_at,$row->customer_name,$row->movie_title,$row->cinema_name,$row->room_name,$row->seat_row.$row->seat_number,$row->start_time,$methodLabels[$row->method] ?? $row->method,RevenueReportService::STATUSES[$row->status] ?? $row->status,$row->ticket_status,(float)$row->price,(float)$row->amount]);
            $writer->close();
        } catch (\Throwable $exception) {
            try { $writer->close(); } catch (\Throwable) {}
            if (is_file($path)) unlink($path);
            throw $exception;
        }
        return response()->download($path, 'Bao-cao-doanh-thu-'.$filters['from'].'_'.$filters['to'].'.xlsx')->deleteFileAfterSend(true);
    }
}

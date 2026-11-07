<?php

namespace App\Http\Controllers\Admin;

use App\Models\{MembershipLevel, Promotion};
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class GiftController extends PromotionController
{
    public function index(Request $request): View
    {
        $levels = MembershipLevel::active()->get();
        $data = $request->validate([
            'q'=>['nullable','string','max:150'], 'type'=>['nullable','in:fixed,percent'],
            'status'=>['nullable','in:active,upcoming,expired,exhausted'], 'level'=>['nullable','string'],
        ]);
        $filters = ['q'=>trim($data['q'] ?? ''),'type'=>$data['type'] ?? '', 'status'=>$data['status'] ?? '', 'level'=>$data['level'] ?? ''];
        if (!in_array($filters['level'], [...$levels->map(fn ($level)=>(string)$level->id)->all(),'other'], true)) $filters['level'] = '';
        $base = Promotion::where('is_member_gift',true);
        $counts = ['total'=>(clone $base)->count(),'active'=>(clone $base)->available()->count(),
            'upcoming'=>(clone $base)->upcoming()->count(),
            'attention'=>(clone $base)->where(fn ($q)=>$q->where('status','expired')->orWhereDate('end_date','<',today())->orWhere('usage_limit',0))->count()];
        $query = clone $base;
        if ($filters['q'] !== '') $query->where(fn ($q)=>$q->where('name','like','%'.$filters['q'].'%')->orWhere('code','like','%'.$filters['q'].'%'));
        if ($filters['type'] !== '') $query->where('type',$filters['type']);
        match ($filters['status']) {
            'active'=>$query->available(), 'upcoming'=>$query->upcoming(),
            'expired'=>$query->where(fn ($q)=>$q->where('status','expired')->orWhereDate('end_date','<',today())),
            'exhausted'=>$query->where('status','active')->whereDate('end_date','>=',today())->where('usage_limit',0),
            default=>null,
        };
        $tierCounts = $levels->mapWithKeys(fn ($level)=>[(string)$level->id=>0])->all() + ['other'=>0];
        foreach ((clone $query)->selectRaw('min_member_points, COUNT(*) as gift_count')->groupBy('min_member_points')->get() as $row) {
            $tierCounts[$this->levelKey($row->min_member_points,$levels)] += (int)$row->gift_count;
        }
        if ($filters['level'] !== '') {
            $level = $levels->first(fn ($level)=>(string)$level->id === $filters['level']);
            if ($level) {
                $next = $levels->first(fn ($item)=>$item->min_points > $level->min_points);
                $query->where('min_member_points','>=',$level->min_points);
                if ($next) $query->where('min_member_points','<',$next->min_points);
            } else {
                if ($levels->isNotEmpty()) $query->where('min_member_points','<',$levels->first()->min_points);
            }
        }
        $promotions = $query->orderBy('min_member_points')->orderBy('sort_order')->orderByDesc('id')->paginate(18)->withQueryString();
        $groups = $promotions->getCollection()->groupBy(fn ($gift)=>$this->levelKey($gift->min_member_points,$levels));
        return view('admin.gifts.index',compact('promotions','filters','counts','levels','tierCounts','groups'));
    }

    private function levelKey(int $points, Collection $levels): string
    {
        return (string)($levels->last(fn ($level)=>$level->min_points <= $points)?->id ?? 'other');
    }

    public function create(): View
    {
        $levels = MembershipLevel::active()->get();
        $selected = $levels->firstWhere('id',request()->integer('level'));
        return view('admin.promotions.create',['giftLevels'=>$levels,'giftDefaultPoints'=>$selected?->min_points ?? 0]);
    }
    public function edit(Promotion $gift): View
    {
        abort_unless($gift->is_member_gift,404);
        return view('admin.promotions.edit',['promotion'=>$gift,'giftLevels'=>MembershipLevel::active()->get()]);
    }
    public function store(Request $request): RedirectResponse
    {
        $request->merge(['is_member_gift'=>1]);
        parent::store($request);
        return redirect()->route('admin.gifts.index')->with('success','Đã thêm quà tặng thành viên.');
    }
    public function update(Request $request, Promotion $gift): RedirectResponse
    {
        abort_unless($gift->is_member_gift,404);
        $request->merge(['is_member_gift'=>1]);
        parent::update($request,$gift);
        return redirect()->route('admin.gifts.index')->with('success','Đã cập nhật quà tặng thành viên.');
    }
    public function destroy(Promotion $gift): RedirectResponse
    {
        abort_unless($gift->is_member_gift,404);
        parent::destroy($gift);
        return redirect()->route('admin.gifts.index')->with('success','Đã xóa quà tặng thành viên.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{MembershipLevel, MembershipSetting, MembershipTransaction, Promotion, User};
use App\Services\MembershipService;
use Illuminate\Http\{Request, RedirectResponse};
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\View\View;

class MembershipController extends Controller
{
    public function __construct(private readonly MembershipService $membership) {}

    public function index(Request $request): View
    {
        $filters = $request->validate(['q'=>['nullable','string','max:150'],'state'=>['nullable','in:active,disabled'],'view'=>['nullable','in:grid,list']]);
        $filters = ['q'=>trim($filters['q'] ?? ''),'state'=>$filters['state'] ?? '', 'view'=>$filters['view'] ?? 'grid'];
        $allLevels = MembershipLevel::orderBy('min_points')->orderBy('id')->get();
        $activeLevels = $allLevels->where('is_active',true);
        $tierMembers = $allLevels->mapWithKeys(fn ($level)=>[$level->id=>0])->all();
        $giftCounts = $tierMembers;
        $pointGroups = User::where('role','customer')->selectRaw('points, COUNT(*) as aggregate')->groupBy('points')->get();
        foreach ($pointGroups as $row) {
            $level = $activeLevels->last(fn ($level)=>$level->min_points <= max(0,(int)$row->points));
            if ($level) $tierMembers[$level->id] += (int)$row->aggregate;
        }
        foreach (Promotion::available()->where('is_member_gift',true)->selectRaw('min_member_points, COUNT(*) as aggregate')->groupBy('min_member_points')->get() as $row) {
            $level = $activeLevels->last(fn ($level)=>$level->min_points <= $row->min_member_points);
            if ($level) $giftCounts[$level->id] += (int)$row->aggregate;
        }
        $matching = $filters['q'] === '' ? null : \App\Support\KeywordSearch::matchingIds($allLevels,$filters['q'],['name','description']);
        $levels = $allLevels->filter(fn ($level)=>($filters['state'] === '' || $level->is_active === ($filters['state'] === 'active'))
            && ($matching === null || in_array($level->id,$matching,true)))->values();
        return view('admin.membership.index',['levels'=>$levels,'allLevels'=>$allLevels,'memberCount'=>(int)$pointGroups->sum('aggregate'),
            'activeLevelCount'=>$activeLevels->count(),'tierMembers'=>$tierMembers,'giftCounts'=>$giftCounts,'filters'=>$filters,'settings'=>$this->membership->settings()]);
    }
    public function create(): View { return $this->levelForm(new MembershipLevel(['is_active'=>true,'color'=>'blue','discount_percent'=>0])); }
    public function edit(MembershipLevel $level): View { return $this->levelForm($level); }

    private function levelForm(MembershipLevel $level): View
    {
        return view('admin.membership.form', ['level'=>$level,
            'existingLevels'=>MembershipLevel::orderBy('min_points')->orderBy('id')->get(['id','name','min_points','is_active','color']),
            'programName'=>$this->membership->settings()->name]);
    }
    public function store(Request $request): RedirectResponse
    {
        $data = $this->levelData($request);
        if ($data['is_active'] && $data['min_points'] > 0 && ! MembershipLevel::active()->where('min_points',0)->exists()) {
            throw ValidationException::withMessages(['min_points'=>'Cần có một hạng đang bật với mốc 0 Stars trước.']);
        }
        MembershipLevel::create($data);
        $this->membership->refresh();
        return redirect()->route('admin.membership.index')->with('success','Đã thêm cấp bậc thành viên.');
    }
    public function update(Request $request, MembershipLevel $level): RedirectResponse
    {
        $data = $this->levelData($request, $level);
        if ((! $data['is_active'] || $data['min_points'] > 0) && ! MembershipLevel::active()->where('min_points',0)->where('id','!=',$level->id)->exists()) {
            throw ValidationException::withMessages(['min_points'=>'Phải giữ ít nhất một hạng đang bật với mốc 0 Stars cho thành viên mới.']);
        }
        $level->update($data);
        $this->membership->refresh();
        return redirect()->route('admin.membership.index')->with('success','Đã cập nhật cấp bậc và quyền lợi.');
    }
    public function destroy(MembershipLevel $level): RedirectResponse
    {
        if ($level->is_active && $level->min_points === 0) return back()->with('error','Không thể xóa hạng khởi đầu. Bạn có thể sửa tên và quyền lợi của hạng này.');
        if ($level->users()->withTrashed()->exists()) return back()->with('error','Hạng này đã có thành viên. Hãy tắt hạng thay vì xóa để giữ thông tin tài khoản.');
        $level->delete();
        $this->membership->refresh();
        return redirect()->route('admin.membership.index')->with('success','Đã xóa cấp bậc.');
    }
    public function settings(): View { return view('admin.membership.settings', ['settings'=>$this->membership->settings()]); }
    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(['name'=>['required','string','max:80'],'title'=>['required','string','max:160'],
            'subtitle'=>['nullable','string','max:500'],'earn_unit'=>['required','integer','min:1','max:100000000'],
            'is_enabled'=>['required','boolean'],'terms'=>['required','string','max:5000']]);
        MembershipSetting::updateOrCreate(['id'=>1], $data);
        $this->membership->refresh();
        return back()->with('success','Đã lưu nội dung và quy tắc chương trình thành viên.');
    }
    public function members(Request $request): View
    {
        $query = User::where('role','customer');
        $q = trim((string) $request->query('q',''));
        if ($q !== '') $query->where(fn ($builder) => $builder->where('name','like','%'.$q.'%')->orWhere('email','like','%'.$q.'%')->orWhere('phone','like','%'.$q.'%'));
        $levels = $this->membership->levels();
        if ($level = $levels->firstWhere('id', (int) $request->query('level'))) {
            $query->where('points','>=',$level->min_points);
            if ($next = $levels->first(fn ($item) => $item->min_points > $level->min_points)) $query->where('points','<',$next->min_points);
        }
        return view('admin.membership.members', ['members'=>$query->orderByDesc('points')->orderBy('name')->paginate(12)->withQueryString(), 'levels'=>$levels, 'q'=>$q]);
    }
    public function member(User $member): View
    {
        abort_unless($member->role === 'customer',404);
        return view('admin.membership.member', ['member'=>$member,'summary'=>$this->membership->summary($member),
            'transactions'=>MembershipTransaction::with(['actor','invoice'])->where('user_id',$member->id)->latest('id')->paginate(12)]);
    }
    public function adjust(Request $request, User $member): RedirectResponse
    {
        abort_unless($member->role === 'customer',404);
        $data = $request->validate(['points'=>['required','integer','between:-10000000,10000000','not_in:0'],'reason'=>['required','string','max:255']]);
        $this->membership->adjust($member, (int) $data['points'], trim($data['reason']), $request->user());
        return back()->with('success','Đã điều chỉnh Stars và cập nhật cấp bậc.');
    }

    private function levelData(Request $request, ?MembershipLevel $level = null): array
    {
        $data = $request->validate(['name'=>['required','string','max:50'],
            'min_points'=>['required','integer','min:0','max:100000000',Rule::unique('membership_levels','min_points')->ignore($level?->id)],
            'discount_percent'=>['required','integer','between:0,100'], 'description'=>['nullable','string','max:500'],
            'benefits_text'=>['nullable','string','max:2000'],'color'=>['required',Rule::in(array_keys(MembershipLevel::COLORS))], 'is_active'=>['required','boolean']]);
        $benefits = array_values(array_filter(array_map('trim', preg_split('/\R/', $data['benefits_text'] ?? '')), fn ($line) => $line !== ''));
        if (count($benefits) > 12 || collect($benefits)->contains(fn ($line) => mb_strlen($line) > 160)) throw ValidationException::withMessages(['benefits_text'=>'Tối đa 12 quyền lợi, mỗi dòng không quá 160 ký tự.']);
        unset($data['benefits_text']);
        return $data + ['benefits'=>$benefits];
    }
}

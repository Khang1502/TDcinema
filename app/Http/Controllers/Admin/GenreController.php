<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Movie, MovieGenre};
use App\Support\{KeywordSearch, MovieGenres};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GenreController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q'=>['nullable','string','max:150'],
            'state'=>['nullable','in:active,disabled'],'usage'=>['nullable','in:used,unused']]);
        $filters = ['q'=>trim($filters['q'] ?? ''),'state'=>$filters['state'] ?? '','usage'=>$filters['usage'] ?? ''];
        $catalog = MovieGenres::catalog();
        $usage = MovieGenres::usage($catalog);
        $matching = $filters['q'] === '' ? null : KeywordSearch::matchingIds($catalog,$filters['q'],['name','description']);
        $ids = $catalog->filter(fn ($genre)=>($matching === null || in_array($genre->id,$matching,true))
            && ($filters['state'] === '' || $genre->is_active === ($filters['state'] === 'active'))
            && ($filters['usage'] === '' || (($usage[$genre->id] > 0) === ($filters['usage'] === 'used'))))->pluck('id');
        $genres = MovieGenre::whereIn('id',$ids)->orderBy('sort_order')->orderBy('name')->paginate(12)->withQueryString();
        $counts = ['total'=>$catalog->count(),'active'=>$catalog->where('is_active',true)->count(),
            'used'=>count(array_filter($usage,fn ($count)=>$count > 0)),'unused'=>count(array_filter($usage,fn ($count)=>$count === 0))];
        return view('admin.genres.index',compact('genres','usage','counts','filters'));
    }

    public function create()
    {
        return view('admin.genres.form',['genre'=>new MovieGenre(['is_active'=>true,
            'sort_order'=>min(100000,(int)MovieGenre::max('sort_order')+10)]),'movieCount'=>0]);
    }

    public function edit(MovieGenre $genre)
    {
        return view('admin.genres.form',['genre'=>$genre,'movieCount'=>count(MovieGenres::movieIds($genre->name))]);
    }

    public function store(Request $request)
    {
        MovieGenre::create($this->data($request));
        return redirect()->route('admin.genres.index')->with('success','Đã thêm thể loại phim. Thể loại đang bật sẽ xuất hiện trong menu chọn phim.');
    }

    public function update(Request $request, MovieGenre $genre)
    {
        $data = $this->data($request,$genre);
        $updated = DB::transaction(function () use ($genre,$data) {
            $current = MovieGenre::whereKey($genre->id)->lockForUpdate()->firstOrFail();
            $updates = [];
            if ($data['name'] !== $current->name) {
                foreach (Movie::orderBy('id')->lockForUpdate()->get(['id','title','genre']) as $movie) {
                    $changed = false;
                    $names = array_map(function ($name) use ($current,$data,&$changed) {
                        if (in_array(MovieGenre::key($name),$current->keys(),true)) { $changed = true; return $data['name']; }
                        return $name;
                    },MovieGenres::parse($movie->genre));
                    if (! $changed) continue;
                    $value = implode(', ',MovieGenres::parse($names));
                    if (mb_strlen($value) > 100) throw ValidationException::withMessages([
                        'name'=>'Tên mới khiến danh sách thể loại của phim “'.$movie->title.'” quá dài. Vui lòng rút gọn tên.']);
                    $updates[$movie->id] = $value;
                }
                $data['aliases'] = array_values(array_filter(MovieGenres::parse([...($current->aliases ?? []),$current->name]),
                    fn ($name)=>MovieGenre::key($name) !== $data['name_key']));
            }
            $current->update($data);
            foreach ($updates as $id=>$value) Movie::whereKey($id)->update(['genre'=>$value]);
            return count($updates);
        });
        return redirect()->route('admin.genres.index')->with('success','Đã cập nhật thể loại.'.($updated ? " Đã đổi tên trên {$updated} phim liên quan." : ''));
    }

    public function visibility(MovieGenre $genre)
    {
        $genre->update(['is_active'=>!$genre->is_active]);
        return back()->with('success',$genre->is_active ? 'Đã bật thể loại trong menu chọn phim.' : 'Đã tắt thể loại trong menu chọn phim. Thể loại trên các phim cũ vẫn được giữ lại.');
    }

    public function destroy(MovieGenre $genre)
    {
        $deleted = DB::transaction(function () use ($genre) {
            $current = MovieGenre::whereKey($genre->id)->lockForUpdate()->firstOrFail();
            $used = Movie::orderBy('id')->lockForUpdate()->get(['id','genre'])->contains(fn ($movie)=>
                count(array_intersect($current->keys(),array_map(MovieGenre::key(...),MovieGenres::parse($movie->genre)))) > 0);
            if ($used) return false;
            $current->delete();
            return true;
        });
        return $deleted ? back()->with('success','Đã xóa thể loại chưa sử dụng.')
            : back()->with('error','Thể loại đang gắn với phim, gồm cả phim đã lưu trữ. Hãy tắt thể loại để ngừng chọn cho phim mới.');
    }

    private function data(Request $request, ?MovieGenre $genre = null): array
    {
        if (is_string($request->input('name'))) $request->merge(['name'=>trim(preg_replace('/\s+/u',' ',$request->input('name')))]);
        $data = $request->validate(['name'=>['required','string','max:100','not_regex:/[,;\r\n]/'],
            'description'=>['nullable','string','max:500'],'sort_order'=>['required','integer','between:0,100000'],
            'is_active'=>['required','boolean']], ['name.not_regex'=>'Nhập một tên thể loại, không dùng dấu phẩy hoặc chấm phẩy.'],
            ['name'=>'tên thể loại','description'=>'ghi chú','sort_order'=>'thứ tự','is_active'=>'trạng thái']);
        $key = MovieGenre::key($data['name']);
        $existing = MovieGenres::lookup()[$key] ?? null;
        if ($existing && $existing->id !== $genre?->id) throw ValidationException::withMessages([
            'name'=>'Tên này đã thuộc thể loại “'.$existing->name.'”. Vui lòng chọn tên khác.']);
        return $data + ['name_key'=>$key];
    }
}

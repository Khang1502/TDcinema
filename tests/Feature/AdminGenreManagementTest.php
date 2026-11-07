<?php

namespace Tests\Feature;

use App\Models\{Movie, MovieGenre, User};
use App\Support\MovieGenres;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminGenreManagementTest extends TestCase
{
    use RefreshDatabase;

    private function data(string $name, array $changes = []): array
    {
        return array_replace(['name'=>$name,'description'=>'Ghi chú quản lý','sort_order'=>5,'is_active'=>1],$changes);
    }

    private function movie(string $name, string $genre, string $status='now_showing'): Movie
    {
        return Movie::create(['title'=>$name,'genre'=>$genre,'duration'=>90,'status'=>$status]);
    }

    private function admin(): void { $this->actingAs(User::factory()->create(['role'=>'admin'])); }

    public function test_admin_can_create_order_and_toggle_genres_and_duplicate_names_are_rejected(): void
    {
        $this->admin();
        $this->post(route('admin.genres.store'),$this->data('Anime'))->assertSessionHasNoErrors()->assertRedirect(route('admin.genres.index'));
        $genre = MovieGenre::where('name','Anime')->firstOrFail();
        $this->assertSame('Anime',MovieGenres::options()[0]);
        $this->post(route('admin.genres.store'),$this->data(' ANIME '))->assertSessionHasErrors('name');
        $this->post(route('admin.genres.store'),$this->data('Ànime'))->assertSessionHasErrors('name');
        $this->patch(route('admin.genres.visibility',$genre))->assertSessionHas('success');
        $this->assertFalse($genre->fresh()->is_active);
        $this->assertNotContains('Anime',MovieGenres::options());
        $this->patch(route('admin.genres.visibility',$genre))->assertSessionHas('success');
        $this->assertContains('Anime',MovieGenres::options());
    }

    public function test_rename_updates_exact_genres_on_current_and_archived_movies_and_imports_use_the_new_name(): void
    {
        $this->admin();
        $genre = MovieGenre::where('name','Hài')->firstOrFail();
        $current = $this->movie('Phim đang chiếu','hAI, Hoạt hình');
        $archived = $this->movie('Phim cũ','Hài','inactive');
        $prefix = $this->movie('Thể loại khác','Hài hước');
        $this->put(route('admin.genres.update',$genre),$this->data('Hài kịch'))->assertSessionHasNoErrors();
        $this->assertSame('Hài kịch, Hoạt hình',$current->fresh()->genre);
        $this->assertSame('Hài kịch',$archived->fresh()->genre);
        $this->assertSame('inactive',$archived->fresh()->status);
        $this->assertSame('Hài hước',$prefix->fresh()->genre);
        $imported = $this->movie('Phim nhập sau','Hài, Hoạt hình');
        $this->assertSame('Hài kịch, Hoạt hình',$imported->genre);
        $this->assertSame(0,MovieGenre::where('name_key',MovieGenre::key('Hài'))->count());
        $this->post(route('admin.genres.store'),$this->data('Hài'))->assertSessionHasErrors('name');
        $this->get(route('admin.movies.index',['genre'=>'Hài']))->assertViewHas('filters',fn ($f)=>$f['genre']==='Hài kịch')
            ->assertViewHas('movies',fn ($items)=>$items->total()===3);
    }

    public function test_rename_that_would_overflow_a_movie_genre_rolls_back_every_change(): void
    {
        $this->admin();
        $safe = $this->movie('Phim ngắn','A');
        $value = 'A, '.str_repeat('B',96);
        $long = $this->movie('Phim nhiều thể loại',$value);
        $genre = MovieGenre::where('name','A')->firstOrFail();
        $this->put(route('admin.genres.update',$genre),$this->data('Tên thể loại dài mới'))->assertSessionHasErrors('name');
        $this->assertSame('A',$genre->fresh()->name);
        $this->assertSame('A',$safe->fresh()->genre);
        $this->assertSame($value,$long->fresh()->genre);
    }

    public function test_disabled_genre_is_retained_on_existing_movies_but_cannot_be_added_to_new_ones(): void
    {
        $this->admin();
        $genre = MovieGenre::where('name','Hành động')->firstOrFail();
        $movie = $this->movie('Phim đã gắn thể loại','Hành động');
        $this->patch(route('admin.genres.visibility',$genre));
        $this->assertSame('Hành động',$movie->fresh()->genre);
        $payload = ['title'=>'Phim mới','duration'=>100,'status'=>'now_showing','genres_present'=>1,'genres'=>['Hành động']];
        $this->post(route('admin.movies.store'),$payload)->assertSessionHasErrors('genres');
        $this->put(route('admin.movies.update',$movie),$payload)->assertSessionHasNoErrors();
        $this->assertSame('Hành động',$movie->fresh()->genre);
        $this->get(route('admin.movies.edit',$movie))->assertOk()->assertSee('Đang tắt');
        $this->post(route('admin.movies.store'),array_replace($payload,['genres'=>['Thể loại chưa đăng ký']]))->assertSessionHasErrors('genres');
    }

    public function test_deleting_used_genres_preserves_archived_movies_and_unused_genres_can_be_deleted(): void
    {
        $this->admin();
        $genre = MovieGenre::where('name','Tâm lý')->firstOrFail();
        $movie = $this->movie('Phim đã lưu trữ','Tâm lý','inactive');
        $this->delete(route('admin.genres.destroy',$genre))->assertSessionHas('error');
        $this->assertDatabaseHas('movie_genres',['id'=>$genre->id]);
        $this->assertSame('Tâm lý',$movie->fresh()->genre);
        $unused = MovieGenre::where('name','Âm nhạc')->firstOrFail();
        $this->delete(route('admin.genres.destroy',$unused))->assertSessionHas('success');
        $this->assertDatabaseMissing('movie_genres',['id'=>$unused->id]);
    }

    public function test_search_usage_counts_and_movie_links_match_whole_genres(): void
    {
        $this->admin();
        $exact = $this->movie('Hài chính xác','Hài');
        $this->movie('Hài hước mở rộng','Hài hước');
        $genre = MovieGenre::where('name','Hài')->firstOrFail();
        $this->get(route('admin.genres.index',['q'=>'hai','usage'=>'used','state'=>'active']))->assertOk()
            ->assertViewHas('genres',fn ($items)=>$items->total()===2)
            ->assertViewHas('usage',fn ($counts)=>$counts[$genre->id]===1)->assertDontSee('class="ad-pagination"',false);
        $this->get(route('admin.movies.index',['genre'=>'Hài']))->assertViewHas('movies',fn ($items)=>$items->pluck('id')->all()===[$exact->id]);
    }

    public function test_movie_imports_register_new_names_without_reactivating_existing_disabled_genres(): void
    {
        $genre = MovieGenre::where('name','Kinh dị')->firstOrFail();
        $genre->update(['is_active'=>false,'sort_order'=>2,'description'=>'Giữ ghi chú']);
        $this->movie('Phim nhập','KINH DỊ, Thể loại nhập riêng');
        $this->assertFalse($genre->fresh()->is_active);
        $this->assertSame(2,$genre->fresh()->sort_order);
        $this->assertSame('Giữ ghi chú',$genre->fresh()->description);
        $this->assertDatabaseHas('movie_genres',['name'=>'Thể loại nhập riêng','is_active'=>true]);
        $this->movie('Phim nhập khác','thể loại nhập riêng');
        $this->assertSame(1,MovieGenre::where('name_key',MovieGenre::key('Thể loại nhập riêng'))->count());
    }

    public function test_genre_management_obeys_existing_movie_management_permissions(): void
    {
        $genre = MovieGenre::firstOrFail();
        $this->actingAs(User::factory()->create(['role'=>'staff']))->get(route('admin.genres.index'))->assertForbidden();
        $this->post(route('admin.genres.store'),$this->data('Không được phép'))->assertForbidden();
        $this->put(route('admin.genres.update',$genre),$this->data('Không được phép'))->assertForbidden();
        $this->patch(route('admin.genres.visibility',$genre))->assertForbidden();
        $this->delete(route('admin.genres.destroy',$genre))->assertForbidden();
        $this->get(route('admin.dashboard'))->assertDontSee(route('admin.genres.index'));
        $this->actingAs(User::factory()->create(['role'=>'manager','admin_permissions'=>['dashboard.view','movies.manage']]))
            ->get(route('admin.genres.index'))->assertOk()->assertSee('Thể loại phim');
    }
}

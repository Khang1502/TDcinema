<?php

namespace Tests\Feature;

use App\Models\{Movie, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMovieGenrePickerTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $changes = []): array
    {
        return array_replace(['title'=>'Phim chọn thể loại','duration'=>100,'status'=>'now_showing','genres_present'=>'1'], $changes);
    }

    private function checkedGenres(string $html): array
    {
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($document);
        $values = [];
        foreach ($xpath->query('//input[@name="genres[]" and @checked]') as $input) $values[] = $input->getAttribute('value');
        return $values;
    }

    public function test_create_has_options_and_edit_preselects_all_existing_genres_including_custom_names(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        $this->get(route('admin.movies.create'))->assertOk()->assertSee('data-genre-picker',false)->assertSee('Hoạt hình');
        $movie = Movie::create(['title'=>'Phim cũ','duration'=>90,'status'=>'now_showing','genre'=>'Hoạt hình; Giả Tưởng, Thể loại riêng']);
        $response = $this->get(route('admin.movies.edit',$movie))->assertOk();
        $this->assertSame(['Hoạt hình','Giả Tưởng','Thể loại riêng'],$this->checkedGenres($response->getContent()));
        $this->assertSame('Hoạt hình; Giả Tưởng, Thể loại riêng',$movie->fresh()->genre);
    }

    public function test_multiple_selections_are_saved_once_and_can_be_cleared_explicitly(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'admin']))->post(route('admin.movies.store'),
            $this->payload(['genres'=>['Hoạt hình',' Giả tưởng ','hoạt hình']]))->assertSessionHasNoErrors();
        $movie = Movie::firstOrFail();
        $this->assertSame('Hoạt hình, Giả tưởng',$movie->genre);
        $this->put(route('admin.movies.update',$movie),$this->payload())->assertSessionHasNoErrors();
        $this->assertNull($movie->fresh()->genre);
    }

    public function test_invalid_submission_retains_the_selected_genres_and_rejects_values_too_long_to_store(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        $this->from(route('admin.movies.create'))->post(route('admin.movies.store'),
            $this->payload(['title'=>'','genres'=>['Hành động','Tâm lý']]))->assertSessionHasErrors('title');
        $response = $this->get(route('admin.movies.create'))->assertOk();
        $this->assertSame(['Hành động','Tâm lý'],$this->checkedGenres($response->getContent()));
        $this->post(route('admin.movies.store'),$this->payload(['genres'=>[str_repeat('a',60),str_repeat('b',60)]]))->assertSessionHasErrors('genre');
        $this->post(route('admin.movies.store'),$this->payload(['genres'=>123]))->assertSessionHasErrors('genres');
        $this->get(route('admin.movies.create'))->assertOk();
        $this->assertDatabaseCount('movies',0);
    }

    public function test_legacy_string_submissions_still_work_and_omitting_the_field_does_not_clear_it(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        $data = $this->payload(['genre'=>'Hành động, Hài']);
        unset($data['genres_present']);
        $this->post(route('admin.movies.store'),$data)->assertSessionHasNoErrors();
        $movie = Movie::firstOrFail();
        unset($data['genre']);
        $this->put(route('admin.movies.update',$movie),$data)->assertSessionHasNoErrors();
        $this->assertSame('Hành động, Hài',$movie->fresh()->genre);
    }
}

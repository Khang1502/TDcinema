<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminMovieTrailerUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_a_trailer_file_when_creating_a_movie(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.movies.store'), [
            'title' => 'Phim có trailer tải lên',
            'duration' => 120,
            'status' => 'upcoming',
            'trailer' => UploadedFile::fake()->create('trailer.mp4', 1024, 'video/mp4'),
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.movies.index'));

        $movie = Movie::where('title', 'Phim có trailer tải lên')->firstOrFail();
        $this->assertStringStartsWith('movies/trailers/', $movie->trailer_url);
        Storage::disk('public')->assertExists($movie->trailer_url);
    }

    public function test_replacing_an_uploaded_trailer_removes_the_old_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('movies/trailers/old.mp4', 'old trailer');
        $admin = User::factory()->create(['role' => 'admin']);
        $movie = Movie::create([
            'title' => 'Phim cũ',
            'duration' => 90,
            'status' => 'upcoming',
            'trailer_url' => 'movies/trailers/old.mp4',
        ]);

        $this->actingAs($admin)->put(route('admin.movies.update', $movie), [
            'title' => 'Phim cũ',
            'duration' => 90,
            'status' => 'upcoming',
            'trailer' => UploadedFile::fake()->create('new-trailer.webm', 1024, 'video/webm'),
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.movies.index'));

        $movie->refresh();
        Storage::disk('public')->assertMissing('movies/trailers/old.mp4');
        Storage::disk('public')->assertExists($movie->trailer_url);
        $this->assertStringEndsWith('.webm', $movie->trailer_url);
    }

    public function test_non_video_file_cannot_be_used_as_a_trailer(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.movies.store'), [
            'title' => 'Phim trailer lỗi',
            'duration' => 100,
            'status' => 'upcoming',
            'trailer' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('trailer');

        $this->assertDatabaseMissing('movies', ['title' => 'Phim trailer lỗi']);
    }

    public function test_admin_can_update_the_movie_age_rating(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $movie = Movie::create([
            'title' => 'Phim giới hạn độ tuổi',
            'duration' => 105,
            'status' => 'now_showing',
            'age_rating' => 'T13',
        ]);

        $this->actingAs($admin)->put(route('admin.movies.update', $movie), [
            'title' => $movie->title,
            'duration' => $movie->duration,
            'status' => $movie->status,
            'age_rating' => 'T18',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.movies.index'));

        $this->assertDatabaseHas('movies', [
            'id' => $movie->id,
            'age_rating' => 'T18',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('age-badge">T18', false);

        $this->get(route('movies.show', $movie))
            ->assertOk()
            ->assertSee('class="age">T18', false);
    }
}

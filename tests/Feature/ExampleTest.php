<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_admin_accounts_can_return_to_and_browse_the_customer_site(): void
    {
        $movie = Movie::create([
            'title' => 'Phim dành cho trang khách',
            'duration' => 100,
            'status' => 'now_showing',
        ]);

        foreach (['admin', 'manager', 'staff'] as $role) {
            $admin = User::factory()->create(['role' => $role]);

            $this->actingAs($admin)
                ->get(route('home'))
                ->assertOk()
                ->assertViewIs('frontend.home');

            $this->get(route('movies.index'))
                ->assertOk()
                ->assertViewIs('frontend.movies.index');

            $this->get(route('movies.show', $movie))
                ->assertOk()
                ->assertViewIs('frontend.movie');
        }
    }

    public function test_only_admin_roles_see_the_return_to_admin_link_on_customer_pages(): void
    {
        foreach (['admin', 'manager', 'staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('home'))
                ->assertOk()
                ->assertSee('Về trang admin');
        }

        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('Về trang admin');
    }
}

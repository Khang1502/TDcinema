<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('home', absolute: false));
    }

    public function test_registration_rejects_a_phone_already_used_by_an_account(): void
    {
        User::factory()->create(['phone' => '0901234567']);

        $this->from('/')->post('/register', $this->registrationData('0901234567'))
            ->assertRedirect('/')
            ->assertSessionHasErrors([
                'phone' => 'Số điện thoại này đã được đăng ký. Vui lòng sử dụng số khác.',
            ], null, 'registration');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
        $this->get('/')
            ->assertOk()
            ->assertSee('data-registration-error="1"', false)
            ->assertSee('id="register-phone-error"', false)
            ->assertSee('Số điện thoại này đã được đăng ký. Vui lòng sử dụng số khác.')
            ->assertSee('value="0901234567"', false)
            ->assertSee('value="New User"', false);
    }

    public function test_registration_rejects_a_phone_used_by_a_deleted_account(): void
    {
        $user = User::factory()->create(['phone' => '0901234567']);
        $user->delete();

        $this->post('/register', $this->registrationData('0901234567'))
            ->assertSessionHasErrors('phone', null, 'registration');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_saves_an_unused_phone(): void
    {
        User::factory()->create(['phone' => '0901234567']);

        $this->post('/register', $this->registrationData('0912345678'))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'phone' => '0912345678']);
    }

    public function test_registration_still_allows_an_optional_phone(): void
    {
        User::factory()->create(['phone' => null]);

        $this->post('/register', $this->registrationData(''))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'phone' => null]);
    }

    public function test_registration_page_displays_phone_validation_errors(): void
    {
        User::factory()->create(['phone' => '0901234567']);

        $this->from('/register')->post('/register', $this->registrationData('0901234567'))
            ->assertRedirect('/register');

        $this->get('/register')
            ->assertOk()
            ->assertSee('name="phone"', false)
            ->assertSee('value="0901234567"', false)
            ->assertSee('Số điện thoại này đã được đăng ký. Vui lòng sử dụng số khác.');
    }

    private function registrationData(string $phone): array
    {
        return [
            'name' => 'New User',
            'email' => 'new@example.com',
            'phone' => $phone,
            'password' => 'password',
            'password_confirmation' => 'password',
        ];
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('home'));

            return true;
        });
    }

    public function test_local_environment_provides_reset_link_when_mail_transport_fails(): void
    {
        $user = User::factory()->create();
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        $this->app->instance(Dispatcher::class, $dispatcher);

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        $response
            ->assertRedirect()
            ->assertSessionHas('reset_sent', true)
            ->assertSessionHas('reset_link');

        $resetLink = $response->getSession()->get('reset_link');
        preg_match('#/reset-password/([^?]+)#', $resetLink, $matches);

        $this->assertNotEmpty($matches[1] ?? null);
        $this->assertTrue(Password::tokenExists($user, $matches[1]));
    }

    public function test_unknown_email_returns_to_forgot_password_state(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'khongtontai@example.com'])
            ->assertSessionHasErrors('email')
            ->assertSessionHas('reset_error', true);
    }
}

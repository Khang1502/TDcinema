<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Throwable;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );
        } catch (Throwable $exception) {
            Log::warning('password_reset.mail_failed', [
                'email' => $request->email,
                'error' => $exception->getMessage(),
            ]);

            if (app()->environment(['local', 'testing'])) {
                $user = User::where('email', $request->email)->first();

                if ($user) {
                    $token = Password::broker()->createToken($user);
                    $resetPath = route('password.reset', [
                        'token' => $token,
                        'email' => $user->email,
                    ], false);

                    return back()->with([
                        'status' => 'Máy chủ email chưa kết nối. Hãy dùng liên kết đặt lại mật khẩu bên dưới.',
                        'reset_email' => $user->email,
                        'reset_sent' => true,
                        'reset_link' => $request->getSchemeAndHttpHost().$resetPath,
                    ]);
                }

                $status = Password::INVALID_USER;
            } else {
                return back()->withInput($request->only('email'))
                    ->with('reset_error', true)
                    ->withErrors(['email' => 'Hiện chưa thể gửi email khôi phục. Vui lòng thử lại sau.']);
            }
        }

        if ($status == Password::RESET_LINK_SENT) {
            return back()->with([
                'status' => __($status),
                'reset_email' => $request->email,
                'reset_sent' => true,
            ]);
        }

        return back()->withInput($request->only('email'))
            ->with('reset_error', true)
            ->withErrors(['email' => __($status)]);
    }
}

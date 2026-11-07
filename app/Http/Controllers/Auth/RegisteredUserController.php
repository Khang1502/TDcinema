<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use App\Models\MembershipLevel;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validateWithBag('registration', [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'phone.unique' => 'Số điện thoại này đã được đăng ký. Vui lòng sử dụng số khác.',
        ]);

        // Lấy hạng thành viên mặc định (level đầu tiên); fallback về 1 nếu chưa có dữ liệu.
        $defaultMembershipId = MembershipLevel::active()->where('min_points', 0)->value('id');

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'customer',
            'status' => 'active',
            'membership_level_id' => $defaultMembershipId,
            'is_active' => true,
            'phone' => $request->input('phone'),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('home'))->with('success', 'Đăng ký tài khoản thành công. Chào mừng bạn đến với TD Cinema.');
    }
}

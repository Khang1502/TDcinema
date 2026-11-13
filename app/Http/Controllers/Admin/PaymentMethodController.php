<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PaymentMethodController extends Controller
{
    public function index()
    {
        return view('admin.payment-methods.index', ['methods'=>PaymentMethod::orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function create()
    {
        return view('admin.payment-methods.create', [
            'nextOrder' => (int) PaymentMethod::max('sort_order') + 10,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]{2,39}$/', Rule::unique('payment_methods', 'code')],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'badge' => ['nullable', 'string', 'max:40'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        $logo = $data['logo'] ?? null;
        unset($data['logo']);
        $data['is_online'] = true;
        $data['is_enabled'] = false;
        if ($logo) $data['logo_path'] = $logo->store('payment-methods', 'public');

        PaymentMethod::create($data);

        return redirect()->route('admin.payment-methods.index')->with('success', 'Đã thêm phương thức '.$data['name'].' ở trạng thái tạm dừng.');
    }

    public function edit(PaymentMethod $paymentMethod)
    {
        return view('admin.payment-methods.edit', ['method'=>$paymentMethod]);
    }

    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        $data = $request->validate([
            'name'=>['required', 'string', 'max:100'],
            'description'=>['nullable', 'string', 'max:500'],
            'instructions'=>['nullable', 'string', 'max:2000'],
            'badge'=>['nullable', 'string', 'max:40'],
            'sort_order'=>['required', 'integer', 'min:0', 'max:9999'],
            'is_enabled'=>['required', 'boolean'],
            'logo'=>['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);
        $logo = $data['logo'] ?? null;
        unset($data['logo']);
        if ($logo) $data['logo_path'] = $logo->store('payment-methods', 'public');
        $previousLogo = $paymentMethod->logo_path;
        $paymentMethod->update($data);
        if ($logo && $previousLogo) Storage::disk('public')->delete($previousLogo);

        return redirect()->route('admin.payment-methods.index')->with('success', 'Đã cập nhật phương thức '.$paymentMethod->name.'.');
    }

    public function visibility(Request $request, PaymentMethod $paymentMethod)
    {
        $data = $request->validate(['is_enabled'=>['required', 'boolean']]);
        $paymentMethod->update($data);

        return redirect()->route('admin.payment-methods.index')->with('success', ($paymentMethod->is_enabled ? 'Đã bật ' : 'Đã tạm dừng ').$paymentMethod->name.'.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function index()
    {
        return view('admin.payment-methods.index', ['methods'=>PaymentMethod::orderBy('sort_order')->orderBy('id')->get()]);
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
        ]);
        $paymentMethod->update($data);

        return redirect()->route('admin.payment-methods.index')->with('success', 'Đã cập nhật phương thức '.$paymentMethod->name.'.');
    }

    public function visibility(Request $request, PaymentMethod $paymentMethod)
    {
        $data = $request->validate(['is_enabled'=>['required', 'boolean']]);
        $paymentMethod->update($data);

        return redirect()->route('admin.payment-methods.index')->with('success', ($paymentMethod->is_enabled ? 'Đã bật ' : 'Đã tạm dừng ').$paymentMethod->name.'.');
    }
}

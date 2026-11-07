<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FooterSetting;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FooterController extends Controller
{
    public function edit(): View
    {
        return view('admin.footer.edit', ['settings' => FooterSetting::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        foreach (['support_links', 'editorial_links'] as $key) {
            $rows = $request->input($key, []);
            if (is_array($rows)) {
                $request->merge([$key => array_values(array_filter($rows, fn ($row) => !is_array($row) ||
                    ($row['label'] ?? '') !== '' && ($row['label'] ?? null) !== null || ($row['url'] ?? '') !== '' && ($row['url'] ?? null) !== null))]);
            }
        }
        $rules = [
            'is_visible' => ['required', 'boolean'], 'brand_name' => ['required', 'string', 'max:120'],
            'company_name' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:600'],
            'address' => ['nullable', 'string', 'max:300'], 'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+().\s-]+$/'],
            'email' => ['nullable', 'email', 'max:120'], 'copyright' => ['required', 'string', 'max:300'],
        ];
        foreach (['support_title', 'editorial_title', 'contact_title'] as $key) $rules[$key] = ['required', 'string', 'max:80'];
        foreach (['facebook_url', 'instagram_url', 'youtube_url'] as $key) $rules[$key] = ['nullable', 'url:http,https', 'max:2048'];
        foreach (['support_links', 'editorial_links'] as $key) {
            $rules[$key] = ['present', 'array', 'max:8'];
            $rules[$key.'.*'] = ['array:label,url'];
            $rules[$key.'.*.label'] = ['required', 'string', 'max:120'];
            $rules[$key.'.*.url'] = ['required', 'string', 'max:2048', new \App\Rules\SafeSiteLink];
        }
        FooterSetting::updateOrCreate(['id' => 1], $request->validate($rules));
        return back()->with('success', 'Đã cập nhật footer trên các trang của website.');
    }
}

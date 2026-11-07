<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HeaderSetting;
use App\Rules\SafeSiteLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HeaderController extends Controller
{
    public function edit() { return view('admin.header.edit', ['settings'=>HeaderSetting::current()]); }

    public function update(Request $request)
    {
        $menus = $request->input('menus');
        if (is_array($menus)) {
            foreach ($menus as &$menu) {
                if (!is_array($menu)) continue;
                $menu['children'] ??= [];
                if (!is_array($menu['children'])) continue;
                $menu['children'] = array_values(array_filter($menu['children'], fn ($row) => !is_array($row) ||
                    ($row['label'] ?? '') !== '' && ($row['label'] ?? null) !== null || ($row['url'] ?? '') !== '' && ($row['url'] ?? null) !== null));
            }
            unset($menu);
            $request->merge(['menus'=>$menus]);
        }
        $data = $request->validate([
            'brand_name'=>['required','string','max:120'], 'logo'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],
            'login_label'=>['required','string','max:50'], 'join_label'=>['required','string','max:70'],
            'search_placeholder'=>['required','string','max:100'], 'show_search'=>['required','boolean'], 'show_membership'=>['required','boolean'],
            'menus'=>['required','array:'.implode(',',array_keys(HeaderSetting::MENUS)),'size:5'],
            'menus.*'=>['array:label,url,is_visible,sort_order,children'], 'menus.*.label'=>['required','string','max:70'],
            'menus.*.url'=>['required','string','max:2048',new SafeSiteLink], 'menus.*.is_visible'=>['required','boolean'],
            'menus.*.sort_order'=>['required','integer','between:0,99'], 'menus.*.children'=>['present','array','max:8'],
            'menus.*.children.*'=>['array:label,url,description'], 'menus.*.children.*.label'=>['required','string','max:120'],
            'menus.*.children.*.url'=>['required','string','max:2048',new SafeSiteLink], 'menus.*.children.*.description'=>['nullable','string','max:180'],
            'about_title'=>['required','string','max:120'], 'about_subtitle'=>['required','string','max:500'], 'about_body'=>['required','string','max:12000'],
        ]);
        unset($data['logo']);
        $setting = HeaderSetting::current();
        $oldLogo = $setting->logo_path;
        if ($request->hasFile('logo')) $data['logo_path'] = $request->file('logo')->store('website','public');
        HeaderSetting::updateOrCreate(['id'=>1], $data);
        if (isset($data['logo_path']) && $oldLogo) Storage::disk('public')->delete($oldLogo);
        return back()->with('success','Đã cập nhật header và thông tin website.');
    }
}

<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\MembershipService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MembershipController extends Controller
{
    public function index(Request $request, MembershipService $membership): View
    {
        return view('frontend.membership', $membership->pageData($request->user()));
    }
}

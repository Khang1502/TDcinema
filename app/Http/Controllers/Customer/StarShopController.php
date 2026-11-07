<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\FoodItem;
use App\Models\Movie;
use Illuminate\View\View;

class StarShopController extends Controller
{
    public function index(): View
    {
        $products = FoodItem::query()
            ->where('status', 'available')
            ->orderBy('price')
            ->orderBy('name')
            ->get();

        return view('frontend.star-shop', [
            'products' => $products,
            'navNow' => Movie::where('status', 'now_showing')->orderByDesc('release_date')->take(4)->get(),
            'navUpcoming' => Movie::where('status', 'upcoming')->orderByDesc('release_date')->take(4)->get(),
            'navImax' => Movie::imax()->orderByDesc('release_date')->take(4)->get(),
        ]);
    }
}

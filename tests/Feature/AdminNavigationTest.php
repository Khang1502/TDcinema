<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_keep_exactly_one_parent_menu_selected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $sections = [
            'movies' => 'Phim', 'genres' => 'Thể loại phim', 'banners' => 'Quản lý banner', 'cinema-corner' => 'Góc Điện Ảnh',
            'showtimes' => 'Suất chiếu', 'cinemas' => 'Hệ thống rạp', 'rooms' => 'Phòng chiếu',
            'seats' => 'Sơ đồ ghế', 'users' => 'Người dùng', 'gifts' => 'Quà tặng thành viên',
            'foods' => 'Dịch vụ & đồ ăn', 'promotions' => 'Khuyến mãi',
        ];
        foreach ($sections as $section => $label) {
            foreach (['index', 'create', 'edit'] as $page) {
                $this->assertActiveMenu("admin.{$section}.{$page}", $label);
            }
        }
        foreach ([
            'admin.dashboard' => 'Bảng điều khiển', 'admin.website.index' => 'Quản lý website',
            'admin.header.edit' => 'Header & website', 'admin.footer.edit' => 'Quản lý footer',
            'admin.showtimes.schedule' => 'Lịch chiếu', 'admin.tickets.show' => 'Vé & check-in',
            'admin.seats.map' => 'Sơ đồ ghế', 'admin.membership.index' => 'Thành viên & quyền lợi',
            'admin.permissions.edit' => 'Phân quyền admin', 'admin.promotions.display' => 'Khuyến mãi',
            'admin.movies.archive' => 'Phim', 'admin.movies.archive.show' => 'Phim',
            'admin.payments.index' => 'Quản lý thanh toán', 'admin.payments.show' => 'Quản lý thanh toán',
            'admin.payment-methods.index' => 'Phương thức thanh toán', 'admin.payment-methods.edit' => 'Phương thức thanh toán',
        ] as $route => $label) $this->assertActiveMenu($route, $label);
    }

    public function test_adding_an_admin_account_keeps_the_permissions_menu_selected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->assertActiveMenu('admin.users.create', 'Phân quyền admin', ['admin_only' => 1]);
        $this->assertActiveMenu('admin.users.edit', 'Phân quyền admin', ['from' => 'permissions']);
    }

    private function assertActiveMenu(string $name, string $label, array $query = []): void
    {
        $route = Route::getRoutes()->getByName($name);
        $this->assertNotNull($route, $name);
        $request = Request::create('/admin/navigation-test', 'GET', $query);
        $route = clone $route;
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);
        $this->app->instance('request', $request);
        $html = view('admin.components.sidebar')->render();
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new DOMXPath($document);
        $active = $xpath->query('//a[contains(concat(" ", normalize-space(@class), " "), " is-active ")]');
        $this->assertCount(1, $active, $name);
        $this->assertSame($label, trim($active->item(0)->textContent), $name);
        $this->assertSame('page', $active->item(0)->getAttribute('aria-current'), $name);
    }
}

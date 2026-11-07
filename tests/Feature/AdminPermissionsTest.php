<?php

namespace Tests\Feature;

use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Review;
use App\Models\Room;
use App\Models\Showtime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_basic_admin_can_manage_tickets_but_cannot_manage_movies(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get('/admin/tickets')->assertOk();
        $this->actingAs($staff)->get('/admin/movies')->assertForbidden();
        $this->actingAs($staff)->get('/admin/permissions')->assertForbidden();
        $this->actingAs($staff)->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('Tổng doanh thu')
            ->assertDontSee('Tải báo cáo Excel');
    }

    public function test_manager_can_manage_content_and_reports_but_not_permissions(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager)->get('/admin/movies')->assertOk();
        $this->actingAs($manager)->get('/admin/export-excel')->assertSuccessful();
        $this->actingAs($manager)->get('/admin/permissions')->assertForbidden();
        $this->actingAs($manager)->get('/admin/users')
            ->assertOk()
            ->assertDontSee('Thêm Người Dùng Mới');
    }

    public function test_super_admin_can_view_and_change_admin_role_assignments(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($superAdmin)
            ->get('/admin/permissions')
            ->assertOk()
            ->assertSee('Bảng phân quyền')
            ->assertSee('Xóa tài khoản');

        $this->actingAs($superAdmin)
            ->patch("/admin/permissions/{$staff->id}", ['role' => 'manager'])
            ->assertRedirect('/admin/permissions');

        $this->assertDatabaseHas('users', ['id' => $staff->id, 'role' => 'manager']);

        $this->actingAs($superAdmin)
            ->patch("/admin/permissions/{$superAdmin->id}", ['role' => 'staff'])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $superAdmin->id, 'role' => 'admin']);
    }

    public function test_admin_header_returns_to_customer_site_in_the_current_tab(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('href="'.route('home').'" class="admin-view-site"', false)
            ->assertSee('Về trang khách')
            ->assertDontSee('class="admin-view-site" target="_blank"', false);
    }

    public function test_super_admin_can_customize_permissions_for_each_admin_account(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($superAdmin)
            ->get("/admin/permissions/{$staff->id}/edit")
            ->assertOk()
            ->assertSee('Chỉnh quyền tài khoản');

        $this->patch("/admin/permissions/{$staff->id}", [
            'role' => 'staff',
            'permissions' => ['dashboard.view', 'movies.manage'],
        ])->assertRedirect('/admin/permissions');

        $this->assertDatabaseHas('users', ['id' => $staff->id, 'role' => 'staff']);
        $this->assertSame(['dashboard.view', 'movies.manage'], $staff->fresh()->admin_permissions);

        $this->actingAs($staff->fresh())->get('/admin/movies')->assertOk();
        $this->get('/admin/tickets')->assertForbidden();
        $this->get('/admin/permissions')->assertForbidden();
    }

    public function test_super_admin_can_edit_another_admin_account(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin', 'name' => 'Admin cũ']);

        $this->actingAs($superAdmin)
            ->get("/admin/users/{$otherAdmin->id}/edit")
            ->assertOk();

        $this->put("/admin/users/{$otherAdmin->id}", [
            'name' => 'Admin đã sửa',
            'email' => $otherAdmin->email,
            'role' => 'manager',
            'status' => 'active',
        ])->assertRedirect('/admin/users');

        $this->assertDatabaseHas('users', [
            'id' => $otherAdmin->id,
            'name' => 'Admin đã sửa',
            'role' => 'manager',
        ]);
    }

    public function test_super_admin_can_delete_another_admin_account_from_permission_page(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($superAdmin)
            ->delete("/admin/users/{$manager->id}", ['return_to' => 'permissions'])
            ->assertRedirect('/admin/permissions');

        $this->assertSoftDeleted('users', ['id' => $manager->id]);
        $this->assertDatabaseMissing('users', ['id' => $manager->id, 'deleted_at' => null]);
    }

    public function test_deleting_admin_keeps_historical_data_available(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);
        $movie = Movie::create(['title' => 'Phim lịch sử', 'duration' => 100, 'status' => 'now_showing']);
        $review = Review::create([
            'user_id' => $manager->id,
            'movie_id' => $movie->id,
            'rating' => 5,
            'comment' => 'Dữ liệu cần giữ lại',
        ]);

        $this->actingAs($superAdmin)
            ->delete("/admin/users/{$manager->id}", ['return_to' => 'permissions'])
            ->assertRedirect('/admin/permissions');

        $this->assertSoftDeleted('users', ['id' => $manager->id]);
        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'user_id' => $manager->id]);
        $this->assertTrue($review->fresh()->user->is($manager));
    }

    public function test_admin_created_from_permission_page_returns_to_admin_accounts(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($superAdmin)->post('/admin/users', [
            'name' => 'Nhân viên mới',
            'email' => 'nhanvienmoi@example.com',
            'role' => 'staff',
            'status' => 'active',
            'password' => 'matkhau123',
            'password_confirmation' => 'matkhau123',
            'return_to' => 'permissions',
        ])->assertRedirect('/admin/permissions');

        $this->assertDatabaseHas('users', [
            'email' => 'nhanvienmoi@example.com',
            'role' => 'staff',
        ]);
    }

    public function test_non_super_admin_cannot_receive_permission_management_permission(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($superAdmin)->patch("/admin/permissions/{$manager->id}", [
            'role' => 'manager',
            'permissions' => ['dashboard.view', 'permissions.manage', 'users.manage'],
        ])->assertRedirect('/admin/permissions');

        $this->assertSame(['dashboard.view'], $manager->fresh()->admin_permissions);
        $this->actingAs($manager)->get('/admin/permissions')->assertForbidden();
        $this->get('/admin/users/create')->assertForbidden();
    }

    public function test_customer_cannot_enter_admin_area(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->get('/admin/dashboard')->assertForbidden();
        $this->actingAs($customer)->get('/admin/tickets')->assertForbidden();
    }

    public function test_lower_roles_cannot_change_roles_or_export_reports_without_permission(): void
    {
        $target = User::factory()->create(['role' => 'staff']);
        foreach (['staff', 'manager', 'customer'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->patch("/admin/permissions/{$target->id}", ['role' => 'admin'])->assertForbidden();
            $this->patch("/admin/users/{$target->id}", ['role' => 'admin'])->assertForbidden();
        }
        $this->assertSame('staff', $target->fresh()->role);
        $this->actingAs($target)->get('/admin/export-excel')->assertForbidden();
    }

    public function test_blocked_admin_cannot_keep_using_an_existing_session(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'blocked', 'is_active' => false]);
        $this->actingAs($admin)->get('/admin/permissions')->assertForbidden();
    }

    public function test_manager_can_open_each_report_period(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager']));
        foreach (['day', 'month', 'year'] as $range) {
            $this->get('/admin/dashboard?range='.$range)->assertOk()->assertSee('Tổng doanh thu');
        }
    }

    public function test_ticket_filter_limits_showtime_options_to_avoid_memory_exhaustion(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cinema = Cinema::create(['name' => 'TD Test', 'address' => 'Test', 'city' => 'HCM']);
        $room = Room::create(['cinema_id' => $cinema->id, 'name' => 'Room 1', 'type' => '2D']);
        $movie = Movie::create(['title' => 'Phim test', 'duration' => 100, 'status' => 'now_showing']);

        foreach (range(1, 205) as $offset) {
            Showtime::create([
                'movie_id' => $movie->id,
                'room_id' => $room->id,
                'start_time' => now()->addMinutes($offset),
                'end_time' => now()->addMinutes($offset + 100),
                'base_price' => 80000,
                'status' => 'active',
            ]);
        }

        $this->actingAs($admin)
            ->get('/admin/tickets')
            ->assertOk()
            ->assertViewHas('showtimes', fn ($showtimes) => $showtimes->count() <= 200);

        $this->actingAs($admin)
            ->get('/admin/tickets/create')
            ->assertOk()
            ->assertViewHas('showtimes', fn ($showtimes) => $showtimes->count() <= 200);
    }
}

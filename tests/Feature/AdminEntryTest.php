<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_login_when_opening_the_admin_root(): void
    {
        $this->get('/admin')
            ->assertRedirectToRoute('login')
            ->assertSessionHas('url.intended', url('/admin'));
    }

    public function test_authorized_admin_roles_reach_the_dashboard_from_the_admin_root(): void
    {
        foreach (['admin', 'manager', 'staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/admin')
                ->assertRedirectToRoute('admin.dashboard');

            $this->get(route('admin.dashboard'))->assertOk();
        }
    }

    public function test_admin_root_keeps_the_dashboard_access_restrictions(): void
    {
        foreach ([
            ['role' => 'customer'],
            ['role' => 'admin', 'status' => 'blocked'],
            ['role' => 'admin', 'is_active' => false],
            ['role' => 'staff', 'admin_permissions' => []],
        ] as $attributes) {
            $this->actingAs(User::factory()->create($attributes))
                ->get('/admin')
                ->assertForbidden();
        }
    }
}

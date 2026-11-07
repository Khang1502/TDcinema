<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\NotificationMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class NotificationPopupTest extends TestCase
{
    use RefreshDatabase;

    private function notices(string $html): array
    {
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $data = (new \DOMXPath($document))->query('//script[@id="td-notification-data"]');
        $this->assertCount(1, $data);
        $this->assertSame(1, substr_count($html, 'id="td-notifications"'));
        $this->assertSame(1, substr_count($html, 'id="td-confirm"'));

        return json_decode($data->item(0)->textContent, true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_customer_and_admin_pages_display_all_four_flash_types_with_one_shared_popup(): void
    {
        $flash = ['success' => 'Đã lưu thay đổi.', 'error' => 'Không thể xử lý.', 'warning' => 'Vui lòng kiểm tra.', 'info' => 'Thông tin mới.'];
        $urls = [route('home'), route('movies.index'), route('cinemas.index'), route('star-shop'), route('promotions.index'), route('membership.index'), route('cinema-corner.index', 'blog'), route('login'), route('register')];
        foreach ($urls as $url) {
            $response = $this->withSession($flash)->get($url)->assertOk()->assertSee('assets/js/notifications.js')->assertSee('assets/css/notifications.css');
            $this->assertSame(array_keys($flash), array_column($this->notices($response->getContent()), 'type'));
        }
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        foreach (['profile.edit', 'customer.tickets', 'customer.gifts'] as $route) {
            $response = $this->withSession($flash)->get(route($route))->assertOk();
            $this->assertCount(4, $this->notices($response->getContent()));
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['admin.dashboard', 'admin.movies.index', 'admin.users.index', 'admin.tickets.index', 'admin.header.edit', 'admin.footer.edit', 'admin.promotions.index', 'admin.gifts.index', 'admin.membership.index'] as $route) {
            $response = $this->withSession($flash)->get(route($route))->assertOk();
            $this->assertCount(4, $this->notices($response->getContent()));
        }
    }

    public function test_all_named_validation_bags_are_grouped_without_losing_field_errors(): void
    {
        $errors = (new ViewErrorBag())->put('default', new MessageBag(['email' => ['Email chưa hợp lệ.']]))
            ->put('registration', new MessageBag(['phone' => ['Số điện thoại đã được đăng ký.'], 'email' => ['Email chưa hợp lệ.']]));
        $response = $this->withSession(['errors' => $errors])->get(route('register'))->assertOk()->assertSee('Số điện thoại đã được đăng ký.');
        $notices = $this->notices($response->getContent());
        $this->assertCount(1, $notices);
        $this->assertSame('error', $notices[0]['type']);
        $this->assertSame(['Email chưa hợp lệ.', 'Số điện thoại đã được đăng ký.'], $notices[0]['details']);
    }

    public function test_failed_admin_save_returns_validation_in_popup_without_changing_data(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->from(route('admin.foods.create'))->post(route('admin.foods.store'), ['name' => '', 'price' => -10, 'status' => 'invalid'])
            ->assertRedirect(route('admin.foods.create'))->assertSessionHasErrors(['name', 'price', 'status']);
        $response = $this->get(route('admin.foods.create'))->assertOk()->assertSee('data-inline-notification', false);
        $notices = $this->notices($response->getContent());
        $this->assertSame('error', $notices[0]['type']);
        $this->assertCount(3, $notices[0]['details']);
        $this->assertDatabaseCount('food_items', 0);
    }

    public function test_saved_data_redirects_to_a_success_popup_and_confirmation_keeps_the_delete_form(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post(route('admin.foods.store'), ['name' => 'Combo mới', 'price' => 80000, 'status' => 'available'])
            ->assertRedirect(route('admin.foods.index'))->assertSessionHas('success');
        $response = $this->get(route('admin.foods.index'))->assertOk()->assertSee('data-confirm="Xóa mục này?"', false)
            ->assertSee('name="_method" value="DELETE"', false)->assertDontSee('return confirm(', false);
        $this->assertSame('success', $this->notices($response->getContent())[0]['type']);
        $this->assertDatabaseHas('food_items', ['name' => 'Combo mới']);
    }

    public function test_messages_are_safe_json_and_do_not_expose_other_session_data(): void
    {
        $message = '</script><img src=x onerror="window.hacked=true">';
        $response = $this->withSession(['error' => $message, 'reset_link' => 'https://private.test/secret-token', 'booking.quick_selection' => ['token' => 'private-token']])
            ->get(route('login'))->assertOk()->assertDontSee($message, false);
        $notices = $this->notices($response->getContent());
        $this->assertSame($message, $notices[0]['message']);
        $this->assertStringNotContainsString('secret-token', json_encode($notices));
        $this->assertStringNotContainsString('private-token', json_encode($notices));
    }

    public function test_statuses_expiry_and_skipped_rooms_are_readable_and_duplicate_messages_are_merged(): void
    {
        $session = session()->driver();
        $session->put(['status' => 'profile-updated', 'success' => 'Đã cập nhật thông tin tài khoản.', 'session_expired' => 'Phiên giữ ghế đã hết.', 'seat_layout_skipped' => ['Rạp 1', 'Rạp 2']]);
        $notices = NotificationMessages::forPage($session, new ViewErrorBag());
        $this->assertCount(3, $notices);
        $this->assertSame('Đã cập nhật thông tin tài khoản.', $notices[0]['message']);
        $this->assertSame('warning', $notices[1]['type']);
        $this->assertSame(['Rạp 1', 'Rạp 2'], $notices[2]['details']);
        $this->assertSame(0, $notices[2]['duration']);
        $session->flush();
        $session->put('status', 'verification-link-sent');
        $this->assertSame('Đã gửi lại email xác thực.', NotificationMessages::forPage($session, new ViewErrorBag())[0]['message']);
    }

    public function test_payment_result_uses_success_and_failure_popups(): void
    {
        $success = view('frontend.booking.confirm', ['success' => true, 'demoPayment' => true])->render();
        $notices = $this->notices($success);
        $this->assertSame('success', $notices[0]['type']);
        $this->assertStringContainsString('Không có tiền được trừ', $notices[0]['message']);
        $failure = view('frontend.booking.confirm', ['success' => false, 'message' => 'Phiên thanh toán hết hạn.'])->render();
        $notices = $this->notices($failure);
        $this->assertSame('error', $notices[0]['type']);
        $this->assertSame('Phiên thanh toán hết hạn.', $notices[0]['message']);
    }
}

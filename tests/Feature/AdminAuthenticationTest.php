<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guest_is_redirected_to_admin_login_for_dashboard(): void
    {
        $response = $this->get('/admin/dashboard');

        $response->assertRedirect('/admin/login');
    }

    public function test_admin_can_login_and_logout_using_username_and_password(): void
    {
        $user = User::factory()->create([
            'username' => 'adminlogin',
            'password' => Hash::make('superadmin'),
            'is_active' => true,
        ]);

        $loginPage = $this->get('/admin/login');
        $loginPage->assertOk();
        $loginPage->assertSee('Admin Panel Login');

        $loginResponse = $this->post('/admin/login', [
            'username' => $user->username,
            'password' => 'superadmin',
        ]);

        $loginResponse->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($user);

        $logoutResponse = $this->post('/admin/logout');
        $logoutResponse->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_inactive_admin_cannot_login(): void
    {
        User::factory()->create([
            'username' => 'inactive-admin',
            'password' => Hash::make('superadmin'),
            'is_active' => false,
        ]);

        $response = $this->from('/admin/login')->post('/admin/login', [
            'username' => 'inactive-admin',
            'password' => 'superadmin',
        ]);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors([
            'username' => 'Akun admin Anda tidak aktif.',
        ]);
        $this->assertGuest();
    }
}

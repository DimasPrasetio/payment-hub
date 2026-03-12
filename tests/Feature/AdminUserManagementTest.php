<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_create_update_and_delete_other_admin_users(): void
    {
        $admin = User::factory()->create([
            'username' => 'rootadmin',
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        $indexResponse = $this->get('/admin/users');
        $indexResponse->assertOk();
        $indexResponse->assertSee('User Management');

        $storeResponse = $this->post('/admin/users', [
            'username' => 'operator1',
            'name' => 'Operator One',
            'email' => 'operator1@example.com',
            'password' => 'operator123',
            'is_active' => '1',
        ]);

        $user = User::query()->where('username', 'operator1')->firstOrFail();

        $storeResponse->assertRedirect('/admin/users/' . $user->id . '/edit');
        $storeResponse->assertSessionHas('success', 'User admin baru berhasil dibuat.');
        $this->assertTrue(Hash::check('operator123', $user->password));

        $updateResponse = $this->put('/admin/users/' . $user->id, [
            'username' => 'operator2',
            'name' => 'Operator Two',
            'email' => 'operator2@example.com',
            'password' => '',
            'is_active' => '0',
        ]);

        $updateResponse->assertRedirect('/admin/users/' . $user->id . '/edit');
        $updateResponse->assertSessionHas('success', 'User admin berhasil diperbarui.');

        $user->refresh();

        $this->assertSame('operator2', $user->username);
        $this->assertSame('Operator Two', $user->name);
        $this->assertSame('operator2@example.com', $user->email);
        $this->assertFalse($user->is_active);

        $deleteResponse = $this->delete('/admin/users/' . $user->id);
        $deleteResponse->assertRedirect('/admin/users');
        $deleteResponse->assertSessionHas('success', 'User admin berhasil dihapus.');
        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    public function test_admin_cannot_delete_currently_logged_in_account(): void
    {
        $admin = User::factory()->create([
            'username' => 'rootadmin2',
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        $response = $this->delete('/admin/users/' . $admin->id);

        $response->assertRedirect('/admin/users/' . $admin->id . '/edit');
        $response->assertSessionHas('error', 'Anda tidak dapat menghapus akun yang sedang digunakan.');
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Http\Requests\Admin\UserIndexRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;

class UserController extends AdminController
{
    public function index(UserIndexRequest $request): View
    {
        $filters = $request->validated();

        $users = User::query()
            ->when($filters['q'] ?? null, function ($query, $search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('username', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('is_active', $status === 'active'))
            ->latest('created_at')
            ->paginate(12)
            ->withQueryString();

        return $this->renderPage('admin.users.index', [
            'title' => 'Admin Users',
            'heading' => 'User Management',
            'kicker' => 'Administration',
            'description' => 'Kelola akun admin yang dapat mengakses panel operasional Payment Orchestrator.',
        ], [
            'usersList' => $users,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return $this->renderPage('admin.users.create', [
            'title' => 'Create Admin User',
            'heading' => 'Tambah User Admin',
            'kicker' => 'Administration',
            'description' => 'Buat akun admin baru untuk mengakses dashboard, konfigurasi aplikasi, dan provider.',
        ]);
    }

    public function store(StoreAdminUserRequest $request): RedirectResponse
    {
        $user = User::query()->create([
            'username' => $request->string('username')->toString(),
            'name' => $request->string('name')->toString(),
            'email' => $request->input('email'),
            'password' => Hash::make($request->string('password')->toString()),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('success', 'User admin baru berhasil dibuat.');
    }

    public function edit(User $user): View
    {
        return $this->renderPage('admin.users.edit', [
            'title' => "Edit {$user->username}",
            'heading' => 'Edit User Admin',
            'kicker' => 'Administration',
            'description' => 'Perbarui identitas, username login, status aktif, dan password user admin.',
        ], [
            'managedUser' => $user,
        ]);
    }

    public function update(UpdateAdminUserRequest $request, User $user): RedirectResponse
    {
        $payload = [
            'username' => $request->string('username')->toString(),
            'name' => $request->string('name')->toString(),
            'email' => $request->input('email'),
            'is_active' => $request->boolean('is_active'),
        ];

        if ($request->filled('password')) {
            $payload['password'] = Hash::make($request->string('password')->toString());
        }

        $user->fill($payload)->save();

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('success', 'User admin berhasil diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ((int) auth()->id() === (int) $user->id) {
            return redirect()
                ->route('admin.users.edit', $user)
                ->with('error', 'Anda tidak dapat menghapus akun yang sedang digunakan.');
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User admin berhasil dihapus.');
    }
}

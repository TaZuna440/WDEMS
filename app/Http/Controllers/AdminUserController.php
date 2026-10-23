<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/users/index', [
            'users' => User::query()
                ->select(['id', 'name', 'email', 'role', 'email_verified_at', 'created_at'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/users/create');
    }

    public function store(StoreUserRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $user = User::create($request->validated());
        $user->sendEmailVerificationNotification();

        $auditLogger->log('user.registered', $user, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'User created. A verification email has been sent to them.',
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'User created. A verification email has been sent to them.');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('admin/users/edit', [
            'user' => $user->only('id', 'name', 'email', 'role'),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validated();

        if ($request->user()->is($user) && $data['role'] === 'staff') {
            throw ValidationException::withMessages([
                'role' => 'You cannot change your own administrator role to staff.',
            ]);
        }

        $emailChanged = $data['email'] !== $user->email;
        $password = $data['password'] ?? null;
        unset($data['password'], $data['password_confirmation']);

        if ($password !== null && $password !== '') {
            $data['password'] = Hash::make($password);
        }

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $before = $user->only('name', 'email', 'role');
        $user->fill($data)->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        $auditLogger->log('user.updated', $user, [
            'before' => $before,
            'after' => $user->only('name', 'email', 'role'),
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(User $user, AuditLogger $auditLogger): RedirectResponse
    {
        if (request()->user()->is($user)) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own account.',
            ]);
        }

        if ($user->events()->exists()) {
            throw ValidationException::withMessages([
                'user' => 'This user cannot be deleted because they have created events. You must reassign or delete their events first.',
            ]);
        }

        $auditLogger->log('user.deleted', $user, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ]);
        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }
}

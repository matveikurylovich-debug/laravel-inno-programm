<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;


class AdminController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function index(): Response
    {
        Gate::authorize('access-admin');

        $users = User::with('roles')->get();
        return Inertia::render('Admin/Dashboard', [
            'users' => $users,
        ]);
    }

    public function updateRole(Request $request, User $user)
    {
        // Защита: не даем администратору снять роль с самого себя
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Нельзя изменить роль собственной учетной записи.');
        }

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(['admin', 'customer', 'analyst'])],
        ]);

        // Перезаписываем старые роли пользователя новой
        $user->syncRoles([$validated['role']]);

        return back()->with('message', 'Роль пользователя успешно обновлена.');
    }
}

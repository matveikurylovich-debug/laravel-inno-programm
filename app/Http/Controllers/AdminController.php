<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

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
}

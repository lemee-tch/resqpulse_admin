<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('first_name')->get();

        return view('users', compact('users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name'  => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name'   => ['required', 'string', 'max:255'],
            'suffix'      => ['nullable', 'string', 'max:20'],
            'email'       => ['required', 'email', 'unique:users,email'],
            'password'    => ['required', 'string', 'min:6'],
        ]);

        User::create([
            'first_name'  => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name'   => $validated['last_name'],
            'suffix'      => $validated['suffix'] ?? null,
            'name'        => $this->buildFullName($validated),
            'email'       => $validated['email'],
            'password'    => Hash::make($validated['password']),
        ]);

        return back()->with('success', "{$this->buildFullName($validated)} was added as an admin user.");
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'first_name'  => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name'   => ['required', 'string', 'max:255'],
            'suffix'      => ['nullable', 'string', 'max:20'],
            'email'       => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password'    => ['nullable', 'string', 'min:6'],
        ]);

        $user->first_name  = $validated['first_name'];
        $user->middle_name = $validated['middle_name'] ?? null;
        $user->last_name   = $validated['last_name'];
        $user->suffix      = $validated['suffix'] ?? null;
        $user->name        = $this->buildFullName($validated);
        $user->email       = $validated['email'];

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return back()->with('success', "{$user->name}'s account was updated.");
    }

    public function destroy(Request $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            return back()->withErrors(['user' => "You can't delete your own account while logged in."]);
        }

        $name = $user->name;
        $user->delete();

        return back()->with('success', "{$name} was removed.");
    }

    /**
     * Joins first/middle/last/suffix into the single display string
     * stored in `name` — same pattern used for Citizen::full_name and
     * Responder::full_name, so admin users stay consistent with the rest
     * of the app.
     */
    private function buildFullName(array $parts): string
    {
        return collect([
            $parts['first_name'] ?? null,
            $parts['middle_name'] ?? null,
            $parts['last_name'] ?? null,
            $parts['suffix'] ?? null,
        ])->filter(fn ($part) => filled($part))->implode(' ');
    }
}
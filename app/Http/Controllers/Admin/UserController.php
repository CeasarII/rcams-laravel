<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Models\Company;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Admin-only account management: create, view, edit, activate/deactivate.
 * No self-registration and no hard delete anywhere in this controller —
 * an account referenced by fund_requests, approvals, etc. must stay
 * addressable for audit history, so it's deactivated, never removed.
 */
class UserController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', User::class);

        $users = User::with(['roles', 'department', 'company'])
            ->orderBy('full_name')
            ->paginate(20);

        return view('admin.users.index', ['users' => $users]);
    }

    public function create()
    {
        $this->authorize('create', User::class);

        return view('admin.users.create', $this->formOptions());
    }

    public function store(UserStoreRequest $request)
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'employee_no' => $data['employee_no'],
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'department_id' => $data['department_id'],
                'company_id' => $data['company_id'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->syncAssignments($user, $data);

            return $user;
        });

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', "{$user->full_name} was created.");
    }

    public function show(User $user)
    {
        $this->authorize('view', $user);

        $user->load(['roles', 'department', 'company', 'vessels', 'departments']);

        return view('admin.users.show', ['user' => $user]);
    }

    public function edit(User $user)
    {
        $this->authorize('update', $user);

        $user->load(['roles', 'vessels', 'departments']);

        return view('admin.users.edit', $this->formOptions() + ['user' => $user]);
    }

    public function update(UserUpdateRequest $request, User $user)
    {
        $data = $request->validated();

        DB::transaction(function () use ($user, $data) {
            $user->fill([
                'employee_no' => $data['employee_no'],
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'department_id' => $data['department_id'],
                'company_id' => $data['company_id'],
            ]);

            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }

            $user->save();

            $this->syncAssignments($user, $data);
        });

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', "{$user->full_name} was updated.");
    }

    /**
     * Deactivate/reactivate — the only "removal" available. Financial
     * records referencing this user (fund_requests.requestor_id,
     * fund_request_approvals.approver_id, ...) must stay intact, so the
     * account is disabled, never deleted. Blocked from targeting yourself
     * by UserPolicy::toggleActive, so an Admin can't lock themselves out.
     */
    public function toggleActive(User $user)
    {
        $this->authorize('toggleActive', $user);

        $user->update(['is_active' => ! $user->is_active]);

        $status = $user->is_active ? 'reactivated' : 'deactivated';

        return back()->with('status', "{$user->full_name} was {$status}.");
    }

    private function syncAssignments(User $user, array $data): void
    {
        $user->roles()->sync($data['roles']);

        $roleCodes = Role::whereIn('id', $data['roles'])->pluck('code');

        // user_vessels is shared: an OE's vessels are the ones they may validate,
        // a Requestor's vessels are the ones they file Sea Base requests for
        // (auto-filled on the fund request form). Department scope is OE-only.
        // Rows are cleared for users who hold neither role, so nothing stale lingers.
        $usesVessels = $roleCodes->contains('OE') || $roleCodes->contains('REQUESTOR');

        $user->vessels()->sync($usesVessels ? ($data['vessel_ids'] ?? []) : []);
        $user->departments()->sync($roleCodes->contains('OE') ? ($data['department_ids'] ?? []) : []);
    }

    private function formOptions(): array
    {
        return [
            'roles' => Role::where('is_active', true)->orderBy('label')->get(),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'companies' => Company::where('is_active', true)->orderBy('name')->get(),
            'vessels' => Vessel::where('is_active', true)->orderBy('name')->get(),
        ];
    }
}

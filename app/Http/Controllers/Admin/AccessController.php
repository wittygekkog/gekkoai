<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccessController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($this->canManage($request), 403);
        $actor = $request->user('admin');
        $role = (string) $request->query('role', '');
        if (! in_array($role, ['', 'superadmin', 'admin'], true)) {
            $role = '';
        }
        if ($role === 'superadmin') {
            abort_unless($actor->isSuperAdmin(), 403);
        }
        $admins = Admin::query()
            ->when($role === 'superadmin', fn ($query) => $query->where('admin_type', 0))
            ->when($role === 'admin' || ! $actor->isSuperAdmin(), fn ($query) => $query->where('admin_type', 1))
            ->orderBy('username')
            ->paginate(150)
            ->withQueryString();

        return view('admin.access.index', compact('admins', 'role'));
    }

    public function create(Request $request): View
    {
        abort_unless($this->canManage($request), 403);

        return view('admin.access.form', [
            'account' => new Admin(['status' => 1, 'admin_type' => 1, 'access_scope' => 'assigned']),
            'tenants' => Tenant::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->canManage($request), 403);
        $data = $this->validated($request, null);
        $this->guardRole($request, (int) $data['admin_type']);
        $account = new Admin();
        $account->name = $data['name'];
        $account->username = $data['username'];
        $account->email = $data['email'];
        $account->password = $data['password'];
        $account->access_scope = $data['access_scope'];
        $account->must_change_password = true;
        $account->admin_type = (int) $data['admin_type'];
        $account->status = (int) $data['status'];
        $account->save();
        $this->syncMembership($account, $data['tenant_id'] ?? null, $data['tenant_role'] ?? null);

        return redirect()->route('admin.access.index')->with('success', 'Administrator created. They must change the password on first sign-in.');
    }

    public function edit(Request $request, Admin $admin): View
    {
        $this->guardTarget($request, $admin);

        return view('admin.access.form', [
            'account' => $admin,
            'tenants' => Tenant::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Admin $admin): RedirectResponse
    {
        $this->guardTarget($request, $admin);
        $data = $this->validated($request, $admin);
        $this->guardRole($request, (int) $data['admin_type']);
        $admin->name = $data['name'];
        $admin->username = $data['username'];
        $admin->email = $data['email'];
        $admin->access_scope = $data['access_scope'];
        $admin->admin_type = (int) $data['admin_type'];
        $admin->status = (int) $data['status'];
        if (! empty($data['password'])) {
            $admin->password = $data['password'];
            $admin->must_change_password = true;
        }
        $admin->save();
        $this->syncMembership($admin, $data['tenant_id'] ?? null, $data['tenant_role'] ?? null);

        return redirect()->route('admin.access.index')->with('success', 'Administrator updated.');
    }

    public function destroy(Request $request, Admin $admin): RedirectResponse
    {
        $this->guardTarget($request, $admin);
        if ($admin->id === $request->user('admin')->id) {
            return back()->with('error', 'You cannot delete the account you are using.');
        }
        $admin->status = 3;
        $admin->save();

        return redirect()->route('admin.access.index')->with('success', 'Administrator archived as deleted.');
    }

    private function canManage(Request $request): bool
    {
        $admin = $request->user('admin');

        return $admin->isSuperAdmin() || ($admin->admin_type === 1 && $admin->access_scope === 'platform');
    }

    private function guardTarget(Request $request, Admin $target): void
    {
        abort_unless($this->canManage($request), 403);
        if ($target->isSuperAdmin() && ! $request->user('admin')->isSuperAdmin()) {
            abort(403);
        }
    }

    private function guardRole(Request $request, int $type): void
    {
        if ($type === 0 && ! $request->user('admin')->isSuperAdmin()) {
            abort(403);
        }
    }

    private function validated(Request $request, ?Admin $account): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('admins', 'username')->ignore($account?->id)],
            'email' => ['required', 'email', 'max:190', Rule::unique('admins', 'email')->ignore($account?->id)],
            'password' => [$account ? 'nullable' : 'required', 'string', 'min:6', 'max:200'],
            'admin_type' => ['required', 'integer', 'in:0,1'],
            'status' => ['required', 'integer', 'in:0,1,2,3'],
            'access_scope' => ['required', 'in:platform,assigned'],
            'tenant_id' => ['nullable', 'uuid'],
            'tenant_role' => ['nullable', 'in:sbu_administrator,sbu_operator,sbu_viewer,human_reviewer'],
        ]);
    }

    private function syncMembership(Admin $account, ?string $tenantId, ?string $role): void
    {
        if ($account->access_scope !== 'assigned' || $tenantId === null || $role === null) {
            return;
        }
        TenantUser::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'user_id' => $account->id],
            ['role' => $role, 'status' => 'active'],
        );
    }
}

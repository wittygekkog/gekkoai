<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LogicItemRequest;
use App\Services\AccessService;
use App\Services\LogicService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Logic is the SBU screen for standing rules and worked instructions.
 */
class LogicController extends Controller
{
    public function __construct(
        private readonly AccessService $access,
        private readonly LogicService $logic,
    ) {}

    public function index(Request $request): View
    {
        $admin = $request->user('admin');
        $selected = $request->session()->get('selected_tenant_id');
        $tenants = $this->access->tenantsFor($admin);
        if ($this->access->isSpecificTenant($selected)) {
            $tenants = $tenants->where('id', $selected)->values();
        }

        return view('admin.logic.index', [
            'rules' => $this->logic->list($admin, $selected, 'rule'),
            'instructions' => $this->logic->list($admin, $selected, 'instruction'),
            'tenants' => $tenants,
            'canManage' => $tenants->contains(fn ($tenant): bool => $this->access->allows($admin, $tenant, 'ai.memory.manage')),
        ]);
    }

    public function store(LogicItemRequest $request): RedirectResponse
    {
        $this->logic->store($request->user('admin'), $request->validated());

        return back()->with('success', $request->validated('kind') === 'instruction'
            ? 'Instruction saved. It does not override an approval.'
            : 'Rule saved. It does not grant a new operation.');
    }
}

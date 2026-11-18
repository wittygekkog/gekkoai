<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\AiLogicItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Reads and stores the rules shown on the Logic screen.
 * Storage does not change authorization or enable a new operation.
 */
final class LogicService
{
    public function __construct(private readonly AccessService $access) {}

    public function list(Admin $admin, mixed $selectedTenantId, string $kind): LengthAwarePaginator
    {
        $ids = $this->access->scopedTenantIds($admin, $selectedTenantId);
        $pageName = $kind === 'instruction' ? 'instructions_page' : 'rules_page';

        $query = AiLogicItem::query()
            ->with('tenant')
            ->whereIn('tenant_id', $ids)
            ->where('kind', $kind);

        // A rule just saved should be the first accordion, still closed.
        if ($kind === 'rule') {
            $query->orderByDesc('created_at');
        } else {
            $query->orderBy('sort_order')->orderBy('title');
        }

        return $query->paginate(150, ['*'], $pageName)->withQueryString();
    }

    /**
     * @param  array{tenant_id: string, title: string, body: string}  $data
     */
    public function store(Admin $admin, array $data): AiLogicItem
    {
        $tenant = $this->access->tenantsFor($admin)->firstWhere('id', $data['tenant_id']);
        abort_if($tenant === null, 403);
        $this->access->assert($admin, $tenant, 'ai.memory.manage');

        $next = (int) AiLogicItem::query()
            ->where('tenant_id', $tenant->id)
            ->where('kind', 'rule')
            ->max('sort_order');

        $item = new AiLogicItem();
        $item->tenant_id = $tenant->id;
        $item->kind = 'rule';
        $item->slug = Str::slug($data['title']).'-'.Str::lower(Str::random(6));
        $item->title = $data['title'];
        $item->body = $data['body'];
        $item->status = 'active';
        $item->sort_order = $next + 1;
        $item->save();

        return $item;
    }

    /**
     * @param  array{title: string, body: string}  $data
     */
    public function update(Admin $admin, AiLogicItem $item, array $data): AiLogicItem
    {
        abort_unless($item->kind === 'rule', 404);
        $this->access->assert($admin, $item->tenant, 'ai.memory.manage');
        $item->title = $data['title'];
        $item->body = $data['body'];
        $item->save();

        return $item;
    }
}

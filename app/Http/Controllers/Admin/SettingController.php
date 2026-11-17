<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingRequest;
use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(private readonly SettingService $settings) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $group = (string) $request->query('group', '');
        $items = Setting::query()
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($inner) use ($q): void {
                    $inner->where('setting_slug', 'like', '%'.$q.'%')
                        ->orWhere('setting_name', 'like', '%'.$q.'%')
                        ->orWhere('help_text', 'like', '%'.$q.'%');
                });
            })
            ->when($group !== '' && isset(Setting::GROUPS[$group]), fn ($query) => $query->where('setting_group', $group))
            ->orderBy('setting_group')
            ->orderBy('sort_order')
            ->orderBy('setting_slug')
            ->paginate(150)
            ->withQueryString();

        return view('admin.settings.index', compact('items', 'q', 'group'));
    }

    public function create(): View
    {
        return view('admin.settings.form', [
            'setting' => new Setting(['input_type' => 'text', 'setting_group' => 'general', 'sort_order' => 0]),
        ]);
    }

    public function store(SettingRequest $request): RedirectResponse
    {
        $this->settings->create($request->validated(), $request->file('setting_file'));

        return redirect()->route('admin.settings.index')->with('success', 'Setting created.');
    }

    public function edit(Setting $setting): View
    {
        return view('admin.settings.form', compact('setting'));
    }

    public function update(SettingRequest $request, Setting $setting): RedirectResponse
    {
        $this->settings->update($setting, $request->validated(), $request->file('setting_file'));

        return redirect()->route('admin.settings.index')->with('success', 'Setting updated.');
    }

    public function destroy(Setting $setting): RedirectResponse
    {
        $this->settings->delete($setting);

        return redirect()->route('admin.settings.index')->with('success', 'Setting deleted.');
    }
}

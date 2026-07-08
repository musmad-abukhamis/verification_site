<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Site Settings — admin side.
 *
 * Port of nimcweb app/(Adminn)/admin/settings: edit the single-row `settings`
 * record holding site info and contact details (name, url, primary/secondary
 * email, phone, WhatsApp, office addresses). These values drive the user-facing
 * Help & Support page.
 *
 * Distinct from the existing pricing-oriented admin "Settings"
 * (SettingController / admin.settings.*). Logo uploads from the source are
 * intentionally omitted (the source's active code dropped them too).
 */
class SiteSettingController extends Controller
{
    /**
     * Editable text columns (logos excluded).
     */
    private const FIELDS = [
        'site_name', 'site_url', 'site_email', 'site_email2',
        'site_phone', 'site_phone2', 'whatsapp_url', 'whatsapp_url2',
        'office_address', 'office_address2',
    ];

    public function index()
    {
        return Inertia::render('Admin/SiteSettings/Index', [
            'settings' => $this->payload($this->current()),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'site_name' => 'nullable|string|max:255',
            'site_url' => 'nullable|url|max:255',
            'site_email' => 'nullable|email|max:255',
            'site_email2' => 'nullable|email|max:255',
            'site_phone' => 'nullable|string|max:50',
            'site_phone2' => 'nullable|string|max:50',
            'whatsapp_url' => 'nullable|url|max:255',
            'whatsapp_url2' => 'nullable|url|max:255',
            'office_address' => 'nullable|string',
            'office_address2' => 'nullable|string',
        ]);

        $settings = $this->current() ?? new Setting;
        $settings->fill($validated);
        $settings->save();

        return back()->with('success', 'Settings updated successfully!');
    }

    private function current(): ?Setting
    {
        return Setting::query()->orderBy('id')->first();
    }

    private function payload(?Setting $settings): array
    {
        $data = [];
        foreach (self::FIELDS as $field) {
            $data[$field] = $settings?->{$field};
        }

        return $data;
    }
}

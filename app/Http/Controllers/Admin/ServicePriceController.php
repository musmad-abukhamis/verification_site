<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ServicePrice;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Admin > Service Prices.
 *
 * Every service has one base price plus optional per-role overrides, stored one
 * row per (service, role) in service_prices. This replaced the single-row
 * ninServicePrices / verifyapiconfiq layout, which had exactly one price per
 * service and so could not express "agents pay less".
 */
class ServicePriceController extends Controller
{
    /**
     * Roles that can carry an override. ADMIN is excluded: it is a staff role,
     * not a price tier, and pricing it invites charging your own operators.
     */
    private const OVERRIDABLE_ROLES = [
        UserRole::AGENT,
        UserRole::API,
        UserRole::SMART,
        UserRole::USER,
    ];

    private function roleValues(): array
    {
        return array_map(fn (UserRole $role) => $role->value, self::OVERRIDABLE_ROLES);
    }

    public function index()
    {
        $rows = ServicePrice::indexed();

        $services = collect(ServicePrice::SERVICES)->map(function (array $meta, string $service) use ($rows) {
            $base = $rows[$service][ServicePrice::BASE] ?? null;

            $overrides = [];
            foreach ($this->roleValues() as $role) {
                $row = $rows[$service][$role] ?? null;
                $overrides[$role] = $row && $row->is_active ? (float) $row->price : null;
            }

            return [
                'service' => $service,
                'label' => $meta['label'],
                'group' => $meta['group'],
                'price' => $base ? (float) $base->price : 0.0,
                'is_active' => (bool) $base?->is_active,
                'overrides' => $overrides,
            ];
        })->values();

        return Inertia::render('Admin/ServicePrices/Index', [
            'services' => $services,
            'roles' => $this->roleValues(),
        ]);
    }

    /**
     * Save one service: its base price, its on/off state, and its overrides.
     *
     * A null override means "no override" and deletes the row, so a role falls
     * back to the base price. That is different from an override of 0, which is
     * a real price meaning free.
     */
    public function update(Request $request, string $service)
    {
        if (! array_key_exists($service, ServicePrice::SERVICES)) {
            return back()->withErrors(['price' => 'Unknown service.']);
        }

        $validated = $request->validate([
            'price' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
            'overrides' => 'array',
            // Keys are role names, so whitelist them: without this any string
            // becomes a row that no lookup will ever match.
            'overrides.*' => 'nullable|numeric|min:0',
        ] + collect($this->roleValues())->mapWithKeys(
            fn (string $role) => ["overrides.{$role}" => 'nullable|numeric|min:0']
        )->all());

        $unknown = array_diff(array_keys($request->input('overrides', [])), $this->roleValues());

        if ($unknown) {
            return back()->withErrors(['overrides' => implode(', ', $unknown).' cannot have their own price.']);
        }

        ServicePrice::updateOrCreate(
            ['service' => $service, 'role' => ServicePrice::BASE],
            ['price' => $validated['price'], 'is_active' => $validated['is_active']],
        );

        foreach ($validated['overrides'] ?? [] as $role => $price) {
            if ($price === null || $price === '') {
                ServicePrice::where('service', $service)->where('role', $role)->delete();

                continue;
            }

            ServicePrice::updateOrCreate(
                ['service' => $service, 'role' => $role],
                ['price' => $price, 'is_active' => true],
            );
        }

        ServicePrice::forgetCache();

        return back()->with('success', ServicePrice::label($service).' pricing updated.');
    }
}

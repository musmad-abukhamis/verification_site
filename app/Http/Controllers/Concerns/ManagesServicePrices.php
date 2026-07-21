<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\UserRole;
use App\Models\ServicePrice;
use Illuminate\Http\Request;

/**
 * Shared behaviour for the admin pages that edit service_prices rows
 * (Service Prices for NIN/slips, BVN Prices for the BVN services).
 *
 * Both present the same thing -- a base price, an on/off switch and optional
 * per-role overrides -- and differ only in which display groups they show.
 */
trait ManagesServicePrices
{
    /**
     * Roles that can carry an override. ADMIN is excluded: it is a staff role,
     * not a price tier, and pricing it invites charging your own operators.
     */
    protected function overridableRoles(): array
    {
        return array_map(
            fn (UserRole $role) => $role->value,
            [UserRole::AGENT, UserRole::API, UserRole::SMART, UserRole::USER],
        );
    }

    /**
     * Every service in the given display groups, with its base price, on/off
     * state and role overrides.
     */
    protected function servicesPayload(array $groups): array
    {
        $rows = ServicePrice::indexed();

        return collect(ServicePrice::SERVICES)
            ->filter(fn (array $meta) => in_array($meta['group'], $groups, true))
            ->map(function (array $meta, string $service) use ($rows) {
                $base = $rows[$service][ServicePrice::BASE] ?? null;

                $overrides = [];
                foreach ($this->overridableRoles() as $role) {
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
            })
            ->values()
            ->all();
    }

    /**
     * Save one service: base price, on/off state and overrides together.
     *
     * A null override means "no override" and deletes the row, so the role
     * falls back to the base price. That is different from an override of 0,
     * which is a real price meaning free.
     *
     * @param  array<int, string>  $groups  the groups this page is allowed to edit
     */
    protected function saveService(Request $request, string $service, array $groups)
    {
        $meta = ServicePrice::SERVICES[$service] ?? null;

        // Scoping to the page's own groups stops the BVN screen from being used
        // to rewrite NIN prices, and vice versa.
        if (! $meta || ! in_array($meta['group'], $groups, true)) {
            return back()->withErrors(['price' => 'Unknown service.']);
        }

        $validated = $request->validate([
            'price' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
            'overrides' => 'array',
            'overrides.*' => 'nullable|numeric|min:0',
        ]);

        $unknown = array_diff(array_keys($request->input('overrides', [])), $this->overridableRoles());

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

        // Prices are read through a 5-minute cache; without this the admin sees
        // the new price while users keep paying the old one.
        ServicePrice::forgetCache();

        return back()->with('success', ServicePrice::label($service).' pricing updated.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NinServicePrice;
use App\Models\VerifyApiConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

/**
 * Pricing is now stored in the single-row config tables verifyapiconfiq
 * (slip + verification prices) and ninServicePrices (per-service prices),
 * replacing the old ServicePrice / SlipType row tables.
 */
class ServicePriceController extends Controller
{
    /** Map a slip code to its verifyapiconfiq column. */
    private array $slipColumns = [
        'regular' => 'regslipprice',
        'standard' => 'standardslipsprice',
        'premium' => 'premiumslipprice',
        'nvs' => 'nvsslipprice',
        'advanced' => 'advslipprice',
    ];

    /**
     * Friendlier names for the ninServicePrices columns that drive live NIN
     * pricing, so an admin can tell which field bills which service. Anything
     * not listed keeps the humanised column name.
     */
    private array $serviceLabels = [
        'searchslip1' => 'NIN Verification',
        'phone_verify' => 'Phone Verification',
        'demo_verify' => 'Demographic Verification',
        'ipe' => 'IPE Clearance',
        'validation' => 'NIN Validation',
    ];

    private function verifyConfig(): VerifyApiConfig
    {
        return VerifyApiConfig::firstOrCreate(['id' => 'API1']);
    }

    private function ninPrices(): NinServicePrice
    {
        return NinServicePrice::firstOrCreate(['id' => 'API1']);
    }

    private function forgetCache(): void
    {
        Cache::forget('verifyapiconfiq.API1');
        NinServicePrice::forgetCache();
    }

    /**
     * Display the service prices management page
     */
    public function index()
    {
        $config = $this->verifyConfig();
        $nin = $this->ninPrices();

        // Slip types: one entry per verifyapiconfiq slip column.
        $slipTypes = collect($this->slipColumns)->values()->map(function ($column, $i) use ($config) {
            $code = array_search($column, $this->slipColumns, true);

            return [
                'id' => $code,
                'code' => $code,
                'name' => ucfirst($code).' Slip',
                'description' => '',
                'price' => (float) ($config->{$column} ?? 0),
                'is_active' => ! is_null($config->{$column}),
                'sort_order' => $i,
            ];
        });

        // Service prices: one entry per ninServicePrices column (id = column name).
        $servicePrices = collect($nin->getAttributes())
            ->except(['id'])
            ->map(fn ($value, $column) => [
                'id' => $column,
                'service_type' => $column,
                'name' => $this->serviceLabels[$column] ?? ucwords(str_replace('_', ' ', $column)),
                'price' => (float) $value,
                'is_active' => ! is_null($value),
            ])
            ->values();

        return Inertia::render('Admin/ServicePrices/Index', [
            'servicePrices' => $servicePrices,
            'config' => $config->toArray(),
            'slipTypes' => $slipTypes,
        ]);
    }

    /**
     * Update a per-service price (ninServicePrices column).
     */
    public function updateServicePrice(Request $request, string $servicePrice)
    {
        $validated = $request->validate([
            'price' => 'required|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        // $servicePrice is a column name straight off the URL. Without this it
        // would happily write any column on the row -- including `id`, which
        // would detach the config row the whole NIN section reads.
        if (! in_array($servicePrice, $this->serviceColumns(), true)) {
            return back()->withErrors(['price' => 'Unknown service.']);
        }

        $this->ninPrices()->update([
            $servicePrice => $this->columnValue($validated, fn ($price) => (string) $price),
        ]);
        // The NIN services read this row through a 5-minute cache, so without
        // this the admin sees the new price but users keep paying the old one.
        $this->forgetCache();

        return back()->with('success', 'Service price updated successfully.');
    }

    /**
     * Update a slip price (verifyapiconfiq column).
     */
    public function updateSlipType(Request $request, string $slipType)
    {
        $validated = $request->validate([
            'price' => 'required|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $column = $this->slipColumns[strtolower($slipType)] ?? 'standardslipsprice';
        $this->verifyConfig()->update([
            $column => $this->columnValue($validated, fn ($price) => (int) $price),
        ]);
        $this->forgetCache();

        return back()->with('success', 'Slip price updated successfully.');
    }

    /**
     * The editable columns on the ninServicePrices row.
     *
     * @return array<int, string>
     */
    private function serviceColumns(): array
    {
        return array_values(array_diff(array_keys($this->ninPrices()->getAttributes()), ['id']));
    }

    /**
     * What to write for a price column, honouring the Active checkbox.
     *
     * These single-row config tables have no separate enabled flag -- one
     * nullable column per service is all there is -- so "inactive" is stored as
     * NULL. That is also exactly what the services already treat as unpriced, so
     * they refuse the request instead of charging. The trade-off: switching a
     * service off discards its price, and you re-enter it to switch it back on.
     * This is how clearing a slip type has always behaved.
     */
    private function columnValue(array $validated, callable $cast)
    {
        return ($validated['is_active'] ?? true)
            ? $cast($validated['price'])
            : null;
    }

    /**
     * Set/create a slip price by code.
     */
    public function storeSlipType(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'price' => 'required|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $column = $this->slipColumns[strtolower($validated['code'])] ?? null;

        if (! $column) {
            return back()->withErrors(['code' => 'Unknown slip type. Allowed: '.implode(', ', array_keys($this->slipColumns))]);
        }

        $this->verifyConfig()->update([
            $column => $this->columnValue($validated, fn ($price) => (int) $price),
        ]);
        $this->forgetCache();

        return back()->with('success', 'Slip price saved successfully.');
    }

    /**
     * Clear a slip price (set the column to null).
     */
    public function destroySlipType(string $slipType)
    {
        $column = $this->slipColumns[strtolower($slipType)] ?? null;

        if ($column) {
            $this->verifyConfig()->update([$column => null]);
            $this->forgetCache();
        }

        return back()->with('success', 'Slip price cleared successfully.');
    }
}

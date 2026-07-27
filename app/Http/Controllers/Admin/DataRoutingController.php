<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataSetting;
use App\Models\DataTransactionAttempt;
use App\Models\NetworkPrefix;
use App\Models\NetworkVendorMapping;
use App\Models\Plan;
use App\Models\Vendor;
use App\Models\VendorRoute;
use App\Services\DataCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DataRoutingController extends Controller
{
    private const NETWORKS = ['mtn', 'airtel', 'glo', '9mobile'];

    public function index()
    {
        $types = Plan::query()->distinct()->orderBy('type')->pluck('type')->filter()->values()->all();

        $routes = VendorRoute::orderBy('position')->get()
            ->groupBy(fn (VendorRoute $r) => $r->network.'|'.$r->type)
            ->map(fn ($rows) => $rows->pluck('vendor_id')->all());

        $codes = NetworkVendorMapping::all()
            ->groupBy('network')
            ->map(fn ($rows) => $rows->pluck('external_network_code', 'vendor_id'));

        return Inertia::render('Admin/Data/Routing', [
            'networks' => self::NETWORKS,
            'types' => $types,
            'vendors' => Vendor::orderBy('priority')->get(['id', 'name', 'is_active'])
                ->map(fn (Vendor $v) => ['id' => $v->id, 'name' => $v->name, 'is_active' => $v->is_active]),
            'routes' => $routes,
            'networkCodes' => $codes,
            'settings' => [
                'failover_enabled' => DataSetting::bool('failover_enabled'),
                'failover_max_attempts' => DataSetting::int('failover_max_attempts'),
                'reconcile_cutoff_minutes' => DataSetting::int('reconcile_cutoff_minutes', 120),
                'requery_interval_seconds' => DataSetting::requeryIntervalSeconds(),
                'inline_settle_seconds' => DataSetting::int('inline_settle_seconds', 30),
            ],
            'prefixes' => NetworkPrefix::map(),
        ]);
    }

    public function updateRoutes(Request $request)
    {
        $validated = $request->validate([
            'routes' => ['array'],
            'routes.*.network' => ['required', 'string'],
            'routes.*.type' => ['required', 'string'],
            'routes.*.vendor_ids' => ['array'],
            'routes.*.vendor_ids.*' => ['string', 'exists:vendors,id'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['routes'] ?? [] as $route) {
                VendorRoute::where('network', $route['network'])->where('type', $route['type'])->delete();

                foreach (array_values($route['vendor_ids'] ?? []) as $i => $vendorId) {
                    VendorRoute::create([
                        'network' => $route['network'],
                        'type' => $route['type'],
                        'vendor_id' => $vendorId,
                        'position' => $i + 1,
                    ]);
                }
            }
        });

        return back()->with('success', 'Routing updated.');
    }

    public function updateNetworkCodes(Request $request)
    {
        $validated = $request->validate([
            'codes' => ['array'],
            'codes.*.network' => ['required', 'string'],
            'codes.*.vendor_id' => ['required', 'string', 'exists:vendors,id'],
            'codes.*.external_network_code' => ['nullable', 'string', 'max:50'],
        ]);

        foreach ($validated['codes'] ?? [] as $code) {
            $value = trim((string) ($code['external_network_code'] ?? ''));
            if ($value === '') {
                NetworkVendorMapping::where('network', $code['network'])->where('vendor_id', $code['vendor_id'])->delete();

                continue;
            }
            NetworkVendorMapping::updateOrCreate(
                ['network' => $code['network'], 'vendor_id' => $code['vendor_id']],
                ['external_network_code' => $value],
            );
        }

        return back()->with('success', 'Network codes updated.');
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'failover_enabled' => ['boolean'],
            'failover_max_attempts' => ['integer', 'min:0', 'max:20'],
            'reconcile_cutoff_minutes' => ['integer', 'min:5', 'max:1440'],
            // Floored at 5s: each run requeries every pending purchase, so a
            // tighter loop just floods the vendor.
            'requery_interval_seconds' => ['integer', 'min:5', 'max:3600'],
            // Capped at 45s: this holds a queue worker and must finish inside
            // the worker's per-job timeout, or the job is killed and retried.
            'inline_settle_seconds' => ['integer', 'min:0', 'max:45'],
        ]);

        // Every field is optional, so each one is read with its current value as
        // the default. Indexing $validated directly would 500 on any partial
        // post -- an older client, or a form that has not been rebuilt yet.
        DataSetting::put('failover_enabled', $request->boolean('failover_enabled'));
        DataSetting::put('failover_max_attempts', (int) ($validated['failover_max_attempts'] ?? DataSetting::int('failover_max_attempts')));
        DataSetting::put('reconcile_cutoff_minutes', (int) ($validated['reconcile_cutoff_minutes'] ?? DataSetting::int('reconcile_cutoff_minutes', 120)));
        DataSetting::put('requery_interval_seconds', (int) ($validated['requery_interval_seconds'] ?? DataSetting::requeryIntervalSeconds()));
        DataSetting::put('inline_settle_seconds', (int) ($validated['inline_settle_seconds'] ?? DataSetting::int('inline_settle_seconds', 30)));
        // The superseded minute key would otherwise win on the next fallback.
        DataSetting::put('requery_interval_minutes', 0);

        return back()->with('success', 'Settings saved.');
    }

    /**
     * Admin > Data (VTU) > Vendor Calls — every hop at one upstream vendor,
     * failed-over ones included. The counterpart to the verification module's
     * Provider Calls screen, and the only place a vendor that is quietly
     * rejecting everything shows up: failover hides it from the buyer.
     */
    public function attempts(Request $request)
    {
        $query = DataTransactionAttempt::query()
            ->with(['transaction:id,user_id,network,type,plan_name,phone,price,status', 'transaction.user:id,name,email']);

        if ($vendorId = $request->input('vendor_id')) {
            $query->where('vendor_id', $vendorId);
        }

        if ($outcome = $request->input('outcome')) {
            $query->where('outcome', $outcome);
        }

        if ($network = $request->input('network')) {
            $query->whereHas('transaction', fn ($q) => $q->where('network', $network));
        }

        // The transaction id doubles as the customer-facing reference, so one
        // box covers "what happened to Data_...?" and "what happened to that
        // phone number?" -- the two things support actually gets asked.
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('data_transaction_id', 'like', "%{$search}%")
                    ->orWhereHas('transaction', fn ($t) => $t->where('phone', 'like', "%{$search}%"));
            });
        }

        $attempts = $query->orderByDesc('created_at')
            ->paginate(25)
            ->through(fn (DataTransactionAttempt $a) => [
                'id' => $a->id,
                'reference' => $a->data_transaction_id,
                'vendor' => $a->vendor_name ?? '—',
                'user' => $a->transaction?->user?->name ?? $a->transaction?->user?->email,
                'network' => $a->transaction?->network,
                'plan' => $a->transaction?->plan_name,
                'phone' => $a->transaction?->phone,
                'txn_status' => $a->transaction?->status,
                'outcome' => $a->outcome,
                'http_status' => $a->http_status,
                'duration_ms' => $a->duration_ms,
                'message' => $a->message,
                'request_payload' => $a->request_payload,
                'response' => $a->response,
                'created_at' => $a->created_at,
            ])
            ->withQueryString();

        return Inertia::render('Admin/Data/Attempts', [
            'attempts' => $attempts,
            'networks' => self::NETWORKS,
            'vendors' => Vendor::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['vendor_id', 'outcome', 'network', 'search']),
            'summary' => $this->attemptSummary(),
        ]);
    }

    /**
     * Per-vendor success rate over the last 24 hours.
     *
     * Grouped on the denormalized vendor_name so deleted vendors still appear
     * rather than collapsing into one nameless bucket.
     *
     * @return array<int, array<string, mixed>>
     */
    private function attemptSummary(): array
    {
        return DataTransactionAttempt::query()
            ->where('created_at', '>=', now()->subDay())
            ->selectRaw('vendor_name, outcome, count(*) as total, avg(duration_ms) as avg_ms')
            ->groupBy('vendor_name', 'outcome')
            ->get()
            ->groupBy('vendor_name')
            ->map(function ($rows, $vendor) {
                $total = $rows->sum('total');
                $success = $rows->firstWhere('outcome', 'success')->total ?? 0;

                return [
                    'vendor' => $vendor ?: 'Unknown',
                    'total' => $total,
                    'success' => $success,
                    'success_rate' => $total > 0 ? round($success / $total * 100) : 0,
                    'avg_ms' => (int) round($rows->avg('avg_ms') ?? 0),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    public function addPrefix(Request $request)
    {
        $validated = $request->validate([
            'network' => ['required', 'string', 'max:50'],
            'prefix' => ['required', 'string', 'regex:/^\d{3,6}$/'],
        ]);

        NetworkPrefix::updateOrCreate([
            'network' => strtolower($validated['network']),
            'prefix' => $validated['prefix'],
        ]);
        DataCache::flush();

        return back()->with('success', 'Prefix added.');
    }

    public function removePrefix(Request $request)
    {
        $validated = $request->validate([
            'network' => ['required', 'string'],
            'prefix' => ['required', 'string'],
        ]);

        NetworkPrefix::where('network', strtolower($validated['network']))
            ->where('prefix', $validated['prefix'])
            ->delete();
        DataCache::flush();

        return back()->with('success', 'Prefix removed.');
    }
}

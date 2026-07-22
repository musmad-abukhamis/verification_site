<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VerificationAttempt;
use App\Models\VerificationProvider;
use App\Models\VerificationRoute;
use App\Models\VerificationSetting;
use App\Services\Verification\ServiceCatalog;
use App\Services\Verification\VerificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Admin > Verification > Routing & Failover.
 *
 * The ordered provider chain per service, plus the failover switches — the same
 * screen the data module has for vendors, over verification_routes.
 */
class VerificationRoutingController extends Controller
{
    public function index(VerificationDispatcher $dispatcher)
    {
        $providers = VerificationProvider::with('endpoints')
            ->orderBy('priority')
            ->orderBy('name')
            ->get();

        // Which providers even implement each service — the routing UI must not
        // offer a provider that has no endpoint for that row.
        $eligible = [];
        foreach (ServiceCatalog::keys() as $service) {
            $eligible[$service] = $providers
                ->filter(fn (VerificationProvider $p) => $p->endpointFor($service) !== null)
                ->map(fn (VerificationProvider $p) => $p->getKey())
                ->values()
                ->all();
        }

        $routes = VerificationRoute::orderBy('position')
            ->get()
            ->groupBy('service')
            ->map(fn ($rows) => $rows->pluck('provider_id')->all());

        // What the engine would actually do right now, unrouted fallback and
        // credential checks included — so the admin sees the effective chain,
        // not just what the matrix says.
        $effective = [];
        foreach (ServiceCatalog::keys() as $service) {
            $effective[$service] = $dispatcher->describeChain($service);
        }

        return Inertia::render('Admin/Verification/Routing', [
            'services' => ServiceCatalog::forFrontend(),
            'providers' => $providers->map(fn (VerificationProvider $p) => [
                'id' => $p->getKey(),
                'name' => $p->name,
                'is_active' => $p->is_active,
                'is_usable' => $p->isUsable(),
            ])->values(),
            'eligible' => $eligible,
            'routes' => $routes,
            'effective' => $effective,
            'settings' => [
                'failover_enabled' => VerificationSetting::bool('failover_enabled', true),
                'failover_max_attempts' => VerificationSetting::int('failover_max_attempts', 0),
                'attempt_retention_days' => VerificationSetting::int('attempt_retention_days', 30),
            ],
        ]);
    }

    public function updateRoutes(Request $request)
    {
        $validated = $request->validate([
            'routes' => ['array'],
            'routes.*.service' => ['required', Rule::in(ServiceCatalog::keys())],
            'routes.*.provider_ids' => ['array'],
            'routes.*.provider_ids.*' => ['string', 'exists:verification_providers,id'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['routes'] ?? [] as $route) {
                VerificationRoute::where('service', $route['service'])->delete();

                foreach (array_values(array_unique($route['provider_ids'] ?? [])) as $i => $providerId) {
                    VerificationRoute::create([
                        'service' => $route['service'],
                        'provider_id' => $providerId,
                        'position' => $i + 1,
                    ]);
                }
            }
        });

        return back()->with('success', 'Routing updated.');
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'failover_enabled' => ['boolean'],
            'failover_max_attempts' => ['integer', 'min:0', 'max:20'],
            'attempt_retention_days' => ['integer', 'min:1', 'max:365'],
        ]);

        VerificationSetting::put('failover_enabled', $request->boolean('failover_enabled'));
        VerificationSetting::put('failover_max_attempts', (int) $validated['failover_max_attempts']);
        VerificationSetting::put('attempt_retention_days', (int) $validated['attempt_retention_days']);

        return back()->with('success', 'Settings saved.');
    }

    /**
     * Admin > Verification > Provider Logs — every hop, including the failed
     * ones that were failed over, which is the only place a silent primary
     * outage is visible.
     */
    public function attempts(Request $request)
    {
        $query = VerificationAttempt::query()->with('user:id,name,email');

        if ($service = $request->input('service')) {
            $query->where('service', $service);
        }

        if ($providerId = $request->input('provider_id')) {
            $query->where('provider_id', $providerId);
        }

        if ($outcome = $request->input('outcome')) {
            $query->where('outcome', $outcome);
        }

        if ($search = $request->input('search')) {
            $query->where('reference', 'like', "%{$search}%");
        }

        $attempts = $query->orderByDesc('created_at')
            ->paginate(25)
            ->through(fn (VerificationAttempt $a) => [
                'id' => $a->id,
                'service' => $a->service,
                'service_label' => ServiceCatalog::label($a->service),
                'provider' => $a->provider_name,
                'user' => $a->user?->name ?? $a->user?->email,
                'reference' => $a->reference,
                'outcome' => $a->outcome,
                'http_status' => $a->http_status,
                'duration_ms' => $a->duration_ms,
                'message' => $a->message,
                'request_payload' => $a->request_payload,
                'response' => $a->response,
                'created_at' => $a->created_at,
            ])
            ->withQueryString();

        return Inertia::render('Admin/Verification/Attempts', [
            'attempts' => $attempts,
            'services' => ServiceCatalog::forFrontend(),
            'providers' => VerificationProvider::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['service', 'provider_id', 'outcome', 'search']),
            'summary' => $this->summary(),
        ]);
    }

    /**
     * Per-provider success rate over the last 24 hours. A primary that is
     * quietly failing every call still "works" from the user's side because
     * failover covers it — this is where that shows up.
     *
     * @return array<int, array<string, mixed>>
     */
    private function summary(): array
    {
        return VerificationAttempt::query()
            ->where('created_at', '>=', now()->subDay())
            ->selectRaw('provider_name, outcome, count(*) as total, avg(duration_ms) as avg_ms')
            ->groupBy('provider_name', 'outcome')
            ->get()
            ->groupBy('provider_name')
            ->map(function ($rows, $provider) {
                $total = $rows->sum('total');
                $success = $rows->firstWhere('outcome', 'success')->total ?? 0;

                return [
                    'provider' => $provider,
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
}

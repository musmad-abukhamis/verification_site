<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DataPlanRequest;
use App\Models\Plan;
use App\Models\PlanVendorMapping;
use App\Models\Vendor;
use App\Services\DataCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DataPlanController extends Controller
{
    public function index(Request $request)
    {
        $query = Plan::query()->withCount('vendorMappings');

        if ($search = $request->input('search')) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('network', 'like', "%{$search}%")
                ->orWhere('type', 'like', "%{$search}%"));
        }
        if (($network = $request->input('network')) && $network !== 'all') {
            $query->where('network', $network);
        }

        $plans = $query->orderBy('network')->orderBy('type')->orderBy('price')
            ->paginate(20)
            ->through(fn (Plan $p) => [
                'id' => $p->id,
                'network' => $p->network,
                'type' => $p->type,
                'name' => $p->name,
                'price' => (float) $p->price,
                'agent_price' => (float) $p->agent_price,
                'api_price' => (float) $p->api_price,
                'validity' => $p->validity,
                'status' => $p->status,
                'plan_status' => $p->plan_status,
                'mappings_count' => $p->vendor_mappings_count,
            ])
            ->withQueryString();

        return Inertia::render('Admin/DataPlans/Index', [
            'plans' => $plans,
            'filters' => $request->only(['search', 'network']),
            'networks' => Plan::query()->distinct()->orderBy('network')->pluck('network'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/DataPlans/Form', [
            'plan' => null,
            'vendors' => $this->vendorOptions(),
            'mappings' => [],
        ]);
    }

    public function edit(Plan $dataplan)
    {
        $mappings = $dataplan->vendorMappings()
            ->pluck('external_plan_id', 'vendor_id');

        return Inertia::render('Admin/DataPlans/Form', [
            'plan' => [
                'id' => $dataplan->id,
                'network' => $dataplan->network,
                'type' => $dataplan->type,
                'name' => $dataplan->name,
                'price' => (float) $dataplan->price,
                'agent_price' => (float) $dataplan->agent_price,
                'api_price' => (float) $dataplan->api_price,
                'validity' => $dataplan->validity,
                'status' => $dataplan->status,
                'plan_status' => $dataplan->plan_status,
            ],
            'vendors' => $this->vendorOptions(),
            'mappings' => $mappings,
        ]);
    }

    public function store(DataPlanRequest $request)
    {
        DB::transaction(function () use ($request) {
            $plan = Plan::create($request->safe()->except('mappings'));
            $this->syncMappings($plan, $request->input('mappings', []));
        });

        DataCache::flush();

        return redirect()->route('admin.dataplan.index')->with('success', 'Plan created.');
    }

    public function update(DataPlanRequest $request, Plan $dataplan)
    {
        DB::transaction(function () use ($request, $dataplan) {
            $dataplan->update($request->safe()->except('mappings'));
            $this->syncMappings($dataplan, $request->input('mappings', []));
        });

        DataCache::flush();

        return redirect()->route('admin.dataplan.index')->with('success', 'Plan updated.');
    }

    public function toggleStatus(Plan $dataplan)
    {
        $dataplan->update(['status' => $dataplan->status === 'on' ? 'off' : 'on']);
        DataCache::flush();

        return back()->with('success', 'Type availability updated.');
    }

    public function togglePlanStatus(Plan $dataplan)
    {
        $dataplan->update(['plan_status' => $dataplan->plan_status === 'on' ? 'off' : 'on']);
        DataCache::flush();

        return back()->with('success', 'Plan visibility updated.');
    }

    public function destroy(Plan $dataplan)
    {
        $dataplan->delete(); // cascades mappings
        DataCache::flush();

        return redirect()->route('admin.dataplan.index')->with('success', 'Plan deleted.');
    }

    /**
     * @param  array<int, array{vendor_id: string, external_plan_id: ?string}>  $mappings
     */
    private function syncMappings(Plan $plan, array $mappings): void
    {
        $keep = [];
        foreach ($mappings as $m) {
            $code = trim((string) ($m['external_plan_id'] ?? ''));
            if ($code === '') {
                continue; // no code → no mapping for this vendor
            }
            PlanVendorMapping::updateOrCreate(
                ['plan_id' => $plan->id, 'vendor_id' => $m['vendor_id']],
                ['external_plan_id' => $code],
            );
            $keep[] = $m['vendor_id'];
        }

        $plan->vendorMappings()->whereNotIn('vendor_id', $keep)->delete();
    }

    private function vendorOptions()
    {
        return Vendor::orderBy('priority')->get(['id', 'name', 'is_active'])
            ->map(fn (Vendor $v) => ['id' => $v->id, 'name' => $v->name, 'is_active' => $v->is_active]);
    }
}

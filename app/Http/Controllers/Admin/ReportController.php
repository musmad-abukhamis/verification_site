<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NinDetail;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/**
 * Admin-side report pages ported from nimcweb's admin "Transactions"/"Reports"
 * sidebar groups:
 *   - NIN/BVN Transactions (/admin/verifytrans)   → full NINDetails list (all users)
 *   - Data Sub Stats        (/admin/user-data-stats) → data-purchase analytics
 *   - Verification Stats     (/admin/verify-stats)    → NINDetails groupBy analytics
 *
 * Admin Data Transactions already exists (admin.transactions.index +
 * admin.data-management.transactions), so it is not rebuilt here.
 *
 * Both stats dashboards support an optional ?userId filter (all | specific user).
 */
class ReportController extends Controller
{
    /* ======================================================================
     | NIN/BVN Transactions (list, all users)
     | ====================================================================== */
    public function verifyTransactions(Request $request)
    {
        $query = NinDetail::query()->with('user')->orderByDesc('createdAt');

        if ($search = $request->input('search')) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('idvalue', 'like', "%{$search}%")
                    ->orWhere('surname', 'like', "%{$search}%")
                    ->orWhere('othernames', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        if (($idtype = $request->input('idtype')) && $idtype !== 'all') {
            $query->where('idtype', $idtype);
        }

        $transactions = $query->paginate(20)->through(fn (NinDetail $n) => [
            'id' => $n->id,
            'user' => $n->user?->name ?? $n->user?->username ?? 'Unknown',
            'idtype' => $n->idtype,
            'idvalue' => $n->idvalue,
            'sliptype' => $n->sliptype,
            'channel' => $n->channel,
            'name' => trim(($n->surname ?? '').' '.($n->othernames ?? '')) ?: '—',
            'price' => (float) ($n->price ?? 0),
            'status' => $n->status,
            'created_at' => $n->createdAt?->format('M d, Y H:i'),
        ])->withQueryString();

        return Inertia::render('Admin/Reports/VerifyTransactions', [
            'transactions' => $transactions,
            'filters' => $request->only(['search', 'idtype']),
            'idtypes' => NinDetail::query()->distinct()->orderBy('idtype')->pluck('idtype')->filter()->values(),
            'stats' => [
                'total_count' => NinDetail::count(),
                'total_revenue' => (float) NinDetail::where('status', 'success')->sum('price'),
            ],
        ]);
    }

    /* ======================================================================
     | Data Sub Stats (analytics, all users or a specific user)
     | ====================================================================== */
    public function dataStats(Request $request)
    {
        [$from, $to, $preset] = $this->resolveRange($request);
        $userId = $request->input('userId', 'all');

        $query = Transaction::where('type', 'data');
        $this->applyUser($query, $userId);
        $this->applyRange($query, $from, $to);

        $transactions = $query->orderBy('createdAt')->get();

        $isSuccess = fn (Transaction $t) => strtolower((string) $t->status) === 'success';

        $networkStats = [];
        foreach ($transactions as $t) {
            $key = $t->network ?: 'Unknown';
            $networkStats[$key] ??= ['network' => $key, 'total' => 0, 'success' => 0, 'amount' => 0.0, 'dataGB' => 0.0];
            $networkStats[$key]['total']++;
            if ($isSuccess($t)) {
                $networkStats[$key]['success']++;
                $networkStats[$key]['amount'] += (float) $t->price;
                $networkStats[$key]['dataGB'] += $this->parseDataMB($t->name) / 1000;
            }
        }
        foreach ($networkStats as &$ns) {
            $ns['dataGB'] = round($ns['dataGB'], 2);
        }
        unset($ns);

        $daily = $this->dailyTrend($transactions, $isSuccess, fn (Transaction $t) => $this->parseDataMB($t->name) / 1000);

        $successful = $transactions->filter($isSuccess);
        $revenue = (float) $successful->sum('price');
        $dataGB = round($successful->sum(fn (Transaction $t) => $this->parseDataMB($t->name)) / 1000, 2);

        return Inertia::render('Admin/Reports/DataStats', [
            'filters' => ['preset' => $preset, 'from' => $from?->toDateString(), 'to' => $to?->toDateString(), 'userId' => (string) $userId],
            'range' => $this->rangeLabel($from, $to),
            'users' => $this->userOptions(),
            'overall' => [
                'total' => $transactions->count(),
                'success' => $successful->count(),
                'failed' => $transactions->count() - $successful->count(),
                'success_rate' => $transactions->count() > 0 ? round($successful->count() / $transactions->count() * 100, 1) : 0,
                'revenue' => $revenue,
                'data_gb' => $dataGB,
                'avg_value' => $successful->count() > 0 ? round($revenue / $successful->count()) : 0,
            ],
            'networkStats' => array_values($networkStats),
            'daily' => $daily,
        ]);
    }

    /* ======================================================================
     | Verification Stats (NINDetails groupBy, all users or a specific user)
     | ====================================================================== */
    public function verifyStats(Request $request)
    {
        [$from, $to, $preset] = $this->resolveRange($request);
        $userId = $request->input('userId', 'all');

        $base = function () use ($userId, $from, $to) {
            $q = NinDetail::query();
            $this->applyUser($q, $userId);
            $this->applyRange($q, $from, $to);

            return $q;
        };

        $total = (clone $base())->count();

        $groupCount = fn (string $column) => (clone $base())
            ->selectRaw("{$column} as label, COUNT(*) as count")
            ->groupBy($column)
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => ['label' => $r->label ?: 'Unknown', 'count' => (int) $r->count])
            ->values();

        // idtype × status matrix
        $idtypeStatus = (clone $base())
            ->selectRaw('idtype, status, COUNT(*) as count')
            ->groupBy('idtype', 'status')
            ->get()
            ->map(fn ($r) => ['idtype' => strtoupper($r->idtype ?: 'Unknown'), 'status' => $r->status ?: 'unknown', 'count' => (int) $r->count])
            ->values();

        $revenue = (float) (clone $base())->where('status', 'success')->sum('price');

        return Inertia::render('Admin/Reports/VerifyStats', [
            'filters' => ['preset' => $preset, 'from' => $from?->toDateString(), 'to' => $to?->toDateString(), 'userId' => (string) $userId],
            'range' => $this->rangeLabel($from, $to),
            'users' => $this->userOptions(),
            'overall' => [
                'total' => $total,
                'revenue' => $revenue,
            ],
            'idtypeCounts' => $groupCount('idtype')->map(fn ($r) => ['label' => strtoupper($r['label']), 'count' => $r['count']])->values(),
            'channelCounts' => $groupCount('channel'),
            'statusCounts' => $groupCount('status'),
            'idtypeStatus' => $idtypeStatus,
        ]);
    }

    /* ======================================================================
     | Helpers
     | ====================================================================== */

    private function applyUser(Builder $query, string $userId): void
    {
        if ($userId !== 'all' && $userId !== '') {
            $query->where('userId', $userId);
        }
    }

    private function applyRange(Builder $query, ?Carbon $from, ?Carbon $to): void
    {
        if ($from && $to) {
            $query->whereBetween('createdAt', [$from, $to]);
        }
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon, 2: string}
     */
    private function resolveRange(Request $request): array
    {
        $preset = $request->input('preset', 'last30days');
        $now = now();

        return match ($preset) {
            'all-time' => [null, null, 'all-time'],
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay(), $preset],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay(), $preset],
            'last7days' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay(), $preset],
            'last90days' => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay(), $preset],
            'custom' => $this->customRange($request, $now),
            default => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay(), 'last30days'],
        };
    }

    private function customRange(Request $request, Carbon $now): array
    {
        $from = $request->input('from') ? Carbon::parse($request->input('from'))->startOfDay() : $now->copy()->subDays(29)->startOfDay();
        $to = $request->input('to') ? Carbon::parse($request->input('to'))->endOfDay() : $now->copy()->endOfDay();

        return [$from, $to, 'custom'];
    }

    private function rangeLabel(?Carbon $from, ?Carbon $to): array
    {
        return [
            'from' => $from?->format('M d, Y') ?? 'Beginning',
            'to' => $to?->format('M d, Y') ?? 'Now',
        ];
    }

    /** @return array<int, array{value: string, label: string}> */
    private function userOptions(): array
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'email'])
            ->map(fn (User $u) => ['value' => (string) $u->id, 'label' => $u->name ?: $u->username ?: $u->email])
            ->prepend(['value' => 'all', 'label' => 'All Users'])
            ->values()
            ->all();
    }

    private function parseDataMB(?string $name): float
    {
        if (! $name || ! preg_match('/(\d+(?:\.\d+)?)\s*(GB|MB)/i', $name, $m)) {
            return 0.0;
        }

        $amount = (float) $m[1];

        return strtoupper($m[2]) === 'GB' ? $amount * 1000 : $amount;
    }

    private function dailyTrend($records, callable $isSuccess, callable $extraValue): array
    {
        $daily = [];
        foreach ($records as $r) {
            $date = $r->createdAt?->toDateString();
            if (! $date) {
                continue;
            }
            $daily[$date] ??= ['date' => $date, 'total' => 0, 'success' => 0, 'revenue' => 0.0, 'value' => 0.0];
            $daily[$date]['total']++;
            if ($isSuccess($r)) {
                $daily[$date]['success']++;
                $daily[$date]['revenue'] += (float) ($r->price ?? 0);
                $daily[$date]['value'] += (float) $extraValue($r);
            }
        }
        ksort($daily);

        return array_values(array_map(function ($d) {
            $d['revenue'] = round($d['revenue'], 2);
            $d['value'] = round($d['value'], 2);

            return $d;
        }, $daily));
    }
}

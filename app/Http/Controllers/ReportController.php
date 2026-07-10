<?php

namespace App\Http\Controllers;

use App\Models\DataTransaction;
use App\Models\NinDetail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * User-facing report pages ported from nimcweb's "Transactions" / "Reports"
 * sidebar groups:
 *   - Data Transactions   → Transactions table (type = data)
 *   - NIN/BVN Transactions → NINDetails table
 *   - Data Sub Stats       → analytics over the user's data purchases
 *   - NIN/BVN Verify Stats → analytics over the user's NINDetails (net-new analogue)
 *
 * All four are scoped to the authenticated user (nimcweb keyed the stats pages
 * off session.user.id).
 */
class ReportController extends Controller
{
    /* ======================================================================
     | Data Transactions (list)
     | ====================================================================== */
    public function dataTransactions(Request $request)
    {
        $base = DataTransaction::where('user_id', Auth::id());

        $query = clone $base;

        if ($search = $request->input('search')) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('network', 'like', "%{$search}%")
                    ->orWhere('plan_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        if (($status = $request->input('status')) && $status !== 'all') {
            $query->where('status', $status);
        }

        if (($network = $request->input('network')) && $network !== 'all') {
            $query->where('network', $network);
        }

        $transactions = $query
            ->orderByDesc('created_at')
            ->paginate(15)
            ->through(fn (DataTransaction $t) => [
                'id' => $t->id,
                'network' => $t->network,
                'name' => $t->plan_name,
                'price' => (float) $t->price,
                'type' => $t->type,
                'phone' => $t->phone,
                'old_balance' => (float) $t->oldbal,
                'new_balance' => (float) $t->newbal,
                'status' => $t->status,
                'date' => $t->created_at?->format('M d, Y H:i'),
            ])
            ->withQueryString();

        return Inertia::render('Reports/DataTransactions', [
            'transactions' => $transactions,
            'filters' => $request->only(['search', 'status', 'network']),
            'networks' => (clone $base)->distinct()->orderBy('network')->pluck('network')->filter()->values(),
            'stats' => [
                'total_count' => (clone $base)->count(),
                'success_count' => (clone $base)->where('status', 'success')->count(),
                'total_spent' => (float) (clone $base)->where('status', 'success')->sum('price'),
            ],
        ]);
    }

    /* ======================================================================
     | NIN/BVN Transactions (list)
     | ====================================================================== */
    public function verifyTransactions(Request $request)
    {
        $base = NinDetail::where('userId', Auth::id());

        $query = clone $base;

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

        $transactions = $query
            ->orderByDesc('createdAt')
            ->paginate(15)
            ->through(fn (NinDetail $n) => [
                'id' => $n->id,
                'idtype' => $n->idtype,
                'idvalue' => $n->idvalue,
                'sliptype' => $n->sliptype,
                'name' => trim(($n->surname ?? '').' '.($n->othernames ?? '')) ?: '—',
                'price' => (float) ($n->price ?? 0),
                'old_balance' => (float) $n->oldBal,
                'new_balance' => (float) $n->newBal,
                'status' => $n->status,
                'date' => $n->createdAt?->format('M d, Y H:i'),
            ])
            ->withQueryString();

        return Inertia::render('Reports/VerifyTransactions', [
            'transactions' => $transactions,
            'filters' => $request->only(['search', 'idtype']),
            'idtypes' => (clone $base)->distinct()->orderBy('idtype')->pluck('idtype')->filter()->values(),
            'stats' => [
                'total_count' => (clone $base)->count(),
                'total_spent' => (float) (clone $base)->sum('price'),
            ],
        ]);
    }

    /* ======================================================================
     | Data Sub Stats (analytics)
     | ====================================================================== */
    public function dataStats(Request $request)
    {
        [$from, $to, $preset] = $this->resolveRange($request);

        $transactions = DataTransaction::where('user_id', Auth::id())
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get();

        $isSuccess = fn (DataTransaction $t) => strtolower((string) $t->status) === 'success';

        // Network breakdown
        $networkStats = [];
        foreach ($transactions as $t) {
            $key = $t->network ?: 'Unknown';
            $networkStats[$key] ??= ['network' => $key, 'total' => 0, 'success' => 0, 'amount' => 0.0, 'dataGB' => 0.0];
            $networkStats[$key]['total']++;
            if ($isSuccess($t)) {
                $networkStats[$key]['success']++;
                $networkStats[$key]['amount'] += (float) $t->price;
                $networkStats[$key]['dataGB'] += $this->parseDataMB($t->plan_name) / 1000;
            }
        }
        foreach ($networkStats as &$ns) {
            $ns['dataGB'] = round($ns['dataGB'], 2);
        }
        unset($ns);

        // Daily trend
        $daily = $this->dailyTrend($transactions, $isSuccess, fn (DataTransaction $t) => $this->parseDataMB($t->plan_name) / 1000);

        $successful = $transactions->filter($isSuccess);
        $revenue = (float) $successful->sum('price');
        $dataGB = round($successful->sum(fn (DataTransaction $t) => $this->parseDataMB($t->plan_name)) / 1000, 2);

        return Inertia::render('Reports/DataStats', [
            'filters' => ['preset' => $preset, 'from' => $from->toDateString(), 'to' => $to->toDateString()],
            'range' => ['from' => $from->format('M d, Y'), 'to' => $to->format('M d, Y')],
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
     | NIN/BVN Verify Stats (analytics) — net-new analogue over NINDetails
     | ====================================================================== */
    public function verifyStats(Request $request)
    {
        [$from, $to, $preset] = $this->resolveRange($request);

        $records = NinDetail::where('userId', Auth::id())
            ->whereBetween('createdAt', [$from, $to])
            ->orderBy('createdAt')
            ->get();

        $isSuccess = fn (NinDetail $n) => in_array(strtolower((string) $n->status), ['success', 'completed', 'found'], true);

        // ID-type breakdown (bvn / nin / …)
        $typeStats = [];
        foreach ($records as $n) {
            $key = strtoupper($n->idtype ?: 'Unknown');
            $typeStats[$key] ??= ['idtype' => $key, 'total' => 0, 'success' => 0, 'amount' => 0.0];
            $typeStats[$key]['total']++;
            $typeStats[$key]['amount'] += (float) ($n->price ?? 0);
            if ($isSuccess($n)) {
                $typeStats[$key]['success']++;
            }
        }

        $daily = $this->dailyTrend($records, $isSuccess, fn () => 0);

        $successful = $records->filter($isSuccess);
        $spent = (float) $records->sum('price');

        return Inertia::render('Reports/VerifyStats', [
            'filters' => ['preset' => $preset, 'from' => $from->toDateString(), 'to' => $to->toDateString()],
            'range' => ['from' => $from->format('M d, Y'), 'to' => $to->format('M d, Y')],
            'overall' => [
                'total' => $records->count(),
                'success' => $successful->count(),
                'failed' => $records->count() - $successful->count(),
                'success_rate' => $records->count() > 0 ? round($successful->count() / $records->count() * 100, 1) : 0,
                'spent' => $spent,
                'avg_value' => $records->count() > 0 ? round($spent / $records->count()) : 0,
            ],
            'typeStats' => array_values($typeStats),
            'daily' => $daily,
        ]);
    }

    /* ======================================================================
     | Helpers
     | ====================================================================== */

    /**
     * Resolve a date range from the ?preset (today|yesterday|last7days|last30days|custom)
     * and optional ?from / ?to query params.
     *
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function resolveRange(Request $request): array
    {
        $preset = $request->input('preset', 'last30days');
        $now = now();

        return match ($preset) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay(), $preset],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay(), $preset],
            'last7days' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay(), $preset],
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

    /**
     * Parse a data amount (in MB) out of a plan name like "1GB" / "500MB" / "2.5GB".
     */
    private function parseDataMB(?string $name): float
    {
        if (! $name || ! preg_match('/(\d+(?:\.\d+)?)\s*(GB|MB)/i', $name, $m)) {
            return 0.0;
        }

        $amount = (float) $m[1];

        return strtoupper($m[2]) === 'GB' ? $amount * 1000 : $amount;
    }

    /**
     * Build a per-day [{date, total, success, revenue}] series from a collection.
     */
    private function dailyTrend($records, callable $isSuccess, callable $extraValue): array
    {
        $daily = [];
        foreach ($records as $r) {
            // NinDetail uses the Prisma `createdAt` column; DataTransaction uses
            // the standard `created_at`.
            $date = ($r->created_at ?? $r->createdAt)?->toDateString();
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

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NinDetail;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * BVN Search logs — admin (read-only).
 *
 * BVN searches are logged to the shared NINDetails table with idtype = "bvn".
 * This gives admins visibility into search activity and revenue.
 */
class BvnSearchController extends Controller
{
    public function index(Request $request)
    {
        $base = NinDetail::where('idtype', 'bvn');

        $query = (clone $base)->with('user');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('idvalue', 'like', "%{$search}%")
                    ->orWhere('surname', 'like', "%{$search}%")
                    ->orWhere('othernames', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if (($status = $request->input('status')) && $status !== 'all') {
            $query->where('status', $status);
        }

        $logs = $query
            ->orderByDesc('createdAt')
            ->paginate(20)
            ->through(fn (NinDetail $d) => [
                'id' => $d->id,
                'bvn' => $d->idvalue,
                'name' => trim(($d->surname ?? '').' '.($d->othernames ?? '')) ?: null,
                'slip_type' => $d->sliptype,
                'status' => $d->status,
                'price' => $d->price,
                'created_at' => $d->createdAt?->format('Y-m-d H:i'),
                'user' => $d->user ? [
                    'id' => $d->user->id,
                    'name' => $d->user->name,
                    'username' => $d->user->username,
                    'email' => $d->user->email,
                ] : null,
            ])
            ->withQueryString();

        return Inertia::render('Admin/BvnSearches/Index', [
            'logs' => $logs,
            'filters' => $request->only(['search', 'status']),
            'stats' => [
                'total' => (clone $base)->count(),
                'success' => (clone $base)->where('status', 'success')->count(),
                'failed' => (clone $base)->where('status', 'fail')->count(),
                'revenue' => (float) (clone $base)->where('status', 'success')->sum('price'),
            ],
        ]);
    }
}

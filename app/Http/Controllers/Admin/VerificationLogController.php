<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Validation;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The old VerificationLog table is gone; NIN verification activity is now
 * tracked in the `validation` table, which this screen reports on.
 */
class VerificationLogController extends Controller
{
    public function index(Request $request)
    {
        $query = Validation::query()
            ->with('user')
            ->latest('createdAt');

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Search by NIN / comment
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nin', 'like', "%{$search}%")
                    ->orWhere('comment', 'like', "%{$search}%");
            });
        }

        $logs = $query->paginate(20)->through(fn (Validation $log) => [
            'id' => $log->id,
            'user' => $log->user?->name ?? 'Unknown',
            'type' => 'NIN',
            'identity_number' => $log->nin,
            'status' => $log->status,
            'transaction_reference' => null,
            'created_at' => $log->createdAt?->format('Y-m-d H:i'),
        ]);

        return Inertia::render('Admin/VerificationLogs/Index', [
            'logs' => $logs,
            'filters' => $request->only(['type', 'status', 'search']),
        ]);
    }

    public function show(Validation $log)
    {
        $log->load('user');

        return Inertia::render('Admin/VerificationLogs/Show', [
            'log' => [
                'id' => $log->id,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
                'type' => 'NIN',
                'identity_number' => $log->nin,
                'status' => $log->status,
                'verification_data' => $log->getParsedResult(),
                'transaction' => null,
                'created_at' => $log->createdAt?->format('Y-m-d H:i:s'),
            ],
        ]);
    }
}

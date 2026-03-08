<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VerificationLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VerificationLogController extends Controller
{
    public function index(Request $request)
    {
        $query = VerificationLog::query()
            ->with(['user', 'transaction'])
            ->latest();

        // Filter by type
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Search by identity number
        if ($search = $request->input('search')) {
            $query->where('identity_number', 'like', "%{$search}%");
        }

        $logs = $query->paginate(20)->through(fn ($log) => [
            'id' => $log->id,
            'user' => $log->user?->name ?? 'Unknown',
            'type' => $log->type,
            'identity_number' => $log->identity_number,
            'status' => $log->status,
            'transaction_reference' => $log->transaction?->reference,
            'created_at' => $log->created_at->format('Y-m-d H:i'),
        ]);

        return Inertia::render('Admin/VerificationLogs/Index', [
            'logs' => $logs,
            'filters' => $request->only(['type', 'status', 'search']),
        ]);
    }

    public function show(VerificationLog $log)
    {
        $log->load(['user', 'transaction']);

        return Inertia::render('Admin/VerificationLogs/Show', [
            'log' => [
                'id' => $log->id,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
                'type' => $log->type,
                'identity_number' => $log->identity_number,
                'status' => $log->status,
                'verification_data' => $log->verification_data,
                'transaction' => $log->transaction ? [
                    'id' => $log->transaction->id,
                    'reference' => $log->transaction->reference,
                    'amount' => $log->transaction->amount,
                ] : null,
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
            ],
        ]);
    }
}

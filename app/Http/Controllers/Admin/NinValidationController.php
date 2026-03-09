<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NinValidation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NinValidationController extends Controller
{
    /**
     * Display a listing of NIN validations
     */
    public function index(Request $request)
    {
        $query = NinValidation::query()
            ->with('user')
            ->latest();

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by provider
        if ($provider = $request->input('provider')) {
            $query->where('provider', $provider);
        }

        // Search by NIN or user
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nin', 'like', "%{$search}%")
                  ->orWhere('id_value', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $validations = $query->paginate(20)->through(fn ($v) => [
            'id' => $v->id,
            'user' => $v->user ? [
                'id' => $v->user->id,
                'name' => $v->user->name,
                'email' => $v->user->email,
            ] : null,
            'nin' => $v->nin,
            'id_type' => $v->id_type,
            'id_value' => $v->id_value,
            'status' => $v->status,
            'provider' => $v->provider,
            'verification_fee' => (float) $v->verification_fee,
            'is_verified' => $v->is_verified,
            'validated_at' => $v->validated_at?->format('Y-m-d H:i'),
            'created_at' => $v->created_at->format('Y-m-d H:i'),
        ]);

        return Inertia::render('Admin/NinValidations/Index', [
            'validations' => $validations,
            'filters' => $request->only(['status', 'provider', 'search']),
            'statuses' => ['pending', 'completed', 'failed'],
            'providers' => ['v1', 'v2', 'demo', 'phone'],
        ]);
    }

    /**
     * Display the specified NIN validation
     */
    public function show(NinValidation $validation)
    {
        $validation->load('user');

        return Inertia::render('Admin/NinValidations/Show', [
            'validation' => [
                'id' => $validation->id,
                'user' => $validation->user ? [
                    'id' => $validation->user->id,
                    'name' => $validation->user->name,
                    'email' => $validation->user->email,
                ] : null,
                'nin' => $validation->nin,
                'id_type' => $validation->id_type,
                'id_value' => $validation->id_value,
                'status' => $validation->status,
                'provider' => $validation->provider,
                'verification_fee' => (float) $validation->verification_fee,
                'is_verified' => $validation->is_verified,
                'reference' => $validation->reference,
                'comment' => $validation->comment,
                'old_balance' => (float) $validation->old_balance,
                'new_balance' => (float) $validation->new_balance,
                'result' => $validation->getParsedResult(),
                'validated_at' => $validation->validated_at?->format('Y-m-d H:i:s'),
                'created_at' => $validation->created_at->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * Get statistics for dashboard
     */
    public function stats()
    {
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();

        return response()->json([
            'total_validations' => NinValidation::count(),
            'today_validations' => NinValidation::whereDate('created_at', $today)->count(),
            'month_validations' => NinValidation::where('created_at', '>=', $thisMonth)->count(),
            'total_revenue' => NinValidation::sum('verification_fee'),
            'today_revenue' => NinValidation::whereDate('created_at', $today)->sum('verification_fee'),
            'status_breakdown' => [
                'completed' => NinValidation::where('status', 'completed')->count(),
                'pending' => NinValidation::where('status', 'pending')->count(),
                'failed' => NinValidation::where('status', 'failed')->count(),
            ],
            'provider_breakdown' => [
                'v1' => NinValidation::where('provider', 'v1')->count(),
                'v2' => NinValidation::where('provider', 'v2')->count(),
                'demo' => NinValidation::where('provider', 'demo')->count(),
                'phone' => NinValidation::where('provider', 'phone')->count(),
            ],
        ]);
    }
}

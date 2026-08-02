<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesAsyncNinJobs;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Validation;
use App\Services\Nin\AsyncJobService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Admin view of NIN validations — jobs that take 3 days to a week to come back.
 *
 * Beyond browsing, an admin can force the status check, set the status and
 * result by hand when a provider settles a job out of band, and refund. See
 * ManagesAsyncNinJobs.
 */
class NinValidationController extends Controller
{
    use ManagesAsyncNinJobs;

    protected function jobService(): string
    {
        return 'nin.validation';
    }

    /**
     * @return array<string, mixed>
     */
    protected function jobInput(Model $record): array
    {
        return ['nin' => $record->nin];
    }

    /** Overwrite the validation's status and result by hand. */
    public function updateStatus(Request $request, Validation $validation)
    {
        return $this->settleUpdate($request, $validation);
    }

    /** Poll the provider on the user's behalf. */
    public function recheck(Validation $validation, AsyncJobService $jobs)
    {
        return $this->settleRecheck($validation, $jobs);
    }

    /** Return money for a validation that failed or stalled. */
    public function refund(Request $request, Validation $validation)
    {
        return $this->settleRefund($request, $validation);
    }

    /**
     * Display a listing of NIN validations
     */
    public function index(Request $request)
    {
        $query = Validation::query()
            ->with(['user', 'provider'])
            ->latest('createdAt');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // The provider is encoded in the comment (e.g. "NIN verify v1").
        if ($provider = $request->input('provider')) {
            $query->where('comment', 'like', "%{$provider}%");
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nin', 'like', "%{$search}%")
                    ->orWhere('comment', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $validations = $query->paginate(20)->through(fn (Validation $v) => [
            'id' => $v->id,
            'user' => $v->user ? [
                'id' => $v->user->id,
                'name' => $v->user->name,
                'email' => $v->user->email,
            ] : null,
            'nin' => $v->nin,
            'id_type' => null,
            'id_value' => $v->nin,
            'status' => $v->status,
            'provider' => $v->comment,
            'verification_fee' => $v->chargedAmount(),
            'is_verified' => $v->status === 'completed',
            'validated_at' => $v->updatedAt?->format('Y-m-d H:i'),
            'created_at' => $v->createdAt?->format('Y-m-d H:i'),
        ] + $this->jobAdminPayload($v));

        return Inertia::render('Admin/NinValidations/Index', [
            'validations' => $validations,
            'filters' => $request->only(['status', 'provider', 'search']),
            'statuses' => ['processing', 'completed', 'failed'],
            'providers' => ['v1', 'v2', 'demo', 'phone'],
        ]);
    }

    /**
     * Display the specified NIN validation
     */
    public function show(Validation $validation)
    {
        $validation->load(['user', 'provider']);

        return Inertia::render('Admin/NinValidations/Show', [
            'statuses' => Validation::EDITABLE_STATUSES,
            'validation' => $this->jobAdminPayload($validation) + [
                'id' => $validation->id,
                'user' => $validation->user ? [
                    'id' => $validation->user->id,
                    'name' => $validation->user->name,
                    'email' => $validation->user->email,
                ] : null,
                'nin' => $validation->nin,
                'id_type' => null,
                'id_value' => $validation->nin,
                'status' => $validation->status,
                'provider' => $validation->comment,
                'verification_fee' => $validation->chargedAmount(),
                'is_verified' => $validation->status === 'completed',
                'reference' => $validation->id,
                'comment' => $validation->comment,
                'old_balance' => (float) $validation->oldBal,
                'new_balance' => (float) $validation->newBal,
                'result' => $validation->getParsedResult(),
                // A job that is still open stores a plain marker ("Pending"),
                // not JSON, so the parsed form is null and the admin needs the
                // raw value to see anything at all.
                'raw_result' => $validation->result,
                'validated_at' => $validation->updatedAt?->format('Y-m-d H:i:s'),
                'created_at' => $validation->createdAt?->format('Y-m-d H:i:s'),
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

        $ninRevenue = fn () => Transaction::whereIn('type', [
            Transaction::TYPE_NIN_VERIFICATION,
            Transaction::TYPE_NIN_VALIDATION,
        ])->where('status', 'success');

        return response()->json([
            'total_validations' => Validation::count(),
            'today_validations' => Validation::whereDate('createdAt', $today)->count(),
            'month_validations' => Validation::where('createdAt', '>=', $thisMonth)->count(),
            'total_revenue' => (float) $ninRevenue()->sum('price'),
            'today_revenue' => (float) $ninRevenue()->whereDate('createdAt', $today)->sum('price'),
            'status_breakdown' => [
                'completed' => Validation::where('status', 'completed')->count(),
                'processing' => Validation::where('status', 'processing')->count(),
                'failed' => Validation::where('status', 'failed')->count(),
            ],
            'provider_breakdown' => [
                'v1' => Validation::where('comment', 'like', '%v1%')->count(),
                'v2' => Validation::where('comment', 'like', '%v2%')->count(),
                'demo' => Validation::where('comment', 'like', '%demo%')->count(),
                'phone' => Validation::where('comment', 'like', '%phone%')->count(),
            ],
        ]);
    }
}

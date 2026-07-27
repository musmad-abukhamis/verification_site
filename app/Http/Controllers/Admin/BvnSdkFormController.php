<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BvnSdkForm;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * BVN SDK Onboarding — admin side.
 *
 * Port of nimcweb app/(Adminn)/admin/bvnsdk_form: review onboarding
 * registrations, update status (pending / picked / onboarded / rejected) with an
 * optional comment, and delete records.
 */
class BvnSdkFormController extends Controller
{
    private const STATUSES = ['pending', 'picked', 'onboarded', 'rejected'];

    private function present(BvnSdkForm $f): array
    {
        return [
            'id' => $f->id,
            'agentLocation' => $f->agentLocation,
            'agentBvn' => $f->agentBvn,
            'bankName' => $f->bankName,
            'accountNumber' => $f->accountNumber,
            'accountName' => $f->accountName,
            'firstName' => $f->firstName,
            'lastName' => $f->lastName,
            'email' => $f->email,
            'phoneNumber' => $f->phoneNumber,
            'address' => $f->address,
            'stateOfResidence' => $f->stateOfResidence,
            'lga' => $f->lga,
            'zone' => $f->zone,
            'dateOfBirth' => $f->dateOfBirth,
            'status' => $f->status,
            'comment' => $f->comment,
            'old_balance' => $f->oldBal,
            'new_balance' => $f->newBal,
            'created_at' => $f->createdAt?->format('Y-m-d H:i'),
            'user' => $f->user ? [
                'id' => $f->user->id,
                'name' => $f->user->name,
                'username' => $f->user->username,
                'email' => $f->user->email,
            ] : null,
        ];
    }

    public function index(Request $request)
    {
        $query = BvnSdkForm::query()->with('user');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('firstName', 'like', "%{$search}%")
                    ->orWhere('lastName', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phoneNumber', 'like', "%{$search}%")
                    ->orWhere('stateOfResidence', 'like', "%{$search}%")
                    ->orWhere('zone', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if (($status = $request->input('status')) && $status !== 'all') {
            $query->where('status', $status);
        }

        $forms = $query
            ->orderBy('createdAt', 'desc')
            ->paginate(15)
            ->through(fn (BvnSdkForm $f) => $this->present($f))
            ->withQueryString();

        return Inertia::render('Admin/BvnSdkForms/Index', [
            'forms' => $forms,
            'filters' => $request->only(['search', 'status']),
            'statuses' => self::STATUSES,
            'stats' => [
                'total' => BvnSdkForm::count(),
                'today' => BvnSdkForm::whereDate('createdAt', now()->toDateString())->count(),
                'onboarded' => BvnSdkForm::where('status', 'onboarded')->count(),
                'pending' => BvnSdkForm::whereIn('status', ['pending', 'Submitted'])->count(),
            ],
        ]);
    }

    public function show(BvnSdkForm $form)
    {
        $form->load('user');

        return Inertia::render('Admin/BvnSdkForms/Show', [
            'form' => $this->present($form),
            'statuses' => self::STATUSES,
        ]);
    }

    public function updateStatus(Request $request, BvnSdkForm $form)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:'.implode(',', self::STATUSES),
            'comment' => 'nullable|string',
        ]);

        if ($validated['status'] === 'rejected' && empty(trim($validated['comment'] ?? ''))) {
            return back()->withErrors(['message' => 'A comment is required when rejecting a request.']);
        }

        $form->update([
            'status' => $validated['status'],
            'comment' => $validated['comment'] ?? $form->comment,
        ]);

        return back()->with('success', 'Registration status updated successfully.');
    }

    public function destroy(BvnSdkForm $form)
    {
        $form->delete();

        return redirect()->route('admin.bvn-sdk-forms.index')
            ->with('success', 'Registration deleted successfully.');
    }
}

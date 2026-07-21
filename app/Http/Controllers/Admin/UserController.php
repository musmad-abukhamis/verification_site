<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\PinVerificationLog;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()->latest('createdAt');

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter by role -- the practical way to find every AGENT or API user
        // when their pricing changes.
        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        // Filter by status (verified / unverified email)
        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->whereNotNull('email_verified');
            } elseif ($status === 'inactive') {
                $query->whereNull('email_verified');
            }
        }

        $users = $query->paginate(20)->through(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role?->value,
            'is_admin' => $user->isAdmin(),
            'email_verified' => ! is_null($user->email_verified),
            'wallet_balance' => (float) $user->balance,
            'created_at' => $user->createdAt?->format('Y-m-d H:i'),
        ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'status', 'role']),
            'roles' => array_column(UserRole::cases(), 'value'),
            'currentUserId' => Auth::id(),
        ]);
    }

    public function show(User $user)
    {
        $user->load(['transactions' => fn ($q) => $q->latest('createdAt')->limit(10)]);

        return Inertia::render('Admin/Users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role?->value,
                'is_admin' => $user->isAdmin(),
                'email_verified_at' => $user->email_verified?->format('Y-m-d H:i'),
                'created_at' => $user->createdAt?->format('Y-m-d H:i'),
                'wallet' => [
                    'balance' => (float) $user->balance,
                    'bonus_balance' => 0.0,
                    'total_balance' => (float) $user->balance,
                ],
                'transactions' => $user->transactions->map(fn (Transaction $t) => [
                    'id' => $t->id,
                    'reference' => $t->reference,
                    'type' => $t->type,
                    'amount' => (float) $t->price,
                    'status' => $t->status,
                    'created_at' => $t->createdAt?->format('Y-m-d H:i'),
                ]),
            ],
        ]);
    }

    public function toggleAdmin(User $user)
    {
        $user->update([
            'role' => $user->isAdmin() ? \App\Enums\UserRole::USER : \App\Enums\UserRole::ADMIN,
        ]);

        return back()->with('success', 'User admin status updated successfully.');
    }

    /**
     * Set a user's role.
     *
     * Role drives pricing (Plan::priceForRole) and API access, so this is a
     * commercial control, not just a permission one -- moving someone to AGENT
     * or API changes what they pay.
     */
    public function updateRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        $role = UserRole::from($validated['role']);

        // Without this an admin can drop their own ADMIN role and lock
        // themselves out of the page they are standing on.
        if ($user->id === Auth::id() && $role !== UserRole::ADMIN) {
            return back()->withErrors(['role' => 'You cannot change your own role.']);
        }

        $user->update(['role' => $role]);

        return back()->with('success', "{$user->name}'s role updated to {$role->value}.");
    }

    public function toggleStatus(User $user)
    {
        // Toggle email verification status as a simple way to disable/enable account
        $user->update([
            'email_verified' => $user->email_verified ? null : now(),
        ]);

        return back()->with('success', 'User status updated successfully.');
    }

    public function creditWallet(Request $request, User $user)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'description' => 'nullable|string|max:255',
        ]);

        $oldBalance = (float) $user->balance;
        $user->credit((float) $validated['amount'], false, ['fundingtype' => 'admin-credit']);

        Transaction::create([
            'id' => 'ADM_CREDIT_'.now()->timestamp.random_int(1000, 9999),
            'network' => 'WALLET',
            'name' => $validated['description'] ?? 'Admin wallet credit',
            'price' => (int) round($validated['amount']),
            'type' => 'admin_credit',
            'phone' => $user->phone ?? '',
            'oldbal' => $oldBalance,
            'newbal' => (float) $user->balance,
            'status' => 'success',
            'userId' => $user->id,
            'response' => 'admin',
        ]);

        return back()->with('success', '₦'.number_format($validated['amount']).' credited to '.$user->name."'s wallet.");
    }

    public function debitWallet(Request $request, User $user)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'description' => 'nullable|string|max:255',
        ]);

        if ((float) $user->balance < $validated['amount']) {
            return back()->withErrors(['amount' => 'Insufficient wallet balance. Current balance: ₦'.number_format((float) $user->balance)]);
        }

        $oldBalance = (float) $user->balance;
        $user->debit((float) $validated['amount'], false, ['fundingtype' => 'admin-debit']);

        Transaction::create([
            'id' => 'ADM_DEBIT_'.now()->timestamp.random_int(1000, 9999),
            'network' => 'WALLET',
            'name' => $validated['description'] ?? 'Admin wallet debit',
            'price' => (int) round($validated['amount']),
            'type' => 'admin_debit',
            'phone' => $user->phone ?? '',
            'oldbal' => $oldBalance,
            'newbal' => (float) $user->balance,
            'status' => 'success',
            'userId' => $user->id,
            'response' => 'admin',
        ]);

        return back()->with('success', '₦'.number_format($validated['amount']).' debited from '.$user->name."'s wallet.");
    }

    /**
     * Set a new password for the user (admin reset). The `hashed` cast on the
     * model hashes it on save. Trimmed so stray whitespace can't lock them out.
     */
    public function changePassword(Request $request, User $user)
    {
        $validated = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update(['password' => trim($validated['password'])]);

        return back()->with('success', "Password updated for {$user->name}.");
    }

    /**
     * Reset the user's 2FA: disable it and remove any confirmation record.
     */
    public function resetTwoFactor(User $user)
    {
        $user->twoFactorConfirmation()->delete();
        $user->update(['isTwoFactorEnabled' => false]);

        return back()->with('success', "Two-factor authentication reset for {$user->name}.");
    }

    /**
     * Reset the user's wallet balance to zero (logs the adjustment).
     */
    public function resetWallet(User $user)
    {
        $oldBalance = (float) $user->balance;

        if ($oldBalance <= 0) {
            return back()->with('success', "{$user->name}'s wallet is already at ₦0.");
        }

        $user->debit($oldBalance, false, ['fundingtype' => 'admin-reset']);

        Transaction::create([
            'id' => 'ADM_RESET_'.now()->timestamp.random_int(1000, 9999),
            'network' => 'WALLET',
            'name' => 'Admin wallet reset',
            'price' => (int) round($oldBalance),
            'type' => 'admin_debit',
            'phone' => $user->phone ?? '',
            'oldbal' => $oldBalance,
            'newbal' => 0.0,
            'status' => 'success',
            'userId' => $user->id,
            'response' => 'admin',
        ]);

        return back()->with('success', "{$user->name}'s wallet has been reset to ₦0.");
    }

    /**
     * Delete a user. Several models reference the user without an ON DELETE
     * CASCADE, so every dependent record is removed first, atomically. Admins
     * cannot delete their own account.
     */
    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->withErrors(['message' => 'You cannot delete your own account.']);
        }

        DB::transaction(function () use ($user) {
            $user->transactions()->delete();
            $user->ninDetails()->delete();
            $user->kyc()->delete();
            $user->walletHistory()->delete();
            $user->notificationUsers()->delete();
            $user->notifications()->delete();
            $user->ipe()->delete();
            $user->validations()->delete();
            $user->personalisations()->delete();
            $user->bvnModifications()->delete();
            $user->bvnSdkForms()->delete();
            $user->bvnRetrievals()->delete();
            $user->idCardRequests()->delete();
            $user->pin()->delete();
            $user->otp()->delete();
            $user->twoFactorConfirmation()->delete();
            $user->accounts()->delete();
            PinVerificationLog::where('userId', $user->id)->delete();

            $user->delete();
        });

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }
}

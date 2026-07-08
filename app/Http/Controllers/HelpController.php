<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Help & Support — user side.
 *
 * Port of the nimcweb `contact` page: displays the site's contact details (from
 * the `settings` row managed by admin Site Settings) and a "send us a message"
 * form. Mirroring the source, the form is not persisted or emailed — it is
 * logged and acknowledged with a success message.
 */
class HelpController extends Controller
{
    public function index()
    {
        $settings = Setting::query()->orderBy('id')->first();

        return Inertia::render('Help/Index', [
            'settings' => [
                'site_name' => $settings?->site_name,
                'site_url' => $settings?->site_url,
                'site_email' => $settings?->site_email,
                'site_email2' => $settings?->site_email2,
                'site_phone' => $settings?->site_phone,
                'site_phone2' => $settings?->site_phone2,
                'whatsapp_url' => $settings?->whatsapp_url,
                'whatsapp_url2' => $settings?->whatsapp_url2,
                'office_address' => $settings?->office_address,
                'office_address2' => $settings?->office_address2,
            ],
        ]);
    }

    public function submit(Request $request)
    {
        $validated = $request->validate([
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|min:10',
            'priority' => 'nullable|in:low,medium,high',
        ]);

        // The source does not persist or email — it acknowledges receipt.
        // We log the submission so it is at least recoverable.
        Log::info('Help & Support message', [
            'name' => $validated['firstName'].' '.$validated['lastName'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'subject' => $validated['subject'],
            'priority' => $validated['priority'] ?? 'medium',
            'user_id' => $request->user()?->id,
        ]);

        return back()->with('success', "Thank you {$validated['firstName']}! Your message has been sent successfully. We'll get back to you soon.");
    }
}

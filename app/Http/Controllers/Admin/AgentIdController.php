<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

/**
 * Agent ID card generator — admin.
 *
 * Port of nimcweb app/(Adminn)/admin/agent-id. Purely client-side: the admin
 * enters name / email / agent ID and an optional photo, previews the composed
 * card on a canvas, and downloads a two-sided (front + back) PDF. No DB access.
 */
class AgentIdController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/AgentId/Index');
    }
}

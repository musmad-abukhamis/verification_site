<?php

namespace App\Http\Controllers\Api\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Record;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Enrolment record search for API resellers.
 *
 * Searches the enrolment records we hold — the same table the web BVN Records
 * screen reads — by ticket id or by the agent (enroller) id that captured them.
 *
 * Free: this is our own data, not a provider lookup, so nothing is charged and
 * an integrator can poll it as a dashboard. It is also the one BVN endpoint
 * that is not scoped to the caller, because enrolment records belong to the
 * enrolment, not to whoever is asking.
 */
class BvnRecordController extends Controller
{
    /**
     * The web screen's floor, kept here for the same reason: a two-character
     * query matches most of the table and returns a page of noise.
     */
    private const MIN_QUERY = 6;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:'.self::MIN_QUERY],
            'type' => ['nullable', 'string', Rule::in(['ticket_id', 'enroller_id'])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $column = $validated['type'] ?? 'ticket_id';

        $records = Record::query()
            ->where($column, 'like', '%'.$validated['query'].'%')
            ->orderByDesc('date_enrolled')
            ->limit($validated['limit'] ?? 50)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'type' => $column,
                'query' => $validated['query'],
                'count' => $records->count(),
                'records' => $records->map(fn (Record $r) => [
                    'ticket_id' => $r->ticket_id,
                    'bvn' => $r->bvn,
                    'enrollee_name' => $r->enrollee_name,
                    'enroller_id' => $r->enroller_id,
                    'status' => $r->status,
                    'comment' => $r->comment,
                    'date_enrolled' => $r->date_enrolled,
                ])->all(),
            ],
        ]);
    }
}

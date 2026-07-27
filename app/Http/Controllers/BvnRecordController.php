<?php

namespace App\Http\Controllers;

use App\Models\Record;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * BVN enrolment records search — user side.
 *
 * Port of nimcweb app/(protectedpages)/bvn_records + its /api/bvn/records
 * handler: search the `Record` table by Ticket ID or Agent ID (enroller_id),
 * requiring at least 6 characters, paginated 10 per page. Nothing is returned
 * until a valid search is submitted.
 */
class BvnRecordController extends Controller
{
    private const SEARCH_TYPES = ['ticket_id', 'enroller_id'];

    public function index(Request $request)
    {
        $searchType = $request->input('searchType', 'ticket_id');
        if (! in_array($searchType, self::SEARCH_TYPES, true)) {
            $searchType = 'ticket_id';
        }

        $query = trim((string) $request->input('query', ''));
        $hasValidSearch = strlen($query) >= 6;

        $records = null;
        if ($hasValidSearch) {
            $records = Record::query()
                ->where($searchType, 'like', "%{$query}%")
                ->orderBy('date_enrolled', 'desc')
                ->paginate(10)
                ->through(fn (Record $r) => [
                    'ticket_id' => $r->ticket_id,
                    'bvn' => $r->bvn,
                    'enrollee_name' => $r->enrollee_name,
                    'enroller_id' => $r->enroller_id,
                    'status' => $r->status,
                    'comment' => $r->comment,
                    'date_enrolled' => $r->date_enrolled,
                ])
                ->withQueryString();
        }

        return Inertia::render('BvnRecords/Index', [
            'records' => $records,
            'filters' => [
                'searchType' => $searchType,
                'query' => $query,
            ],
            'hasSearched' => $hasValidSearch,
        ]);
    }
}

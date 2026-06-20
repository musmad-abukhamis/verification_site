<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BvnServicePrice;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * BVN service prices — admin.
 *
 * Port of nimcweb app/(Adminn)/admin/bvn-prices. Edits the single-row
 * `bvnserviceprices` config (id "API1") that drives BVN Modification pricing
 * (and the other BVN services). Prices are stored as strings (Naira, no symbol).
 */
class BvnPriceController extends Controller
{
    /**
     * All editable columns on bvnserviceprices, grouped for the UI.
     */
    private array $groups = [
        'Modification Services' => [
            'name_mod' => 'Name Modification',
            'dob_mod' => 'DOB Modification',
            'phone_mod' => 'Phone Modification',
            'email_mod' => 'Email Modification',
            'namedob_mod' => 'Name & DOB Modification',
            'namephone_mod' => 'Name & Phone Modification',
            'namephonedob_mod' => 'Name, DOB & Phone Modification',
        ],
        'Retrieval & Search' => [
            'searchslip1' => 'Search Slip 1',
            'searchslip2' => 'Search Slip 2',
            'searchslip3' => 'Search Slip 3',
            'retrieve_with_phone' => 'Retrieve With Phone',
            'retrieve_with_Id' => 'Retrieve With ID',
        ],
        'Onboarding & Others' => [
            'onboarding1' => 'Onboarding 1',
            'onboarding2' => 'Onboarding 2',
            'idcardfee' => 'ID Card Fee',
        ],
    ];

    private function prices(): BvnServicePrice
    {
        return BvnServicePrice::firstOrCreate(['id' => 'API1']);
    }

    /**
     * All column keys across every group.
     */
    private function columns(): array
    {
        return array_merge(...array_map('array_keys', array_values($this->groups)));
    }

    public function index()
    {
        $prices = $this->prices();

        $groups = collect($this->groups)->map(fn ($fields) => collect($fields)->map(fn ($label, $column) => [
            'column' => $column,
            'label' => $label,
            'value' => $prices->{$column},
        ])->values())->all();

        return Inertia::render('Admin/BvnPrices/Index', [
            'groups' => $groups,
        ]);
    }

    public function update(Request $request)
    {
        $columns = $this->columns();

        $rules = [];
        foreach ($columns as $column) {
            $rules[$column] = 'nullable|numeric|min:0';
        }

        $validated = $request->validate($rules);

        $data = [];
        foreach ($columns as $column) {
            $value = $validated[$column] ?? null;
            // Store as string (matches the Prisma string columns); blank → null.
            $data[$column] = ($value === null || $value === '') ? null : (string) $value;
        }

        $this->prices()->update($data);

        return back()->with('success', 'BVN service prices updated successfully.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveNetworkRequest;
use App\Models\Network;
use Inertia\Inertia;

class NetworkController extends Controller
{
    /**
     * Display the network configuration page with tabs
     */
    public function index()
    {
        return Inertia::render('Admin/NetworkId/Index');
    }

    /**
     * Display edit page for a specific network
     */
    public function edit($network)
    {
        // Validate network name
        $validNetworks = ['MTN', 'AIRTEL', 'GLO', '9MOBILE'];
        if (! in_array(strtoupper($network), $validNetworks)) {
            abort(404, 'Invalid network');
        }

        $networkName = strtoupper($network);

        // Get or create network data (network name is the primary key)
        $networkData = Network::firstOrCreate(
            ['id' => $networkName],
            [
                'vendor1network' => '1',
                'vendor2network' => '1',
                'vendor3network' => '1',
                'vendor4network' => '1',
                'vendor5network' => '1',
            ]
        );

        return Inertia::render('Admin/NetworkId/Edit', [
            'networkName' => $networkName,
            'networkData' => $networkData,
        ]);
    }

    /**
     * Store or update network configuration
     */
    public function store(SaveNetworkRequest $request)
    {
        $validated = $request->validated();

        Network::updateOrCreate(
            ['id' => $validated['id']],
            [
                'vendor1network' => (string) $validated['vendor1Network'],
                'vendor2network' => (string) $validated['vendor2Network'],
                'vendor3network' => (string) $validated['vendor3Network'],
                'vendor4network' => (string) $validated['vendor4Network'],
                'vendor5network' => (string) $validated['vendor5Network'],
            ]
        );

        return redirect()->back()->with('success', 'Network IDs saved successfully');
    }
}

<?php

namespace App\Http\Controllers\NIN;

use App\Http\Controllers\Controller;
use App\Services\SlipDownloadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SlipDownloadController extends Controller
{
    protected SlipDownloadService $slipService;

    public function __construct(SlipDownloadService $slipService)
    {
        $this->slipService = $slipService;
    }

    /**
     * Get all available slip types with prices
     */
    public function types()
    {
        return response()->json([
            'success' => true,
            'data' => $this->slipService->getActiveSlipTypes(),
        ]);
    }

    /**
     * Process slip download request
     */
    public function download(Request $request)
    {
        $validated = $request->validate([
            'validation_id' => 'required|integer|exists:nin_validations,id',
            'slip_type'     => 'required|string',
        ]);

        $user = Auth::user();
        $result = $this->slipService->download(
            $validated['validation_id'],
            $validated['slip_type'],
            $user
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'required' => $result['required'] ?? null,
                'available' => $result['available'] ?? null,
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $result['data'],
        ]);
    }
}

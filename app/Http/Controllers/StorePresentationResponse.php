<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class StorePresentationResponse extends Controller
{
    public function __invoke(Request $request, string $id): JsonResponse
    {
        if (! Cache::has("oid4vp:request:{$id}")) {
            abort(404, 'Presentation request not found or expired.');
        }

        $responseData = $request->all();

        Cache::put("oid4vp:status:{$id}", [
            'status' => 'complete',
            'data' => $responseData,
        ], now()->addMinutes(10));

        return response()->json(['status' => 'ok']);
    }
}

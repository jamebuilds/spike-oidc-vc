<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class GetPresentationStatus extends Controller
{
    public function __invoke(string $id): JsonResponse
    {
        $status = Cache::get("oid4vp:status:{$id}", ['status' => 'pending']);

        return response()->json($status);
    }
}

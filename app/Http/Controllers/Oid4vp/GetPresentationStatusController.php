<?php

namespace App\Http\Controllers\Oid4vp;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class GetPresentationStatusController extends Controller
{
    public function __invoke(string $id): JsonResponse
    {
        $status = Cache::get("oid4vp:status:{$id}", ['status' => 'pending']);

        return response()->json($status);
    }
}

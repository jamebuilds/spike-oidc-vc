<?php

namespace App\Http\Controllers\Oid4vci;

use App\Http\Controllers\Controller;
use App\Services\Oid4vci\IssuanceSession;
use Illuminate\Http\JsonResponse;

class GetIssuanceStatusController extends Controller
{
    public function __invoke(string $id, IssuanceSession $session): JsonResponse
    {
        return response()->json($session->getStatus($id));
    }
}

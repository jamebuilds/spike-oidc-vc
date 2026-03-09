<?php

namespace App\Http\Controllers\Oid4vp;

use App\Http\Controllers\Controller;
use App\Services\Oid4vp\PresentationSession;
use Illuminate\Http\JsonResponse;

class GetPresentationStatusController extends Controller
{
    public function __invoke(string $id, PresentationSession $session): JsonResponse
    {
        return response()->json($session->getStatus($id));
    }
}

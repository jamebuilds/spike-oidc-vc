<?php

namespace App\Http\Controllers\Oid4vp;

use App\Http\Controllers\Controller;
use App\Services\Oid4vp\PresentationSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PresentationRequestController extends Controller
{
    public function __construct(
        private PresentationSession $session,
    ) {}

    public function create(): Response
    {
        return Inertia::render('oid4vp/create');
    }

    public function store(): JsonResponse
    {
        $id = Str::uuid()->toString();
        $nonce = Str::random(32);

        $presentationDefinition = $this->session->buildPresentationDefinition($id);
        $this->session->create($id, $nonce, $presentationDefinition);
        $uri = $this->session->buildAuthorizationUri($id, $nonce);

        return response()->json([
            'id' => $id,
            'uri' => $uri,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $requestData = $this->session->findOrFail($id);

        return response()->json($requestData);
    }
}

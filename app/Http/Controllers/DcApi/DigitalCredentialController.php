<?php

namespace App\Http\Controllers\DcApi;

use App\Http\Controllers\Controller;
use App\Services\Oid4vp\PresentationSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DigitalCredentialController extends Controller
{
    public function __construct(
        private PresentationSession $session,
    ) {}

    public function create(): Response
    {
        return Inertia::render('dc-api/create');
    }

    /**
     * Create a presentation session and return the Digital Credentials API request parameters.
     */
    public function store(): JsonResponse
    {
        $id = Str::uuid()->toString();
        $nonce = Str::random(32);

        $dcqlQuery = [
            'credentials' => [[
                'id' => 'cred_0',
                'format' => 'dc+sd-jwt',
                'meta' => ['vct_values' => ['AccredifyEmployeePass']],
                'claims' => [
                    ['path' => ['employeeId']],
                    ['path' => ['firstName']],
                    ['path' => ['lastName']],
                    ['path' => ['dateOfBirth']],
                    ['path' => ['nric']],
                ],
            ]],
        ];

        $this->session->create($id, $nonce, $dcqlQuery);

        return response()->json([
            'id' => $id,
            'nonce' => $nonce,
            'dcql_query' => $dcqlQuery,
        ]);
    }
}

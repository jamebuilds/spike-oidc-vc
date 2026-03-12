<?php

namespace App\Http\Controllers\Oid4vci;

use App\Http\Controllers\Controller;
use App\Services\Oid4vci\IssuanceSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CredentialOfferController extends Controller
{
    public function __construct(
        private IssuanceSession $session,
    ) {}

    public function create(): Response
    {
        return Inertia::render('oid4vci/create');
    }

    public function store(): JsonResponse
    {
        $id = Str::uuid()->toString();

        $subjectClaims = [
            'accountId' => 'ACC-'.strtoupper(Str::random(8)),
            'IBAN' => 'DE89370400440532013000',
            'BIC' => 'COBADEFFXXX',
            'givenName' => 'Max',
            'familyName' => 'Mustermann',
            'birthDate' => '1990-01-15',
        ];

        $this->session->create($id, $subjectClaims);
        $uri = $this->session->buildCredentialOfferUri($id);

        return response()->json([
            'id' => $id,
            'uri' => $uri,
        ]);
    }
}

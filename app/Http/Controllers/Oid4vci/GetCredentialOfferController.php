<?php

namespace App\Http\Controllers\Oid4vci;

use App\Http\Controllers\Controller;
use App\Services\Oid4vci\IssuanceSession;
use Illuminate\Http\JsonResponse;

class GetCredentialOfferController extends Controller
{
    public function __invoke(string $id, IssuanceSession $session): JsonResponse
    {
        $offer = $session->findOrFail($id);

        return response()->json([
            'credential_issuer' => config('oid4vci.issuer_url'),
            'credential_configuration_ids' => [config('oid4vci.credential_type', 'AccredifyEmployeePass')],
            'grants' => [
                'urn:ietf:params:oauth:grant-type:pre-authorized_code' => [
                    'pre-authorized_code' => $offer['pre_authorized_code'],
                ],
            ],
        ]);
    }
}

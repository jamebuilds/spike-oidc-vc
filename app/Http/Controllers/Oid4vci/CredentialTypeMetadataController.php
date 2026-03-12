<?php

namespace App\Http\Controllers\Oid4vci;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CredentialTypeMetadataController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'vct' => config('oid4vci.issuer_url').'/'.config('oid4vci.credential_type', 'AccredifyEmployeePass'),
            'name' => 'Accredify Employee Pass',
            'description' => 'A demo employee pass credential issued as SD-JWT.',
            'claims' => [
                ['path' => ['employeeId'], 'display' => [['lang' => 'en', 'label' => 'Employee ID']]],
                ['path' => ['firstName'], 'display' => [['lang' => 'en', 'label' => 'First Name']]],
                ['path' => ['lastName'], 'display' => [['lang' => 'en', 'label' => 'Last Name']]],
                ['path' => ['dateOfBirth'], 'display' => [['lang' => 'en', 'label' => 'Date of Birth']]],
                ['path' => ['nric'], 'display' => [['lang' => 'en', 'label' => 'NRIC']]],
            ],
            'display' => [
                [
                    'lang' => 'en',
                    'name' => 'Accredify Employee Pass',
                    'description' => 'An employee identification credential.',
                ],
            ],
        ]);
    }
}

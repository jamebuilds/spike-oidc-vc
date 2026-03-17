<?php

namespace App\Http\Controllers\DcApi;

use App\Http\Controllers\Controller;
use App\Services\Oid4vp\PresentationSession;
use App\Services\Oid4vp\SdJwt\SdJwtVerifier;
use App\Services\Oid4vp\VpToken\VpTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerifyDigitalCredentialController extends Controller
{
    /** @var array<string, class-string> */
    private const FORMAT_VERIFIERS = [
        'dc+sd-jwt' => SdJwtVerifier::class,
        'vc+sd-jwt' => SdJwtVerifier::class,
        'jwt_vp' => VpTokenVerifier::class,
        'jwt_vp_json' => VpTokenVerifier::class,
    ];

    /**
     * Verify the Digital Credentials API response from the browser.
     */
    public function __invoke(
        Request $request,
        string $id,
        PresentationSession $session,
    ): JsonResponse {
        $requestData = $session->findOrFail($id);

        $responseData = $request->input('data');

        if (is_string($responseData)) {
            $responseData = json_decode($responseData, true);
        }

        if (! is_array($responseData)) {
            abort(422, 'Invalid response data');
        }

        $vpToken = $responseData['vp_token'] ?? null;
        $verificationResult = null;

        if ($vpToken) {
            $expectedNonce = $requestData['nonce'] ?? '';
            $format = $this->resolveFormat($responseData);
            $verifier = app(self::FORMAT_VERIFIERS[$format]);

            $result = $verifier->verify($vpToken, $expectedNonce);
            $verificationResult = $result->toArray();
        }

        $session->complete($id, $responseData, $verificationResult);

        return response()->json([
            'status' => 'ok',
            'verification' => $verificationResult,
            'data' => $responseData,
        ]);
    }

    private function resolveFormat(array $responseData): string
    {
        $submission = $responseData['presentation_submission'] ?? null;

        if (is_string($submission)) {
            $submission = json_decode($submission, true);
        }

        if (is_array($submission) && ! empty($submission['descriptor_map'])) {
            $format = $submission['descriptor_map'][0]['format'] ?? null;

            if ($format && isset(self::FORMAT_VERIFIERS[$format])) {
                return $format;
            }
        }

        return 'dc+sd-jwt';
    }
}

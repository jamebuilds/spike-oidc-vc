<?php

namespace App\Http\Controllers\Oid4vp;

use App\Http\Controllers\Controller;
use App\Services\Oid4vp\PresentationSession;
use App\Services\Oid4vp\SdJwt\SdJwtVerifier;
use App\Services\Oid4vp\VpToken\VpTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorePresentationResponseController extends Controller
{
    /** @var array<string, class-string> */
    private const FORMAT_VERIFIERS = [
        'vc+sd-jwt' => SdJwtVerifier::class,
        'jwt_vp_json' => VpTokenVerifier::class,
    ];

    public function __invoke(
        Request $request,
        string $id,
        PresentationSession $session,
    ): JsonResponse {
        $requestData = $session->findOrFail($id);

        $responseData = $request->all();

        $verificationResult = null;
        $vpToken = $request->input('vp_token');

        if ($vpToken) {
            $expectedNonce = $requestData['nonce'] ?? '';
            $presentationDefinition = $requestData['presentation_definition'];

            $format = $this->resolveFormat($request, $presentationDefinition);
            $verifier = app(self::FORMAT_VERIFIERS[$format]);

            $result = $verifier->verify($vpToken, $expectedNonce);
            $verificationResult = $result->toArray();
        }

        $session->complete($id, $responseData, $verificationResult);

        return response()->json(['status' => 'ok']);
    }

    private function resolveFormat(Request $request, array $presentationDefinition): string
    {
        $submission = json_decode($request->input('presentation_submission', ''), true);

        if (! is_array($submission) || empty($submission['descriptor_map'])) {
            abort(422, 'Missing or invalid presentation_submission');
        }

        $format = $submission['descriptor_map'][0]['format'] ?? null;

        if (! $format || ! isset(self::FORMAT_VERIFIERS[$format])) {
            abort(422, "Unsupported format: {$format}");
        }

        $acceptedFormats = $this->getAcceptedFormats($presentationDefinition);

        if (! empty($acceptedFormats) && ! in_array($format, $acceptedFormats, true)) {
            abort(422, "Format '{$format}' is not accepted by the presentation definition");
        }

        return $format;
    }

    /**
     * @return array<int, string>
     */
    private function getAcceptedFormats(array $presentationDefinition): array
    {
        $formats = [];

        foreach ($presentationDefinition['input_descriptors'] ?? [] as $descriptor) {
            foreach (array_keys($descriptor['format'] ?? []) as $format) {
                $formats[] = $format;
            }
        }

        return array_unique($formats);
    }
}

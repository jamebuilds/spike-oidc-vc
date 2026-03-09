<?php

namespace App\Services\Wallet;

use App\Models\Credential;
use App\Models\WalletKey;
use Illuminate\Support\Collection;

class PresentationService
{
    public function __construct(
        private SdJwtService $sdJwtService,
        private WalletKeyService $walletKeyService,
    ) {}

    /**
     * Match stored credentials against a presentation definition's input descriptors.
     *
     * @param  Collection<int, Credential>  $credentials
     * @param  array<string, mixed>  $presentationDefinition
     * @return Collection<int, array{credential: Credential, inputDescriptor: array<string, mixed>}>
     */
    public function matchCredentials(Collection $credentials, array $presentationDefinition): Collection
    {
        $inputDescriptors = $presentationDefinition['input_descriptors'] ?? [];
        $matches = collect();

        foreach ($inputDescriptors as $descriptor) {
            foreach ($credentials as $credential) {
                if ($this->credentialMatchesDescriptor($credential, $descriptor)) {
                    $matches->push([
                        'credential' => $credential,
                        'inputDescriptor' => $descriptor,
                    ]);
                }
            }
        }

        return $matches;
    }

    /**
     * Categorize disclosures into required and selectable for a given input descriptor.
     *
     * @param  array<string, mixed>  $inputDescriptor
     * @return array{required: list<array<string, mixed>>, selectable: list<array<string, mixed>>}
     */
    public function categorizeDisclosures(Credential $credential, array $inputDescriptor): array
    {
        $disclosureMapping = $credential->disclosure_mapping ?? [];
        $requestedFields = $this->extractRequestedFields($inputDescriptor);

        $required = [];
        $selectable = [];

        foreach ($disclosureMapping as $index => $disclosure) {
            $disclosureWithIndex = [...$disclosure, 'index' => $index];

            if (isset($requestedFields[$disclosure['claimName']])) {
                $field = $requestedFields[$disclosure['claimName']];

                if (! ($field['optional'] ?? false)) {
                    $required[] = $disclosureWithIndex;
                } else {
                    $selectable[] = $disclosureWithIndex;
                }
            } else {
                $selectable[] = $disclosureWithIndex;
            }
        }

        return [
            'required' => $required,
            'selectable' => $selectable,
        ];
    }

    /**
     * Build the full VP token for submission to a verifier.
     *
     * @param  list<int>  $selectedDisclosureIndices
     */
    public function buildVpToken(
        Credential $credential,
        array $selectedDisclosureIndices,
        WalletKey $walletKey,
        string $audience,
        string $nonce,
    ): string {
        $parsed = $this->sdJwtService->parse($credential->raw_sd_jwt);
        $disclosureMapping = $credential->disclosure_mapping;

        // Collect selected disclosure encoded strings
        $selectedDisclosures = [];
        foreach ($selectedDisclosureIndices as $index) {
            if (isset($disclosureMapping[$index])) {
                $selectedDisclosures[] = $disclosureMapping[$index]['encoded'];
            }
        }

        // Build the presentation without KB-JWT to compute sd_hash
        $presentationWithoutKb = $parsed['issuerJwt'].'~'.implode('~', $selectedDisclosures).'~';
        $sdHash = $this->sdJwtService->computeSdHash($presentationWithoutKb);

        // Create the Key Binding JWT
        $kbJwt = $this->walletKeyService->createKeyBindingJwt($walletKey, $audience, $nonce, $sdHash);

        // Assemble the final presentation SD-JWT
        return $this->sdJwtService->buildPresentationSdJwt(
            $parsed['issuerJwt'],
            $selectedDisclosures,
            $kbJwt,
        );
    }

    /**
     * Build a spec-compliant presentation_submission object.
     *
     * @return array<string, mixed>
     */
    public function buildPresentationSubmission(string $definitionId, string $descriptorId): array
    {
        return [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'definition_id' => $definitionId,
            'descriptor_map' => [
                [
                    'id' => $descriptorId,
                    'format' => 'vc+sd-jwt',
                    'path' => '$',
                ],
            ],
        ];
    }

    /**
     * Check if a credential matches an input descriptor by vct/type.
     *
     * @param  array<string, mixed>  $descriptor
     */
    private function credentialMatchesDescriptor(Credential $credential, array $descriptor): bool
    {
        // Check constraints for vct/type matching
        $constraints = $descriptor['constraints'] ?? [];
        $fields = $constraints['fields'] ?? [];

        foreach ($fields as $field) {
            $paths = $field['path'] ?? [];

            foreach ($paths as $path) {
                // Match on common type paths: $.vct, $.type, $.vc.type
                if (in_array($path, ['$.vct', '$.type', '$.vc.type'])) {
                    $filter = $field['filter'] ?? [];
                    $pattern = $filter['pattern'] ?? ($filter['const'] ?? null);

                    if ($pattern !== null && $this->typeMatchesPattern($credential->type, $pattern)) {
                        return true;
                    }
                }
            }
        }

        // Fallback: match descriptor id against credential type (case-insensitive)
        $descriptorId = $descriptor['id'] ?? '';

        if ($descriptorId !== '' && strcasecmp($descriptorId, $credential->type) === 0) {
            return true;
        }

        return false;
    }

    /**
     * Check if a credential type matches a filter pattern (exact match or regex).
     */
    private function typeMatchesPattern(string $type, string $pattern): bool
    {
        if ($type === $pattern) {
            return true;
        }

        // Try as regex pattern
        return (bool) preg_match('/^'.$pattern.'$/', $type);
    }

    /**
     * Extract requested field names from an input descriptor.
     *
     * @param  array<string, mixed>  $inputDescriptor
     * @return array<string, array{optional: bool}>
     */
    private function extractRequestedFields(array $inputDescriptor): array
    {
        $constraints = $inputDescriptor['constraints'] ?? [];
        $fields = $constraints['fields'] ?? [];
        $requested = [];

        foreach ($fields as $field) {
            $paths = $field['path'] ?? [];
            $optional = $field['optional'] ?? false;

            foreach ($paths as $path) {
                // Convert JSON path like $.given_name to claim name
                if (str_starts_with($path, '$.')) {
                    $claimName = substr($path, 2);
                    $requested[$claimName] = ['optional' => $optional];
                }
            }
        }

        return $requested;
    }
}

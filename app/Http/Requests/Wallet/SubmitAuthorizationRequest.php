<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAuthorizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'credential_id' => ['required', 'integer', 'exists:credentials,id'],
            'selected_disclosures' => ['required', 'array'],
            'selected_disclosures.*' => ['integer', 'min:0'],
            'nonce' => ['required', 'string'],
            'client_id' => ['required', 'string'],
            'response_uri' => ['required', 'url'],
            'state' => ['nullable', 'string'],
            'definition_id' => ['nullable', 'string'],
            'descriptor_id' => ['nullable', 'string'],
        ];
    }
}

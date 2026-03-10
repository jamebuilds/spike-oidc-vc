export type Disclosure = {
    salt: string;
    claimName: string;
    claimValue: unknown;
    digest: string;
    encoded: string;
    index: number;
};

export type Credential = {
    id: number;
    issuer: string;
    type: string;
    payload_claims: Record<string, unknown>;
    disclosure_mapping: Disclosure[];
    cnf_jwk?: Record<string, string> | null;
    issued_at: string | null;
    expires_at: string | null;
    created_at?: string;
};

export type WalletKey = {
    id: number;
    algorithm: string;
    public_jwk: Record<string, string>;
};

export type PresentationLog = {
    id: number;
    credential_id: number | null;
    verifier_client_id: string;
    nonce: string;
    disclosed_claims: string[];
    response_uri: string;
    status: string;
    submitted_at: string | null;
    created_at: string;
    credential?: {
        id: number;
        type: string;
        issuer: string;
    } | null;
};

export type AuthorizationRequest = {
    client_id: string;
    nonce: string;
    response_uri: string;
    state?: string | null;
    presentation_definition: PresentationDefinition;
};

export type PresentationDefinition = {
    id: string;
    input_descriptors: InputDescriptor[];
};

export type InputDescriptor = {
    id: string;
    name?: string;
    purpose?: string;
    format?: Record<string, unknown>;
    constraints: {
        fields: {
            path: string[];
            filter?: Record<string, unknown>;
            optional?: boolean;
        }[];
    };
};

export type CredentialMatch = {
    credential: Credential;
    inputDescriptor: InputDescriptor;
    requiredDisclosures: Disclosure[];
    selectableDisclosures: Disclosure[];
};

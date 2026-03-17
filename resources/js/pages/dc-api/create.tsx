import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

type VerificationResult = {
    is_valid: boolean;
    claims: Record<string, unknown>;
    disclosed_claims: Record<string, unknown>;
    vct: string | null;
    nonce: string | null;
    errors: string[];
};

type StoreResponse = {
    id: string;
    nonce: string;
    dcql_query: {
        credentials: Array<{
            id: string;
            format: string;
            meta: { vct_values: string[] };
            claims: Array<{ path: string[] }>;
        }>;
    };
};

type VerifyResponse = {
    status: string;
    verification: VerificationResult | null;
    data: Record<string, unknown>;
};

function getXsrfToken(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}

type ApiSupport = {
    digitalCredential: boolean;
    secureContext: boolean;
    credentialsApi: boolean;
};

function detectSupport(): ApiSupport {
    return {
        digitalCredential: typeof window !== 'undefined' && 'DigitalCredential' in window,
        secureContext: typeof window !== 'undefined' && window.isSecureContext,
        credentialsApi: typeof navigator !== 'undefined' && 'credentials' in navigator,
    };
}

export default function Create() {
    const [loading, setLoading] = useState(false);
    const [verifying, setVerifying] = useState(false);
    const [result, setResult] = useState<Record<string, unknown> | null>(null);
    const [verification, setVerification] =
        useState<VerificationResult | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [showRaw, setShowRaw] = useState(false);
    const [rawResponse, setRawResponse] = useState<Record<
        string,
        unknown
    > | null>(null);
    const [showDebug, setShowDebug] = useState(false);
    const [debugRequest, setDebugRequest] = useState<Record<
        string,
        unknown
    > | null>(null);
    const [support, setSupport] = useState<ApiSupport>({
        digitalCredential: false,
        secureContext: false,
        credentialsApi: false,
    });

    useEffect(() => {
        setSupport(detectSupport());
    }, []);

    const handleRequest = useCallback(async () => {
        setLoading(true);
        setError(null);
        setResult(null);
        setVerification(null);
        setRawResponse(null);
        setShowRaw(false);

        try {
            if (!support.digitalCredential) {
                throw new Error(
                    'Digital Credentials API is not available in this browser. ' +
                    'Try Chrome 141+ on Android, or enable the flag at chrome://flags/#web-identity-digital-credentials',
                );
            }

            // 1. Create session on backend
            const storeRes = await fetch('/dc-api', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
            });

            if (!storeRes.ok) {
                throw new Error('Failed to create presentation request');
            }

            const { id, nonce, dcql_query }: StoreResponse =
                await storeRes.json();

            // 2. Build the Digital Credentials API request
            const requestData = {
                response_type: 'vp_token',
                nonce,
                dcql_query,
            };

            const credentialRequestOptions = {
                digital: {
                    requests: [
                        {
                            protocol: 'openid4vp-v1-unsigned',
                            data: requestData,
                        },
                    ],
                },
                mediation: 'required',
            } as CredentialRequestOptions;

            setDebugRequest(credentialRequestOptions as unknown as Record<string, unknown>);

            const credential = await navigator.credentials.get(credentialRequestOptions);

            if (!credential) {
                throw new Error(
                    'No credential returned — the wallet may not have a matching credential, or the request was cancelled.',
                );
            }

            const dcCredential = credential as unknown as {
                protocol: string;
                data: string;
            };

            setRawResponse({
                protocol: dcCredential.protocol,
                data: dcCredential.data,
            });

            // 3. Parse response data
            let responseData: Record<string, unknown>;
            try {
                responseData =
                    typeof dcCredential.data === 'string'
                        ? JSON.parse(dcCredential.data)
                        : dcCredential.data;
            } catch {
                responseData = { raw: dcCredential.data };
            }

            // 4. Send to backend for verification
            setVerifying(true);

            const verifyRes = await fetch(`/dc-api/${id}/verify`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
                body: JSON.stringify({ data: responseData }),
            });

            if (!verifyRes.ok) {
                throw new Error('Verification request failed');
            }

            const verifyData: VerifyResponse = await verifyRes.json();
            setResult(verifyData.data);
            setVerification(verifyData.verification);
        } catch (err) {
            if (
                err instanceof DOMException &&
                err.name === 'NotAllowedError'
            ) {
                setError(
                    'Request was cancelled or not allowed by the user.',
                );
            } else if (
                err instanceof DOMException &&
                err.name === 'AbortError'
            ) {
                setError('Request was aborted.');
            } else {
                setError(
                    err instanceof Error ? err.message : 'An error occurred',
                );
            }
        } finally {
            setLoading(false);
            setVerifying(false);
        }
    }, [support.digitalCredential]);

    const handleReset = () => {
        setResult(null);
        setVerification(null);
        setError(null);
        setRawResponse(null);
        setShowRaw(false);
        setDebugRequest(null);
        setShowDebug(false);
    };

    const renderClaimValue = (value: unknown): string => {
        if (typeof value === 'object' && value !== null) {
            return JSON.stringify(value, null, 2);
        }
        return String(value);
    };

    return (
        <>
            <Head title="Digital Credentials API Test" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-[#FDFDFC] p-6 text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
                <div className="w-full max-w-lg rounded-lg bg-white p-8 shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:bg-[#161615] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]">
                    <a
                        href="/"
                        className="mb-4 inline-block text-xs text-[#706f6c] hover:text-[#1b1b18] dark:text-[#A1A09A] dark:hover:text-[#EDEDEC]"
                    >
                        &larr; Home
                    </a>
                    <h1 className="mb-2 text-xl font-semibold">
                        Digital Credentials API
                    </h1>
                    <p className="mb-4 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                        Request a verifiable credential presentation using the
                        browser&apos;s Digital Credentials API (
                        <code className="text-xs">
                            navigator.credentials.get
                        </code>
                        ).
                    </p>

                    <div className="mb-4 rounded-md bg-[#f5f5f4] p-3 dark:bg-[#1a1a19]">
                        <p className="mb-2 text-xs font-medium text-[#706f6c] dark:text-[#A1A09A]">
                            Browser Support
                        </p>
                        <div className="space-y-1 text-xs">
                            <div className="flex items-center gap-2">
                                <span
                                    className={`inline-block h-2 w-2 rounded-full ${support.secureContext ? 'bg-green-500' : 'bg-red-500'}`}
                                />
                                <span>
                                    Secure context (HTTPS/localhost)
                                </span>
                            </div>
                            <div className="flex items-center gap-2">
                                <span
                                    className={`inline-block h-2 w-2 rounded-full ${support.credentialsApi ? 'bg-green-500' : 'bg-red-500'}`}
                                />
                                <span>Credential Management API</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <span
                                    className={`inline-block h-2 w-2 rounded-full ${support.digitalCredential ? 'bg-green-500' : 'bg-red-500'}`}
                                />
                                <span>
                                    DigitalCredential interface
                                </span>
                            </div>
                        </div>
                        {!support.digitalCredential && (
                            <p className="mt-2 text-xs text-amber-700 dark:text-amber-300">
                                Requires Chrome 141+ on Android (or enable{' '}
                                <code className="rounded bg-[#e5e5e3] px-1 dark:bg-[#2a2a28]">
                                    chrome://flags/#web-identity-digital-credentials
                                </code>
                                ), or Safari 26+ on iOS 26+.
                            </p>
                        )}
                    </div>

                    {debugRequest && (
                        <div className="mb-4">
                            <button
                                onClick={() => setShowDebug(!showDebug)}
                                className="mb-2 text-xs text-[#706f6c] underline hover:text-[#1b1b18] dark:text-[#A1A09A] dark:hover:text-[#EDEDEC]"
                            >
                                {showDebug
                                    ? 'Hide request debug'
                                    : 'Show request debug'}
                            </button>
                            {showDebug && (
                                <pre className="max-h-64 overflow-auto rounded-md bg-[#f5f5f4] p-4 font-mono text-xs dark:bg-[#0a0a0a]">
                                    {JSON.stringify(debugRequest, null, 2)}
                                </pre>
                            )}
                        </div>
                    )}

                    <div className="mb-6 rounded-md bg-[#f5f5f4] p-4 dark:bg-[#1a1a19]">
                        <p className="mb-1 text-xs font-medium text-[#706f6c] dark:text-[#A1A09A]">
                            Credential type
                        </p>
                        <p className="mb-3 font-mono text-sm">
                            AccredifyEmployeePass{' '}
                            <span className="text-xs text-[#706f6c] dark:text-[#A1A09A]">
                                (SD-JWT)
                            </span>
                        </p>
                        <p className="mb-1 text-xs font-medium text-[#706f6c] dark:text-[#A1A09A]">
                            Requested claims
                        </p>
                        <div className="flex flex-wrap gap-1.5">
                            {[
                                'employeeId',
                                'firstName',
                                'lastName',
                                'dateOfBirth',
                                'nric',
                            ].map((claim) => (
                                <span
                                    key={claim}
                                    className="rounded bg-[#e5e5e3] px-2 py-0.5 font-mono text-xs dark:bg-[#2a2a28]"
                                >
                                    {claim}
                                </span>
                            ))}
                        </div>
                        <div className="mt-3 border-t border-[#e5e5e3] pt-3 dark:border-[#2a2a28]">
                            <p className="mb-1 text-xs font-medium text-[#706f6c] dark:text-[#A1A09A]">
                                Protocol
                            </p>
                            <p className="font-mono text-xs">
                                openid4vp-v1-unsigned
                            </p>
                        </div>
                    </div>

                    {error && (
                        <div className="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">
                            {error}
                        </div>
                    )}

                    {!result && !error && (
                        <button
                            onClick={handleRequest}
                            disabled={loading || !support.digitalCredential}
                            className="w-full rounded-md bg-[#1b1b18] px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-[#2d2d2a] disabled:opacity-50 dark:bg-[#EDEDEC] dark:text-[#1b1b18] dark:hover:bg-[#d4d4d1]"
                        >
                            {loading
                                ? verifying
                                    ? 'Verifying credential...'
                                    : 'Waiting for wallet...'
                                : 'Request Credential'}
                        </button>
                    )}

                    {error && !result && (
                        <button
                            onClick={handleReset}
                            className="mt-2 w-full rounded-md bg-[#1b1b18] px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-[#2d2d2a] dark:bg-[#EDEDEC] dark:text-[#1b1b18] dark:hover:bg-[#d4d4d1]"
                        >
                            Try Again
                        </button>
                    )}

                    {result && (
                        <div className="flex flex-col gap-4">
                            {verification && (
                                <>
                                    <div
                                        className={`flex items-center gap-2 text-sm font-medium ${
                                            verification.is_valid
                                                ? 'text-green-700 dark:text-green-400'
                                                : 'text-red-700 dark:text-red-400'
                                        }`}
                                    >
                                        <span
                                            className={`inline-block h-2 w-2 rounded-full ${
                                                verification.is_valid
                                                    ? 'bg-green-500'
                                                    : 'bg-red-500'
                                            }`}
                                        />
                                        {verification.is_valid
                                            ? 'Verification passed'
                                            : 'Verification failed'}
                                    </div>

                                    {verification.vct && (
                                        <div className="rounded-md bg-[#f5f5f4] px-3 py-2 dark:bg-[#1a1a19]">
                                            <span className="text-xs font-medium text-[#706f6c] dark:text-[#A1A09A]">
                                                Credential Type
                                            </span>
                                            <p className="font-mono text-sm">
                                                {verification.vct}
                                            </p>
                                        </div>
                                    )}

                                    {verification.errors.length > 0 && (
                                        <div className="rounded-md bg-red-50 p-3 dark:bg-red-950">
                                            <p className="mb-1 text-xs font-medium text-red-700 dark:text-red-300">
                                                Errors
                                            </p>
                                            <ul className="list-inside list-disc text-xs text-red-600 dark:text-red-400">
                                                {verification.errors.map(
                                                    (err, i) => (
                                                        <li key={i}>{err}</li>
                                                    ),
                                                )}
                                            </ul>
                                        </div>
                                    )}

                                    {Object.keys(
                                        verification.disclosed_claims,
                                    ).length > 0 && (
                                        <div>
                                            <p className="mb-2 text-xs font-medium text-[#706f6c] dark:text-[#A1A09A]">
                                                Disclosed Claims
                                            </p>
                                            <div className="divide-y divide-[#e5e5e3] rounded-md bg-[#f5f5f4] dark:divide-[#2a2a28] dark:bg-[#1a1a19]">
                                                {Object.entries(
                                                    verification.disclosed_claims,
                                                ).map(([key, value]) => (
                                                    <div
                                                        key={key}
                                                        className="flex items-start justify-between gap-4 px-3 py-2"
                                                    >
                                                        <span className="text-xs font-medium text-[#706f6c] dark:text-[#A1A09A]">
                                                            {key}
                                                        </span>
                                                        <span className="text-right font-mono text-xs">
                                                            {renderClaimValue(
                                                                value,
                                                            )}
                                                        </span>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </>
                            )}

                            {!verification && (
                                <div className="flex items-center gap-2 text-sm font-medium text-green-700 dark:text-green-400">
                                    <span className="inline-block h-2 w-2 rounded-full bg-green-500" />
                                    Response received
                                </div>
                            )}

                            <div>
                                <button
                                    onClick={() => setShowRaw(!showRaw)}
                                    className="mb-2 text-xs text-[#706f6c] underline hover:text-[#1b1b18] dark:text-[#A1A09A] dark:hover:text-[#EDEDEC]"
                                >
                                    {showRaw
                                        ? 'Hide raw data'
                                        : 'Show raw data'}
                                </button>
                                {showRaw && (
                                    <div className="space-y-2">
                                        {rawResponse && (
                                            <div>
                                                <p className="mb-1 text-xs font-medium text-[#706f6c] dark:text-[#A1A09A]">
                                                    DC API Response
                                                </p>
                                                <pre className="max-h-48 overflow-auto rounded-md bg-[#f5f5f4] p-4 font-mono text-xs dark:bg-[#0a0a0a]">
                                                    {JSON.stringify(
                                                        rawResponse,
                                                        null,
                                                        2,
                                                    )}
                                                </pre>
                                            </div>
                                        )}
                                        <div>
                                            <p className="mb-1 text-xs font-medium text-[#706f6c] dark:text-[#A1A09A]">
                                                Parsed Response
                                            </p>
                                            <pre className="max-h-48 overflow-auto rounded-md bg-[#f5f5f4] p-4 font-mono text-xs dark:bg-[#0a0a0a]">
                                                {JSON.stringify(
                                                    result,
                                                    null,
                                                    2,
                                                )}
                                            </pre>
                                        </div>
                                    </div>
                                )}
                            </div>

                            <button
                                onClick={handleReset}
                                className="w-full rounded-md bg-[#1b1b18] px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-[#2d2d2a] dark:bg-[#EDEDEC] dark:text-[#1b1b18] dark:hover:bg-[#d4d4d1]"
                            >
                                New Request
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

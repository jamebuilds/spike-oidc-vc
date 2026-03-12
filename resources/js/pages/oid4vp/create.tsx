import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { QRCodeSVG } from 'qrcode.react';
import { store } from '@/actions/App/Http/Controllers/Oid4vp/PresentationRequestController';
import GetPresentationStatusController from '@/actions/App/Http/Controllers/Oid4vp/GetPresentationStatusController';

type VerificationResult = {
    is_valid: boolean;
    claims: Record<string, unknown>;
    disclosed_claims: Record<string, unknown>;
    vct: string | null;
    nonce: string | null;
    errors: string[];
};

type StatusResponse = {
    status: 'pending' | 'complete';
    data?: Record<string, unknown>;
    verification?: VerificationResult | null;
};

export default function Create() {
    const [uri, setUri] = useState<string | null>(null);
    const [requestId, setRequestId] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [result, setResult] = useState<Record<string, unknown> | null>(null);
    const [verification, setVerification] =
        useState<VerificationResult | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [showRaw, setShowRaw] = useState(false);
    const [copied, setCopied] = useState(false);
    const pollingRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const stopPolling = useCallback(() => {
        if (pollingRef.current) {
            clearInterval(pollingRef.current);
            pollingRef.current = null;
        }
    }, []);

    const startPolling = useCallback(
        (id: string) => {
            stopPolling();

            pollingRef.current = setInterval(async () => {
                try {
                    const response = await fetch(GetPresentationStatusController.url(id));
                    const data: StatusResponse = await response.json();

                    if (data.status === 'complete') {
                        setResult(data.data ?? null);
                        setVerification(data.verification ?? null);
                        stopPolling();
                    }
                } catch {
                    // Silently retry on network errors
                }
            }, 2000);
        },
        [stopPolling],
    );

    useEffect(() => {
        return () => stopPolling();
    }, [stopPolling]);

    const handleRequest = async () => {
        setLoading(true);
        setError(null);
        setResult(null);
        setVerification(null);
        setUri(null);
        setRequestId(null);
        setShowRaw(false);

        try {
            const { url, method } = store.post();
            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': decodeURIComponent(
                        document.cookie
                            .split('; ')
                            .find((row) => row.startsWith('XSRF-TOKEN='))
                            ?.split('=')[1] ?? '',
                    ),
                },
            });

            if (!response.ok) {
                throw new Error('Failed to create presentation request');
            }

            const data = await response.json();
            setUri(data.uri);
            setRequestId(data.id);
            startPolling(data.id);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'An error occurred');
        } finally {
            setLoading(false);
        }
    };

    const handleReset = () => {
        stopPolling();
        setUri(null);
        setRequestId(null);
        setResult(null);
        setVerification(null);
        setError(null);
        setShowRaw(false);
    };

    const renderClaimValue = (value: unknown): string => {
        if (typeof value === 'object' && value !== null) {
            return JSON.stringify(value, null, 2);
        }
        return String(value);
    };

    return (
        <>
            <Head title="OID4VP Credential Request" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-[#FDFDFC] p-6 text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
                <div className="w-full max-w-lg rounded-lg bg-white p-8 shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:bg-[#161615] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]">
                    <a href="/" className="mb-4 inline-block text-xs text-[#706f6c] hover:text-[#1b1b18] dark:text-[#A1A09A] dark:hover:text-[#EDEDEC]">&larr; Home</a>
                    <h1 className="mb-2 text-xl font-semibold">
                        OID4VP Credential Request
                    </h1>
                    <p className="mb-4 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                        Request a verifiable credential presentation via QR
                        code or deep link.
                    </p>

                    <div className="mb-6 rounded-md bg-[#f5f5f4] p-4 dark:bg-[#1a1a19]">
                        <p className="mb-1 text-xs font-medium text-[#706f6c] dark:text-[#A1A09A]">
                            Credential type
                        </p>
                        <p className="mb-3 font-mono text-sm">
                            AccredifyEmployeePass <span className="text-xs text-[#706f6c] dark:text-[#A1A09A]">(SD-JWT)</span>
                        </p>
                        <p className="mb-1 text-xs font-medium text-[#706f6c] dark:text-[#A1A09A]">
                            Requested claims
                        </p>
                        <div className="flex flex-wrap gap-1.5">
                            {['employeeId', 'firstName', 'lastName', 'dateOfBirth', 'nric'].map((claim) => (
                                <span
                                    key={claim}
                                    className="rounded bg-[#e5e5e3] px-2 py-0.5 font-mono text-xs dark:bg-[#2a2a28]"
                                >
                                    {claim}
                                </span>
                            ))}
                        </div>
                    </div>

                    {error && (
                        <div className="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">
                            {error}
                        </div>
                    )}

                    {!uri && !result && (
                        <button
                            onClick={handleRequest}
                            disabled={loading}
                            className="w-full rounded-md bg-[#1b1b18] px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-[#2d2d2a] disabled:opacity-50 dark:bg-[#EDEDEC] dark:text-[#1b1b18] dark:hover:bg-[#d4d4d1]"
                        >
                            {loading
                                ? 'Creating request...'
                                : 'Request Credential'}
                        </button>
                    )}

                    {uri && !result && (
                        <div className="flex flex-col items-center gap-4">
                            <a
                                href={uri}
                                className="inline-block rounded-lg bg-white p-4 dark:bg-white"
                            >
                                <QRCodeSVG value={uri} size={256} />
                            </a>
                            <p className="text-center text-sm text-[#706f6c] dark:text-[#A1A09A]">
                                Scan with your wallet or{' '}
                                <a
                                    href={uri}
                                    className="underline hover:text-[#1b1b18] dark:hover:text-[#EDEDEC]"
                                >
                                    tap here
                                </a>{' '}
                                on mobile.
                            </p>
                            <div className="flex items-center gap-2 text-xs text-[#706f6c] dark:text-[#A1A09A]">
                                <span className="inline-block h-2 w-2 animate-pulse rounded-full bg-amber-500" />
                                Waiting for wallet response...
                            </div>
                            <div className="w-full rounded-md bg-[#f5f5f4] p-3 dark:bg-[#1a1a19]">
                                <div className="mb-1 flex items-center justify-between">
                                    <span className="text-xs font-medium text-[#706f6c] dark:text-[#A1A09A]">
                                        Authorization URL
                                    </span>
                                    <button
                                        onClick={() => {
                                            navigator.clipboard.writeText(uri);
                                            setCopied(true);
                                            setTimeout(() => setCopied(false), 2000);
                                        }}
                                        className="rounded px-2 py-0.5 text-xs text-[#706f6c] transition-colors hover:bg-[#e5e5e3] hover:text-[#1b1b18] dark:text-[#A1A09A] dark:hover:bg-[#2a2a28] dark:hover:text-[#EDEDEC]"
                                    >
                                        {copied ? 'Copied!' : 'Copy'}
                                    </button>
                                </div>
                                <p className="break-all font-mono text-xs text-[#1b1b18] dark:text-[#EDEDEC]">
                                    {uri}
                                </p>
                            </div>
                            {requestId && (
                                <p className="mt-1 break-all font-mono text-xs text-[#706f6c] dark:text-[#A1A09A]">
                                    Request ID: {requestId}
                                </p>
                            )}
                        </div>
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
                                    <pre className="max-h-64 overflow-auto rounded-md bg-[#f5f5f4] p-4 font-mono text-xs dark:bg-[#0a0a0a]">
                                        {JSON.stringify(result, null, 2)}
                                    </pre>
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

import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { QRCodeSVG } from 'qrcode.react';
import { store } from '@/actions/App/Http/Controllers/PresentationRequestController';
import GetPresentationStatus from '@/actions/App/Http/Controllers/GetPresentationStatus';

type StatusResponse = {
    status: 'pending' | 'complete';
    data?: Record<string, unknown>;
};

export default function Create() {
    const [uri, setUri] = useState<string | null>(null);
    const [requestId, setRequestId] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [result, setResult] = useState<Record<string, unknown> | null>(null);
    const [error, setError] = useState<string | null>(null);
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
                    const response = await fetch(GetPresentationStatus.url(id));
                    const data: StatusResponse = await response.json();

                    if (data.status === 'complete') {
                        setResult(data.data ?? null);
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
        setUri(null);
        setRequestId(null);

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
        setError(null);
    };

    return (
        <>
            <Head title="OID4VP Credential Request" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-[#FDFDFC] p-6 text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
                <div className="w-full max-w-lg rounded-lg bg-white p-8 shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:bg-[#161615] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]">
                    <h1 className="mb-2 text-xl font-semibold">
                        OID4VP Credential Request
                    </h1>
                    <p className="mb-6 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                        Request a verifiable credential presentation via QR
                        code or deep link.
                    </p>

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
                            {requestId && (
                                <p className="mt-1 font-mono text-xs text-[#706f6c] break-all dark:text-[#A1A09A]">
                                    Request ID: {requestId}
                                </p>
                            )}
                        </div>
                    )}

                    {result && (
                        <div className="flex flex-col gap-4">
                            <div className="flex items-center gap-2 text-sm font-medium text-green-700 dark:text-green-400">
                                <span className="inline-block h-2 w-2 rounded-full bg-green-500" />
                                Response received
                            </div>
                            <pre className="max-h-96 overflow-auto rounded-md bg-[#f5f5f4] p-4 font-mono text-xs dark:bg-[#0a0a0a]">
                                {JSON.stringify(result, null, 2)}
                            </pre>
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

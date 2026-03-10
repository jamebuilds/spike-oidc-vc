import { Head, Link, router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useCallback, useState } from 'react';
import QrScannerModal from '@/components/qr-scanner-modal';
import type { Credential } from '@/types/wallet';

type Props = {
    credentials: Credential[];
};

export default function WalletDashboard({ credentials }: Props) {
    const [authRequestUrl, setAuthRequestUrl] = useState('');
    const [isScannerOpen, setIsScannerOpen] = useState(false);

    function navigateToAuthorization(input: string): void {
        const trimmed = input.trim();

        if (!trimmed) {
            return;
        }

        let params: URLSearchParams;

        try {
            const cleaned = trimmed.replace(
                /^openid4vp:\/\/authorize\?/,
                'https://wallet.local/?',
            );
            const url = new URL(cleaned);
            params = url.searchParams;
        } catch {
            params = new URLSearchParams(trimmed);
        }

        const query: Record<string, string> = {};

        for (const [key, value] of params.entries()) {
            query[key] = value;
        }

        router.get('/wallet/authorizations/create', query);
    }

    function handleAuthRequest(e: FormEvent): void {
        e.preventDefault();
        navigateToAuthorization(authRequestUrl);
    }

    const handleScanResult = useCallback((decodedText: string) => {
        setIsScannerOpen(false);
        navigateToAuthorization(decodedText);
    }, []);

    return (
        <>
            <Head title="Wallet" />
            <div className="min-h-screen bg-[#FDFDFC] p-6 text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
                <div className="mx-auto max-w-3xl">
                    <div className="mb-8 flex items-center justify-between">
                        <h1 className="text-2xl font-semibold">Web Wallet</h1>
                        <Link
                            href="/wallet/presentation-logs"
                            className="text-sm text-[#706f6c] hover:text-[#1b1b18] dark:text-[#A1A09A] dark:hover:text-[#EDEDEC]"
                        >
                            Presentation History
                        </Link>
                    </div>

                    {/* Authorization Request Input */}
                    <div className="mb-8 rounded-lg border border-[#e3e3e0] bg-white p-6 shadow-sm dark:border-[#3E3E3A] dark:bg-[#161615]">
                        <h2 className="mb-3 text-sm font-medium">
                            Authorization Request
                        </h2>
                        <form
                            onSubmit={handleAuthRequest}
                            className="flex gap-3"
                        >
                            <input
                                type="text"
                                value={authRequestUrl}
                                onChange={(e) =>
                                    setAuthRequestUrl(e.target.value)
                                }
                                placeholder="Paste openid4vp://authorize?... or request URL"
                                className="flex-1 rounded-md border border-[#e3e3e0] bg-[#FDFDFC] px-3 py-2 text-sm placeholder:text-[#A1A09A] focus:border-[#1b1b18] focus:outline-none dark:border-[#3E3E3A] dark:bg-[#0a0a0a] dark:focus:border-[#EDEDEC]"
                            />
                            <button
                                type="button"
                                onClick={() => setIsScannerOpen(true)}
                                className="rounded-md border border-[#1b1b18] px-4 py-2 text-sm font-medium text-[#1b1b18] hover:bg-[#1b1b18] hover:text-white dark:border-[#EDEDEC] dark:text-[#EDEDEC] dark:hover:bg-[#EDEDEC] dark:hover:text-[#1b1b18]"
                            >
                                Scan QR
                            </button>
                            <button
                                type="submit"
                                className="rounded-md bg-[#1b1b18] px-4 py-2 text-sm font-medium text-white hover:bg-[#2d2d2a] dark:bg-[#EDEDEC] dark:text-[#1b1b18] dark:hover:bg-[#d4d4d0]"
                            >
                                Submit
                            </button>
                        </form>
                    </div>

                    {/* Credentials */}
                    <h2 className="mb-4 text-lg font-medium">Credentials</h2>

                    {credentials.length === 0 ? (
                        <p className="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                            No credentials stored yet.
                        </p>
                    ) : (
                        <div className="space-y-4">
                            {credentials.map((credential) => (
                                <Link
                                    key={credential.id}
                                    href={`/wallet/credentials/${credential.id}`}
                                    className="block rounded-lg border border-[#e3e3e0] bg-white p-5 shadow-sm transition-colors hover:border-[#1b1b18] dark:border-[#3E3E3A] dark:bg-[#161615] dark:hover:border-[#EDEDEC]"
                                >
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <h3 className="text-sm font-medium">
                                                {credential.type}
                                            </h3>
                                            <p className="mt-1 text-xs text-[#706f6c] dark:text-[#A1A09A]">
                                                Issuer: {credential.issuer}
                                            </p>
                                        </div>
                                        <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400">
                                            Active
                                        </span>
                                    </div>
                                    <div className="mt-3 flex flex-wrap gap-1.5">
                                        {credential.disclosure_mapping?.map(
                                            (d, i) => (
                                                <span
                                                    key={i}
                                                    className="rounded bg-[#f5f5f4] px-2 py-0.5 text-xs text-[#706f6c] dark:bg-[#1f1f1e] dark:text-[#A1A09A]"
                                                >
                                                    {d.claimName}
                                                </span>
                                            ),
                                        )}
                                    </div>
                                    {credential.issued_at && (
                                        <p className="mt-2 text-xs text-[#A1A09A]">
                                            Issued:{' '}
                                            {new Date(
                                                credential.issued_at,
                                            ).toLocaleDateString()}
                                        </p>
                                    )}
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            <QrScannerModal
                open={isScannerOpen}
                onClose={() => setIsScannerOpen(false)}
                onScan={handleScanResult}
            />
        </>
    );
}

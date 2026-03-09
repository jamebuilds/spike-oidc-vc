import { Head, Link, router } from '@inertiajs/react';
import type { Credential } from '@/types/wallet';

type Props = {
    credential: Credential;
};

function formatValue(value: unknown): string {
    if (typeof value === 'object' && value !== null) {
        return JSON.stringify(value, null, 2);
    }

    return String(value);
}

export default function CredentialShow({ credential }: Props) {
    function handleDelete() {
        if (confirm('Are you sure you want to delete this credential?')) {
            router.delete(`/wallet/credentials/${credential.id}`);
        }
    }

    return (
        <>
            <Head title={`Credential: ${credential.type}`} />
            <div className="min-h-screen bg-[#FDFDFC] p-6 text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
                <div className="mx-auto max-w-3xl">
                    <div className="mb-6">
                        <Link
                            href="/wallet"
                            className="text-sm text-[#706f6c] hover:text-[#1b1b18] dark:text-[#A1A09A] dark:hover:text-[#EDEDEC]"
                        >
                            &larr; Back to Wallet
                        </Link>
                    </div>

                    <div className="rounded-lg border border-[#e3e3e0] bg-white p-6 shadow-sm dark:border-[#3E3E3A] dark:bg-[#161615]">
                        <div className="mb-6 flex items-start justify-between">
                            <div>
                                <h1 className="text-xl font-semibold">{credential.type}</h1>
                                <p className="mt-1 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                                    Issuer: {credential.issuer}
                                </p>
                            </div>
                            <button
                                onClick={handleDelete}
                                className="rounded-md border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20"
                            >
                                Delete
                            </button>
                        </div>

                        {/* Metadata */}
                        <div className="mb-6 grid grid-cols-2 gap-4 text-sm">
                            {credential.issued_at && (
                                <div>
                                    <span className="text-[#706f6c] dark:text-[#A1A09A]">Issued:</span>{' '}
                                    {new Date(credential.issued_at).toLocaleDateString()}
                                </div>
                            )}
                            {credential.expires_at && (
                                <div>
                                    <span className="text-[#706f6c] dark:text-[#A1A09A]">Expires:</span>{' '}
                                    {new Date(credential.expires_at).toLocaleDateString()}
                                </div>
                            )}
                        </div>

                        {/* Disclosures */}
                        <h2 className="mb-3 text-sm font-medium">Selectively Disclosable Claims</h2>
                        <div className="space-y-3">
                            {credential.disclosure_mapping?.map((disclosure, index) => (
                                <div
                                    key={index}
                                    className="rounded-md border border-[#e3e3e0] p-4 dark:border-[#3E3E3A]"
                                >
                                    <div className="flex items-start justify-between">
                                        <span className="text-sm font-medium">{disclosure.claimName}</span>
                                        <span className="rounded bg-[#f5f5f4] px-2 py-0.5 text-xs text-[#A1A09A] dark:bg-[#1f1f1e]">
                                            SD
                                        </span>
                                    </div>
                                    <pre className="mt-2 overflow-x-auto text-xs text-[#706f6c] dark:text-[#A1A09A]">
                                        {formatValue(disclosure.claimValue)}
                                    </pre>
                                </div>
                            ))}
                        </div>

                        {/* Confirmation Key */}
                        {credential.cnf_jwk && (
                            <div className="mt-6">
                                <h2 className="mb-3 text-sm font-medium">Confirmation Key (cnf)</h2>
                                <pre className="overflow-x-auto rounded-md bg-[#f5f5f4] p-3 text-xs text-[#706f6c] dark:bg-[#1f1f1e] dark:text-[#A1A09A]">
                                    {JSON.stringify(credential.cnf_jwk, null, 2)}
                                </pre>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

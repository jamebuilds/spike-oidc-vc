import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { AuthorizationRequest, CredentialMatch } from '@/types/wallet';

type Props = {
    matches: CredentialMatch[];
    authRequest: AuthorizationRequest;
    error: string | null;
};

export default function AuthorizationCreate({
    matches,
    authRequest,
    error,
}: Props) {
    const [selectedMatchIndex, setSelectedMatchIndex] = useState(0);
    const match = matches[selectedMatchIndex] ?? null;

    // Track which optional disclosures are checked
    const [checkedOptional, setCheckedOptional] = useState<
        Record<number, boolean>
    >({});

    const { post, processing, errors, transform } = useForm({
        credential_id: match?.credential.id ?? 0,
        selected_disclosures: [] as number[],
        nonce: authRequest?.nonce ?? '',
        client_id: authRequest?.client_id ?? '',
        response_uri: authRequest?.response_uri ?? '',
        state: authRequest?.state ?? '',
        definition_id: authRequest?.presentation_definition?.id ?? '',
        descriptor_id: match?.inputDescriptor?.id ?? '',
    });

    // Transform computes the final payload at submission time, using current state
    transform(() => {
        const requiredIndices =
            match?.requiredDisclosures.map((d) => d.index) ?? [];
        const selectedOptionalIndices =
            match?.selectableDisclosures
                .filter((d) => checkedOptional[d.index])
                .map((d) => d.index) ?? [];

        return {
            credential_id: match?.credential.id ?? 0,
            selected_disclosures: [
                ...requiredIndices,
                ...selectedOptionalIndices,
            ],
            nonce: authRequest?.nonce ?? '',
            client_id: authRequest?.client_id ?? '',
            response_uri: authRequest?.response_uri ?? '',
            state: authRequest?.state ?? '',
            definition_id: authRequest?.presentation_definition?.id ?? '',
            descriptor_id: match?.inputDescriptor?.id ?? '',
        };
    });

    function toggleOptional(index: number) {
        setCheckedOptional((prev) => ({ ...prev, [index]: !prev[index] }));
    }

    function handleApprove() {
        if (!match) {
            return;
        }

        post('/wallet/authorizations');
    }

    if (error) {
        return (
            <>
                <Head title="Authorization Error" />
                <div className="flex min-h-screen items-center justify-center bg-[#FDFDFC] p-6 dark:bg-[#0a0a0a]">
                    <div className="rounded-lg border border-red-200 bg-white p-6 text-center dark:border-red-800 dark:bg-[#161615]">
                        <p className="text-sm text-red-600 dark:text-red-400">
                            {error}
                        </p>
                        <Link
                            href="/wallet"
                            className="mt-4 inline-block text-sm text-[#706f6c] hover:text-[#1b1b18] dark:text-[#A1A09A] dark:hover:text-[#EDEDEC]"
                        >
                            &larr; Back to Wallet
                        </Link>
                    </div>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Authorize Presentation" />
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

                    <h1 className="mb-6 text-xl font-semibold">
                        Authorization Request
                    </h1>

                    {/* Verifier Info */}
                    <div className="mb-6 rounded-lg border border-[#e3e3e0] bg-white p-5 shadow-sm dark:border-[#3E3E3A] dark:bg-[#161615]">
                        <h2 className="mb-2 text-sm font-medium">Verifier</h2>
                        <p className="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                            {authRequest.client_id}
                        </p>
                        {authRequest.presentation_definition
                            ?.input_descriptors?.[0]?.purpose && (
                            <p className="mt-2 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                                Purpose:{' '}
                                {
                                    authRequest.presentation_definition
                                        .input_descriptors[0].purpose
                                }
                            </p>
                        )}
                    </div>

                    {matches.length === 0 ? (
                        <div className="rounded-lg border border-amber-200 bg-amber-50 p-5 dark:border-amber-800 dark:bg-amber-900/20">
                            <p className="text-sm text-amber-700 dark:text-amber-400">
                                No matching credentials found for this request.
                            </p>
                        </div>
                    ) : (
                        <>
                            {/* Credential selector (if multiple matches) */}
                            {matches.length > 1 && (
                                <div className="mb-4">
                                    <label className="mb-1 block text-sm font-medium">
                                        Select credential
                                    </label>
                                    <select
                                        value={selectedMatchIndex}
                                        onChange={(e) => {
                                            setSelectedMatchIndex(
                                                Number(e.target.value),
                                            );
                                            setCheckedOptional({});
                                        }}
                                        className="w-full rounded-md border border-[#e3e3e0] bg-[#FDFDFC] px-3 py-2 text-sm dark:border-[#3E3E3A] dark:bg-[#0a0a0a]"
                                    >
                                        {matches.map((m, i) => (
                                            <option key={i} value={i}>
                                                {m.credential.type} —{' '}
                                                {m.credential.issuer}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            {match && (
                                <div className="rounded-lg border border-[#e3e3e0] bg-white p-5 shadow-sm dark:border-[#3E3E3A] dark:bg-[#161615]">
                                    <h2 className="mb-1 text-sm font-medium">
                                        {match.credential.type}
                                    </h2>
                                    <p className="mb-4 text-xs text-[#706f6c] dark:text-[#A1A09A]">
                                        Issuer: {match.credential.issuer}
                                    </p>

                                    {/* Required Disclosures */}
                                    {match.requiredDisclosures.length > 0 && (
                                        <div className="mb-4">
                                            <h3 className="mb-2 text-xs font-medium tracking-wide text-[#706f6c] uppercase dark:text-[#A1A09A]">
                                                Required Claims
                                            </h3>
                                            <div className="space-y-2">
                                                {match.requiredDisclosures.map(
                                                    (d) => (
                                                        <label
                                                            key={d.index}
                                                            className="flex items-center gap-3 rounded-md border border-[#e3e3e0] p-3 dark:border-[#3E3E3A]"
                                                        >
                                                            <input
                                                                type="checkbox"
                                                                checked
                                                                disabled
                                                                className="accent-emerald-600"
                                                            />
                                                            <div>
                                                                <span className="text-sm font-medium">
                                                                    {
                                                                        d.claimName
                                                                    }
                                                                </span>
                                                                <span className="ml-2 text-xs text-[#706f6c] dark:text-[#A1A09A]">
                                                                    {typeof d.claimValue ===
                                                                    'object'
                                                                        ? JSON.stringify(
                                                                              d.claimValue,
                                                                          )
                                                                        : String(
                                                                              d.claimValue,
                                                                          )}
                                                                </span>
                                                            </div>
                                                        </label>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    {/* Optional Disclosures */}
                                    {match.selectableDisclosures.length > 0 && (
                                        <div className="mb-4">
                                            <h3 className="mb-2 text-xs font-medium tracking-wide text-[#706f6c] uppercase dark:text-[#A1A09A]">
                                                Optional Claims
                                            </h3>
                                            <div className="space-y-2">
                                                {match.selectableDisclosures.map(
                                                    (d) => (
                                                        <label
                                                            key={d.index}
                                                            className="flex cursor-pointer items-center gap-3 rounded-md border border-[#e3e3e0] p-3 hover:bg-[#f5f5f4] dark:border-[#3E3E3A] dark:hover:bg-[#1f1f1e]"
                                                        >
                                                            <input
                                                                type="checkbox"
                                                                checked={
                                                                    !!checkedOptional[
                                                                        d.index
                                                                    ]
                                                                }
                                                                onChange={() =>
                                                                    toggleOptional(
                                                                        d.index,
                                                                    )
                                                                }
                                                                className="accent-emerald-600"
                                                            />
                                                            <div>
                                                                <span className="text-sm font-medium">
                                                                    {
                                                                        d.claimName
                                                                    }
                                                                </span>
                                                                <span className="ml-2 text-xs text-[#706f6c] dark:text-[#A1A09A]">
                                                                    {typeof d.claimValue ===
                                                                    'object'
                                                                        ? JSON.stringify(
                                                                              d.claimValue,
                                                                          )
                                                                        : String(
                                                                              d.claimValue,
                                                                          )}
                                                                </span>
                                                            </div>
                                                        </label>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    {/* Errors */}
                                    {Object.keys(errors).length > 0 && (
                                        <div className="mb-4 rounded-md border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20">
                                            {Object.values(errors).map(
                                                (msg, i) => (
                                                    <p
                                                        key={i}
                                                        className="text-xs text-red-600 dark:text-red-400"
                                                    >
                                                        {msg}
                                                    </p>
                                                ),
                                            )}
                                        </div>
                                    )}

                                    {/* Actions */}
                                    <div className="mt-6 flex gap-3">
                                        <button
                                            onClick={handleApprove}
                                            disabled={processing}
                                            className="rounded-md bg-emerald-600 px-5 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                                        >
                                            {processing
                                                ? 'Submitting...'
                                                : 'Approve'}
                                        </button>
                                        <Link
                                            href="/wallet"
                                            className="rounded-md border border-[#e3e3e0] px-5 py-2 text-sm font-medium hover:bg-[#f5f5f4] dark:border-[#3E3E3A] dark:hover:bg-[#1f1f1e]"
                                        >
                                            Deny
                                        </Link>
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </div>
            </div>
        </>
    );
}

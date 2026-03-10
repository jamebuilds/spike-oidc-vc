import { Head, Link } from '@inertiajs/react';
import type { PresentationLog } from '@/types/wallet';

type PaginatedData<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
};

type Props = {
    logs: PaginatedData<PresentationLog>;
};

export default function PresentationHistory({ logs }: Props) {
    return (
        <>
            <Head title="Presentation History" />
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
                        Presentation History
                    </h1>

                    {logs.data.length === 0 ? (
                        <p className="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                            No presentations yet.
                        </p>
                    ) : (
                        <div className="space-y-3">
                            {logs.data.map((log) => (
                                <div
                                    key={log.id}
                                    className="rounded-lg border border-[#e3e3e0] bg-white p-4 shadow-sm dark:border-[#3E3E3A] dark:bg-[#161615]"
                                >
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <p className="text-sm font-medium">
                                                {log.credential?.type ??
                                                    'Unknown Credential'}
                                            </p>
                                            <p className="mt-0.5 text-xs text-[#706f6c] dark:text-[#A1A09A]">
                                                Verifier:{' '}
                                                {log.verifier_client_id}
                                            </p>
                                        </div>
                                        <span
                                            className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                                                log.status === 'success'
                                                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400'
                                                    : 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400'
                                            }`}
                                        >
                                            {log.status}
                                        </span>
                                    </div>
                                    <div className="mt-2 flex flex-wrap gap-1.5">
                                        {log.disclosed_claims.map(
                                            (claim, i) => (
                                                <span
                                                    key={i}
                                                    className="rounded bg-[#f5f5f4] px-2 py-0.5 text-xs text-[#706f6c] dark:bg-[#1f1f1e] dark:text-[#A1A09A]"
                                                >
                                                    {claim}
                                                </span>
                                            ),
                                        )}
                                    </div>
                                    {log.submitted_at && (
                                        <p className="mt-2 text-xs text-[#A1A09A]">
                                            {new Date(
                                                log.submitted_at,
                                            ).toLocaleString()}
                                        </p>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}

                    {/* Pagination */}
                    {logs.last_page > 1 && (
                        <div className="mt-6 flex justify-center gap-3">
                            {logs.prev_page_url && (
                                <Link
                                    href={logs.prev_page_url}
                                    className="rounded-md border border-[#e3e3e0] px-4 py-2 text-sm hover:bg-[#f5f5f4] dark:border-[#3E3E3A] dark:hover:bg-[#1f1f1e]"
                                >
                                    Previous
                                </Link>
                            )}
                            <span className="px-4 py-2 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                                Page {logs.current_page} of {logs.last_page}
                            </span>
                            {logs.next_page_url && (
                                <Link
                                    href={logs.next_page_url}
                                    className="rounded-md border border-[#e3e3e0] px-4 py-2 text-sm hover:bg-[#f5f5f4] dark:border-[#3E3E3A] dark:hover:bg-[#1f1f1e]"
                                >
                                    Next
                                </Link>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

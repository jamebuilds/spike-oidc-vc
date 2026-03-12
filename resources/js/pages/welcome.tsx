import { Head } from '@inertiajs/react';

export default function Welcome() {
    return (
        <>
            <Head title="Home" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-[#FDFDFC] p-6 text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
                <div className="w-full max-w-2xl">
                    <h1 className="mb-2 text-2xl font-semibold">OID4VP / OID4VCI Spike</h1>
                    <p className="mb-6 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                        A proof-of-concept for issuing and verifying SD-JWT verifiable credentials
                        using the OpenID for Verifiable Presentations (OID4VP) and OpenID for Verifiable
                        Credential Issuance (OID4VCI) protocols with a Google Wallet-compatible flow.
                    </p>

                    <div className="mb-8 rounded-md bg-[#f5f5f4] p-4 text-sm leading-relaxed dark:bg-[#161615]">
                        <p className="mb-3 font-medium">How it works</p>
                        <ol className="list-inside list-decimal space-y-1.5 text-[#706f6c] dark:text-[#A1A09A]">
                            <li><span className="font-medium text-[#1b1b18] dark:text-[#EDEDEC]">Issue</span> &mdash; The issuer creates an AccredifyEmployeePass SD-JWT credential and presents a QR code. The wallet scans it and stores the credential.</li>
                            <li><span className="font-medium text-[#1b1b18] dark:text-[#EDEDEC]">Present</span> &mdash; The verifier requests specific claims (employeeId, firstName, lastName, dateOfBirth, nric). The wallet selectively discloses only the requested claims and submits a VP token.</li>
                            <li><span className="font-medium text-[#1b1b18] dark:text-[#EDEDEC]">Verify</span> &mdash; The verifier validates the SD-JWT signature, matches disclosed claims against the _sd hashes, and checks the key-binding JWT nonce.</li>
                        </ol>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <a
                            href="/oid4vci/create"
                            className="rounded-lg bg-white p-5 shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] transition-colors hover:bg-[#f5f5f4] dark:bg-[#161615] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d] dark:hover:bg-[#1a1a19]"
                        >
                            <p className="mb-1 text-sm font-medium">Issue Credential (OID4VCI)</p>
                            <p className="text-xs text-[#706f6c] dark:text-[#A1A09A]">
                                Issue a demo AccredifyEmployeePass SD-JWT credential to a wallet via QR code.
                            </p>
                        </a>
                        <a
                            href="/oid4vp/create"
                            className="rounded-lg bg-white p-5 shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] transition-colors hover:bg-[#f5f5f4] dark:bg-[#161615] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d] dark:hover:bg-[#1a1a19]"
                        >
                            <p className="mb-1 text-sm font-medium">Verify Credential (OID4VP)</p>
                            <p className="text-xs text-[#706f6c] dark:text-[#A1A09A]">
                                Request selective disclosure of claims from a wallet-held SD-JWT credential.
                            </p>
                        </a>
                    </div>
                </div>
            </div>
        </>
    );
}

import type { Html5Qrcode } from 'html5-qrcode';
import { useEffect, useRef, useState } from 'react';

type Props = {
    open: boolean;
    onClose: () => void;
    onScan: (decodedText: string) => void;
};

export default function QrScannerModal({ open, onClose, onScan }: Props) {
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(true);
    const scannerRef = useRef<Html5Qrcode | null>(null);
    const containerId = 'qr-reader';

    useEffect(() => {
        if (!open) {
            return;
        }

        let cancelled = false;

        async function startScanner(): Promise<void> {
            setError(null);
            setLoading(true);

            const { Html5Qrcode } = await import('html5-qrcode');

            if (cancelled) {
                return;
            }

            const scanner = new Html5Qrcode(containerId);
            scannerRef.current = scanner;

            try {
                await scanner.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 250, height: 250 } },
                    (decodedText) => {
                        onScan(decodedText);
                    },
                    () => {},
                );
                if (!cancelled) {
                    setLoading(false);
                }
            } catch (err) {
                if (!cancelled) {
                    setLoading(false);
                    setError(
                        err instanceof Error
                            ? err.message
                            : 'Camera access denied or unavailable.',
                    );
                }
            }
        }

        startScanner();

        return () => {
            cancelled = true;
            const scanner = scannerRef.current;
            if (scanner) {
                scanner
                    .stop()
                    .then(() => scanner.clear())
                    .catch(() => {});
                scannerRef.current = null;
            }
        };
    }, [open, onScan]);

    if (!open) {
        return null;
    }

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
            onClick={onClose}
        >
            <div
                className="mx-4 w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-[#161615]"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="text-sm font-medium text-[#1b1b18] dark:text-[#EDEDEC]">
                        Scan QR Code
                    </h2>
                    <button
                        onClick={onClose}
                        className="text-[#706f6c] hover:text-[#1b1b18] dark:text-[#A1A09A] dark:hover:text-[#EDEDEC]"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="20"
                            height="20"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        >
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </button>
                </div>

                <div className="overflow-hidden rounded-md bg-black">
                    <div id={containerId} className="min-h-[300px]" />
                </div>

                {loading && !error && (
                    <p className="mt-3 animate-pulse text-center text-xs text-[#706f6c] dark:text-[#A1A09A]">
                        Requesting camera access...
                    </p>
                )}

                {error && (
                    <p className="mt-3 text-center text-xs text-red-600 dark:text-red-400">
                        {error}
                    </p>
                )}
            </div>
        </div>
    );
}

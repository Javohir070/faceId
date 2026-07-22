export default function BiometricHeroVisual({ brandName = 'EIdora' }) {
    return (
        <div className="relative w-[min(88vw,320px)] sm:w-[min(75vw,360px)] md:w-[min(60vw,400px)] lg:w-[85%] lg:max-w-[440px] xl:max-w-[480px]">
            <div
                className="absolute -inset-3 rounded-[2rem] border border-white/15 bg-white/5 sm:-inset-4"
                aria-hidden="true"
            />
            <div
                className="absolute -inset-1.5 rounded-[1.75rem] border border-white/25 sm:-inset-2"
                aria-hidden="true"
            />

            <div className="relative overflow-hidden rounded-[1.5rem] border-2 border-white/90 bg-white p-5 shadow-ambient sm:rounded-[1.75rem] sm:p-6 md:p-8">
                <div className="pointer-events-none absolute -right-8 -top-8 h-32 w-32 rounded-full bg-brand-100/80 blur-2xl" aria-hidden="true" />
                <div className="pointer-events-none absolute -bottom-10 -left-8 h-36 w-36 rounded-full bg-brand-50 blur-2xl" aria-hidden="true" />

                <svg
                    viewBox="0 0 320 260"
                    role="img"
                    aria-label="Biometrik kirish nazorati"
                    className="relative mx-auto h-auto w-full max-w-[300px]"
                >
                    <defs>
                        <linearGradient id="deviceGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stopColor="#228f5c" />
                            <stop offset="100%" stopColor="#145734" />
                        </linearGradient>
                        <linearGradient id="scanGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" stopColor="#4aba82" stopOpacity="0.1" />
                            <stop offset="50%" stopColor="#228f5c" stopOpacity="0.55" />
                            <stop offset="100%" stopColor="#4aba82" stopOpacity="0.1" />
                        </linearGradient>
                    </defs>

                    {/* Device body */}
                    <rect x="78" y="28" width="164" height="204" rx="18" fill="#f3f4f6" stroke="#d1d5db" strokeWidth="2" />
                    <rect x="92" y="42" width="136" height="148" rx="12" fill="url(#deviceGradient)" />

                    {/* Screen */}
                    <rect x="104" y="56" width="112" height="112" rx="10" fill="#0f2e21" opacity="0.92" />

                    {/* Face scan brackets */}
                    <path d="M118 78 H138 V88 H118 Z" fill="none" stroke="#7fd3a8" strokeWidth="3" strokeLinecap="round" />
                    <path d="M182 78 H202 V88 H182 Z" fill="none" stroke="#7fd3a8" strokeWidth="3" strokeLinecap="round" />
                    <path d="M118 136 H138 V146 H118 Z" fill="none" stroke="#7fd3a8" strokeWidth="3" strokeLinecap="round" />
                    <path d="M182 136 H202 V146 H182 Z" fill="none" stroke="#7fd3a8" strokeWidth="3" strokeLinecap="round" />

                    {/* Face silhouette */}
                    <circle cx="160" cy="104" r="22" fill="#b3e6c9" />
                    <path
                        d="M132 138c6-16 20-24 28-24s22 8 28 24"
                        fill="none"
                        stroke="#d8f3e4"
                        strokeWidth="6"
                        strokeLinecap="round"
                    />

                    {/* Scan line */}
                    <rect x="108" y="112" width="104" height="4" rx="2" fill="url(#scanGradient)" opacity="0.95" />

                    {/* Fingerprint badge */}
                    <circle cx="160" cy="198" r="18" fill="#ffffff" opacity="0.95" />
                    <path
                        d="M160 186c-4 0-7 3-7 7 0 5 4 8 7 11 3-3 7-6 7-11 0-4-3-7-7-7z"
                        fill="none"
                        stroke="#228f5c"
                        strokeWidth="2"
                    />
                    <path
                        d="M160 190c-2 0-3.5 1.5-3.5 3.5s1.5 3.5 3.5 5c1.5-1.5 3.5-3 3.5-5S162 190 160 190z"
                        fill="none"
                        stroke="#228f5c"
                        strokeWidth="1.5"
                    />

                    {/* Door / access */}
                    <rect x="34" y="92" width="28" height="56" rx="6" fill="#e5f7ed" stroke="#7fd3a8" strokeWidth="2" />
                    <circle cx="54" cy="120" r="3" fill="#228f5c" />
                    <path d="M42 108h12M42 132h12" stroke="#4aba82" strokeWidth="2" strokeLinecap="round" />

                    {/* ID card */}
                    <rect x="258" y="98" width="34" height="44" rx="6" fill="#ffffff" stroke="#7fd3a8" strokeWidth="2" />
                    <circle cx="275" cy="112" r="7" fill="#b3e6c9" />
                    <rect x="264" y="124" width="22" height="4" rx="2" fill="#d8f3e4" />
                    <rect x="264" y="132" width="16" height="3" rx="1.5" fill="#d8f3e4" />

                    {/* Success pulse */}
                    <circle cx="258" cy="74" r="16" fill="#228f5c" />
                    <path d="M251 74l5 5 10-10" fill="none" stroke="#ffffff" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" />
                </svg>

                <div className="relative mt-4 border-t border-line/80 pt-4 text-center sm:mt-5 sm:pt-5">
                    <p className="text-lg font-extrabold tracking-tight text-brand-800 sm:text-xl">
                        {brandName}
                    </p>
                    <p className="mt-1 text-xs font-semibold uppercase tracking-[0.16em] text-brand-600 sm:text-sm">
                        Biometrik kirish nazorati
                    </p>
                </div>
            </div>
        </div>
    );
}

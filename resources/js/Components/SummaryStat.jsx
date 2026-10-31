/** Sahifa tepasidagi kichik ko‘rsatkich kartochkasi: ikonka, nom va son. */
export default function SummaryStat({ icon, label, value, note, className = '' }) {
    return (
        <div className={`flex min-w-0 items-center gap-4 rounded-xl border border-line bg-surface p-3 shadow-ambient sm:p-4 ${className}`.trim()}>
            <span
                aria-hidden="true"
                className="hidden h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-lg text-brand-700 sm:flex"
            >
                {icon}
            </span>
            <div className="min-w-0">
                <p className="text-xs font-semibold leading-tight text-ink-muted sm:truncate sm:text-sm">{label}</p>
                <p className="mt-0.5 flex flex-wrap items-baseline gap-x-2">
                    <span className="text-xl font-extrabold tabular-nums text-ink sm:text-2xl">{value}</span>
                    {note ? <span className="truncate text-xs font-semibold text-ink-muted">{note}</span> : null}
                </p>
            </div>
        </div>
    );
}

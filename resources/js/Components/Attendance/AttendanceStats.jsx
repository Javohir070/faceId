function Dot({ className }) {
    return <span className={`inline-block h-2 w-2 rounded-full ${className}`} aria-hidden="true" />;
}

function Stat({ label, dot, value, percent, percentClass, note }) {
    return (
        <div className="min-w-0">
            <p className="flex items-center gap-2 text-sm text-ink-muted">
                {dot}
                {label}
            </p>
            <p className="mt-1 flex flex-wrap items-baseline gap-x-2">
                <span className="text-2xl font-extrabold text-ink">{value}</span>
                {percent !== undefined && <span className={`text-sm font-bold ${percentClass}`}>{percent} %</span>}
                {note && <span className="text-sm text-ink-muted">{note}</span>}
            </p>
        </div>
    );
}

/** Davomat sahifasidagi statistika kartochkasi (tanlangan davr, joriy filtrdagi barcha xodimlar). */
export default function AttendanceStats({ stats }) {
    if (!stats) {
        return null;
    }

    return (
        <section className="grid grid-cols-2 gap-6 rounded-xl border border-line bg-surface p-6 md:grid-cols-3 xl:grid-cols-6">
            <Stat
                label="Ish kunlari"
                value={`${stats.elapsed_days} / ${stats.work_days}`}
                note={stats.avg_arrival ? `o‘rtacha kelish ${stats.avg_arrival}` : null}
            />
            <Stat
                label="O‘z vaqtida"
                dot={<Dot className="bg-positive" />}
                value={stats.on_time}
                percent={stats.percent.on_time}
                percentClass="text-positive"
                note={stats.early ? `${stats.early} marta erta ketgan` : null}
            />
            <Stat
                label="Kech kelgan"
                dot={<Dot className="bg-caution" />}
                value={stats.late}
                percent={stats.percent.late}
                percentClass="text-caution"
            />
            <Stat
                label="Sababli"
                dot={<Dot className="border-2 border-positive bg-transparent" />}
                value={stats.excused}
                percent={stats.percent.excused}
                percentClass="text-positive"
            />
            <Stat
                label="Kelmagan"
                dot={<Dot className="bg-negative" />}
                value={stats.absent}
                percent={stats.percent.absent}
                percentClass="text-negative"
            />
            <div className="min-w-0">
                <p className="text-sm text-ink-muted">Ishladi</p>
                <p className="mt-1 text-2xl font-extrabold text-ink">{stats.worked}</p>
                <p className="text-sm text-ink-muted">reja: {stats.plan}</p>
            </div>
        </section>
    );
}

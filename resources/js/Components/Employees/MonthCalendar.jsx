import { LeftOutlined, LogoutOutlined, RightOutlined } from '@ant-design/icons';
import { Button } from 'antd';
import { MONTHS } from '../../utils/uzDate';

const WEEKDAYS_SHORT = ['Du', 'Se', 'Ch', 'Pa', 'Ju', 'Sh', 'Ya'];

/** Kun holati → katak foni va nuqta (Davomat sahifasidagi ma’nolar bilan bir xil, tokenlardan). */
const STATUS = {
    on_time: { label: 'O‘z vaqtida', cell: 'bg-positive/10', dot: 'bg-positive' },
    late: { label: 'Kech kelgan', cell: 'bg-caution/10', dot: 'bg-caution' },
    excused: { label: 'Sababli (ruxsat)', cell: 'bg-surface ring-1 ring-inset ring-positive', dot: 'border-2 border-positive' },
    absent: { label: 'Kelmagan', cell: 'bg-negative/10', dot: 'bg-negative' },
    pending: { label: 'Hali kelmagan', cell: 'bg-surface ring-1 ring-inset ring-line', dot: 'border-2 border-ink-subtle' },
    weekend: { label: 'Dam olish kuni', cell: 'bg-surface-muted/60', dot: null },
    inactive: { label: 'Ish muddatidan tashqari', cell: 'bg-surface-muted', dot: null },
    future: { label: '', cell: 'bg-surface ring-1 ring-inset ring-line/60', dot: null },
};

const LEGEND = ['on_time', 'late', 'excused', 'absent', 'weekend'];

export function monthTitle(month) {
    const [year, number] = month.split('-').map(Number);
    const name = MONTHS[number - 1];

    return `${name.charAt(0).toUpperCase()}${name.slice(1)} ${year}`;
}

function Dot({ className }) {
    return <span aria-hidden="true" className={`inline-block h-2 w-2 shrink-0 rounded-full ${className}`} />;
}

function Stat({ label, value, note, dot }) {
    return (
        <div className="min-w-0">
            <dt className="flex items-center gap-2 text-sm text-ink-muted">
                {dot ? <Dot className={dot} /> : null}
                {label}
            </dt>
            <dd className="mt-1 flex flex-wrap items-baseline gap-x-2">
                <span className="whitespace-nowrap text-2xl font-extrabold tabular-nums text-ink">{value}</span>
                {note ? <span className="text-xs font-semibold text-ink-muted">{note}</span> : null}
            </dd>
        </div>
    );
}

function dayLabel(day, month) {
    const parts = [`${day.day}-${MONTHS[Number(month.split('-')[1]) - 1]}`, STATUS[day.status]?.label];
    if (day.in) {
        parts.push(`kelgan ${day.in}`);
    }
    if (day.out && !day.inside) {
        parts.push(`ketgan ${day.out}`);
    }
    if (day.exits) {
        parts.push(`${day.exits} marta chiqqan`);
    }
    parts.push(...day.notes);

    return parts.filter(Boolean).join(', ');
}

function CalendarDay({ day, month, onOpen }) {
    const meta = STATUS[day.status] ?? STATUS.future;
    const hasPasses = day.entries + day.exits > 0;
    const clickable = hasPasses || !['future', 'inactive', 'weekend'].includes(day.status);

    return (
        <button
            type="button"
            disabled={!clickable}
            onClick={() => onOpen(day.date)}
            aria-label={dayLabel(day, month)}
            title={dayLabel(day, month)}
            className={`flex min-h-[3.25rem] min-w-0 flex-col rounded-lg p-1.5 text-left transition duration-150 ease-productive sm:min-h-[6rem] sm:p-2 ${meta.cell} ${day.is_today ? 'outline outline-2 -outline-offset-2 outline-brand-600' : ''} ${clickable ? 'cursor-pointer hover:brightness-95 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-600 dark:hover:brightness-125' : 'cursor-default'}`}
        >
            <span className="flex items-center justify-between gap-1">
                <span className={`text-sm font-extrabold tabular-nums ${day.is_weekend || day.status === 'inactive' ? 'text-ink-subtle' : 'text-ink'}`}>
                    {day.day}
                </span>
                {meta.dot ? <Dot className={meta.dot} /> : null}
            </span>
            {day.in ? (
                <span className="mt-1 hidden truncate text-[11px] font-bold tabular-nums tracking-tight text-ink sm:block">
                    {day.in}
                    –
                    {day.inside ? 'ishda' : (day.out ?? '…')}
                </span>
            ) : null}
            {day.worked ? (
                <span className="hidden truncate text-[11px] font-semibold text-ink-muted sm:block">{day.worked}</span>
            ) : null}
            <span className="mt-auto hidden flex-wrap gap-1 pt-1 sm:flex">
                {day.exits > 0 ? (
                    <span className="inline-flex items-center gap-1 rounded bg-surface/80 px-1 text-[11px] font-semibold text-ink-muted">
                        <LogoutOutlined aria-hidden="true" />
                        {day.exits}
                        ×
                    </span>
                ) : null}
                {day.notes.map((note) => (
                    <span key={note} className="truncate rounded bg-surface/80 px-1 text-[11px] font-semibold text-brand-700">
                        {note}
                    </span>
                ))}
            </span>
        </button>
    );
}

/**
 * Xodimning oylik davomat kalendari: oy ko‘rsatkichlari va kunlar (bosilsa kun tafsiloti ochiladi).
 */
export default function MonthCalendar({ month, currentMonth, onMonthChange, onDayOpen }) {
    const { stats, days } = month;
    const counted = stats.on_time + stats.late + stats.excused + stats.absent;
    const offset = (days[0]?.weekday ?? 1) - 1;

    return (
        <section className="table-shell">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                <div className="min-w-0">
                    <h2 className="text-lg font-extrabold text-ink">Davomat kalendari</h2>
                    <p className="mt-0.5 text-sm text-ink-muted">Ish kunlari dushanba–juma; kunni bosing — kirish-chiqishlari ochiladi.</p>
                </div>
                <div className="flex items-center gap-1.5">
                    <Button icon={<LeftOutlined />} aria-label="Oldingi oy" onClick={() => onMonthChange(month.prev)} />
                    <span className="min-w-[8.5rem] text-center text-sm font-extrabold text-ink" aria-live="polite">
                        {monthTitle(month.month)}
                    </span>
                    <Button
                        icon={<RightOutlined />}
                        aria-label="Keyingi oy"
                        disabled={month.month >= currentMonth}
                        onClick={() => onMonthChange(month.next)}
                    />
                    {month.month !== currentMonth ? (
                        <Button className="!font-bold" onClick={() => onMonthChange(currentMonth)}>Joriy oy</Button>
                    ) : null}
                </div>
            </div>

            <dl className="grid grid-cols-2 gap-x-6 gap-y-4 border-b border-line px-5 py-4 sm:grid-cols-3 2xl:grid-cols-6">
                <Stat
                    label="Kelgan kunlar"
                    value={`${stats.on_time + stats.late} / ${counted}`}
                    note={stats.avg_arrival ? `o‘rtacha ${stats.avg_arrival}` : null}
                />
                <Stat label="Kech kelgan" dot="bg-caution" value={stats.late} />
                <Stat label="Sababli" dot="border-2 border-positive" value={stats.excused} />
                <Stat label="Kelmagan" dot="bg-negative" value={stats.absent} />
                <Stat label="Chiqishlar" value={stats.exits} note={`${stats.entries} kirish`} />
                <Stat label="Ishlagan" value={stats.worked} note={`reja ${stats.plan}`} />
            </dl>

            <div className="px-2 py-4 sm:px-5">
                <div className="grid grid-cols-7 gap-1 sm:gap-2">
                    {WEEKDAYS_SHORT.map((weekday, index) => (
                        <div
                            key={weekday}
                            className={`pb-1 text-center text-xs font-bold uppercase tracking-wide ${index > 4 ? 'text-ink-subtle' : 'text-ink-muted'}`}
                        >
                            {weekday}
                        </div>
                    ))}
                    {Array.from({ length: offset }, (_, index) => <div key={`pad-${index}`} aria-hidden="true" />)}
                    {days.map((day) => (
                        <CalendarDay key={day.date} day={day} month={month.month} onOpen={onDayOpen} />
                    ))}
                </div>
            </div>

            <div className="flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-line px-5 py-3 text-xs font-semibold text-ink-muted">
                {LEGEND.map((status) => (
                    <span key={status} className="inline-flex items-center gap-2">
                        {STATUS[status].dot ? (
                            <Dot className={STATUS[status].dot} />
                        ) : (
                            <span aria-hidden="true" className="inline-block h-2 w-2 rounded-sm bg-line-strong" />
                        )}
                        {STATUS[status].label}
                    </span>
                ))}
                <span className="inline-flex items-center gap-1.5">
                    <LogoutOutlined aria-hidden="true" />
                    necha marta chiqqan
                </span>
            </div>
        </section>
    );
}

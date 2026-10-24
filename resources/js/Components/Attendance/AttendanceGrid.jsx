import { DownOutlined, RightOutlined } from '@ant-design/icons';
import { Empty } from 'antd';
import { Fragment, useState } from 'react';

/** Katak holati → ko‘rinish (ranglar admin temasi tokenlaridan, yorug‘/qorong‘i rejimda moslashadi). */
export const STATUS_STYLES = {
    on_time: 'bg-positive/10 text-brand-700',
    late: 'bg-caution/10 text-caution',
    excused: 'bg-surface text-brand-700 ring-1 ring-inset ring-positive',
    absent: 'bg-negative/10 text-negative',
    inactive: 'bg-surface-muted text-ink-subtle',
    pending: 'bg-transparent',
    future: 'bg-transparent',
};

export const STATUS_LABELS = {
    on_time: 'O‘z vaqtida',
    late: 'Kech kelgan',
    excused: 'Sababli',
    absent: 'Kelmagan',
    pending: 'Hali kelmagan',
    inactive: 'Ish muddatidan tashqari',
    future: '',
};

function CellContent({ cell }) {
    if (cell.status === 'inactive' || (cell.status === 'absent' && !cell.in)) {
        return <span className="text-base font-bold">—</span>;
    }

    if (!cell.in) {
        return cell.status === 'excused' ? <span className="text-xs font-bold">Sababli</span> : null;
    }

    return (
        <>
            <span className="block text-[13px] font-bold leading-4">{cell.in}</span>
            <span className="block text-xs leading-4 opacity-80">
                {cell.inside ? 'ishda' : (cell.out ?? '—')}
            </span>
        </>
    );
}

function DayCell({ cell, isToday, onClick }) {
    const clickable = cell.status !== 'future' && cell.status !== 'inactive';
    const todayRing = isToday ? 'outline outline-2 -outline-offset-2 outline-sky-500' : '';
    const pendingBorder = cell.status === 'pending' && isToday ? 'border border-dashed border-sky-400' : '';

    return (
        <button
            type="button"
            disabled={!clickable}
            onClick={onClick}
            title={STATUS_LABELS[cell.status]}
            className={`flex h-12 w-[72px] flex-col items-center justify-center rounded-lg text-center transition ${STATUS_STYLES[cell.status]} ${todayRing} ${pendingBorder} ${clickable ? 'cursor-pointer hover:brightness-95 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-600' : 'cursor-default'}`}
        >
            <CellContent cell={cell} />
        </button>
    );
}

/**
 * Xodim × ish kuni jadvali. Qatorlar bo‘lim bo‘yicha guruhlanadi (sahifa ichida), bo‘lim yig‘ib-yoyiladi.
 */
export default function AttendanceGrid({ days, rows, departmentCounts = {}, onCellClick }) {
    const [collapsed, setCollapsed] = useState({});
    const todayIndex = days.findIndex((day) => day.is_today);

    if (!rows.length) {
        return (
            <div className="py-12">
                <Empty description="Tanlangan filtr bo‘yicha xodim topilmadi" />
            </div>
        );
    }

    const groups = [];
    rows.forEach((row) => {
        const last = groups[groups.length - 1];
        if (last && last.department.id === row.department.id) {
            last.rows.push(row);
        } else {
            groups.push({ department: row.department, rows: [row] });
        }
    });

    return (
        <div className="overflow-x-auto">
            <table className="w-full border-separate border-spacing-x-1 border-spacing-y-1 text-sm">
                <thead>
                    <tr>
                        <th className="sticky left-0 z-10 min-w-[200px] bg-surface pb-2 pl-2 text-left font-semibold text-ink-muted">
                            Xodim
                        </th>
                        {days.map((day) => (
                            <th
                                key={day.date}
                                className={`min-w-[72px] rounded-lg pb-2 pt-1 text-center font-bold ${day.is_today ? 'bg-sky-500/10 text-sky-600' : 'text-ink'}`}
                            >
                                <span className="block text-[13px]">{day.label}</span>
                                <span className={`block text-xs font-medium ${day.is_today ? 'text-sky-600' : 'text-ink-muted'}`}>
                                    {day.weekday}
                                </span>
                            </th>
                        ))}
                        <th className="min-w-[96px] pb-2 pl-3 text-left font-semibold text-ink-muted">Ishladi</th>
                    </tr>
                </thead>
                <tbody>
                    {groups.map(({ department, rows: groupRows }) => {
                        const isCollapsed = Boolean(collapsed[department.id]);

                        return (
                            <Fragment key={department.id}>
                                <tr>
                                    <td colSpan={days.length + 2} className="rounded-lg bg-surface-muted p-0">
                                        <button
                                            type="button"
                                            className="flex w-full items-center gap-2 px-3 py-2 text-left font-bold text-ink"
                                            aria-expanded={!isCollapsed}
                                            onClick={() => setCollapsed((prev) => ({ ...prev, [department.id]: !prev[department.id] }))}
                                        >
                                            {isCollapsed
                                                ? <RightOutlined className="text-xs text-ink-muted" />
                                                : <DownOutlined className="text-xs text-ink-muted" />}
                                            <span>{department.name || 'Bo‘limsiz'}</span>
                                            <span className="rounded-full bg-line px-2 text-xs font-semibold text-ink-muted">
                                                {departmentCounts[department.id] ?? groupRows.length}
                                            </span>
                                        </button>
                                    </td>
                                </tr>
                                {!isCollapsed && groupRows.map((row) => (
                                    <tr key={row.id}>
                                        <td className="sticky left-0 z-10 bg-surface py-1 pl-2 pr-3">
                                            <div className="max-w-[220px] truncate font-bold text-ink">{row.name}</div>
                                            <div className="max-w-[220px] truncate text-xs text-ink-muted">{row.position || '—'}</div>
                                        </td>
                                        {row.cells.map((cell, index) => (
                                            <td key={cell.date} className="p-0">
                                                <DayCell
                                                    cell={cell}
                                                    isToday={index === todayIndex}
                                                    onClick={() => onCellClick(row, cell)}
                                                />
                                            </td>
                                        ))}
                                        <td className="whitespace-nowrap pl-3 font-bold text-ink">{row.worked}</td>
                                    </tr>
                                ))}
                            </Fragment>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

import { LoginOutlined, LogoutOutlined } from '@ant-design/icons';
import { DatePicker, Segmented, Select } from 'antd';
import dayjs from 'dayjs';
import { useMemo } from 'react';
import Pagination from '../Pagination';
import { formatDayTitle } from '../../utils/uzDate';

const TYPE_OPTIONS = [
    { value: 'all', label: 'Hammasi' },
    { value: 'entry', label: 'Kirish' },
    { value: 'exit', label: 'Chiqish' },
];

function PassType({ type }) {
    return type === 'exit' ? (
        <span className="inline-flex w-[5.5rem] shrink-0 items-center gap-1.5 rounded-full bg-surface-muted px-2.5 py-1 text-xs font-bold text-ink-muted">
            <LogoutOutlined aria-hidden="true" />
            Chiqdi
        </span>
    ) : (
        <span className="inline-flex w-[5.5rem] shrink-0 items-center gap-1.5 rounded-full bg-positive/10 px-2.5 py-1 text-xs font-bold text-brand-700">
            <LoginOutlined aria-hidden="true" />
            Kirdi
        </span>
    );
}

function formatShortDate(isoDate) {
    return dayjs(isoDate).format('DD.MM.YYYY');
}

/**
 * Xodimning kirish-chiqishlari: sana oralig‘i, tur va qurilma bo‘yicha filtr, kunlar bo‘yicha guruhlangan ro‘yxat.
 */
export default function PassesPanel({ passes, summary, devices = [], filters, monthRange, onChange }) {
    const byDate = useMemo(() => {
        const groups = new Map();
        passes.data.forEach((pass) => {
            if (!groups.has(pass.date)) {
                groups.set(pass.date, []);
            }
            groups.get(pass.date).push(pass);
        });

        return [...groups.entries()];
    }, [passes.data]);

    const isMonthRange = filters.from === monthRange.from && filters.to === monthRange.to;
    const filtered = !isMonthRange || filters.type || filters.device_id;

    return (
        <section className="table-shell">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                <div className="min-w-0">
                    <h2 className="text-lg font-extrabold text-ink">Kirish-chiqishlar</h2>
                    <p className="mt-0.5 text-sm text-ink-muted">
                        {formatShortDate(filters.from)}
                        {filters.from !== filters.to ? ` – ${formatShortDate(filters.to)}` : ''}
                        {' · '}
                        <span className="font-bold text-ink">{summary.entries}</span>
                        {' kirish · '}
                        <span className="font-bold text-ink">{summary.exits}</span>
                        {' chiqish'}
                    </p>
                </div>
            </div>

            <div className="flex flex-wrap items-center gap-3 border-b border-line bg-surface-muted/50 px-5 py-3">
                <DatePicker.RangePicker
                    allowClear={false}
                    format="DD.MM.YYYY"
                    placeholder={['Boshlanish', 'Tugash']}
                    value={[dayjs(filters.from), dayjs(filters.to)]}
                    onChange={(range) => {
                        if (range?.[0] && range?.[1]) {
                            onChange({ from: range[0].format('YYYY-MM-DD'), to: range[1].format('YYYY-MM-DD') });
                        }
                    }}
                    className="w-full sm:w-auto"
                />
                <Segmented
                    options={TYPE_OPTIONS}
                    value={filters.type ?? 'all'}
                    onChange={(value) => onChange({ type: value === 'all' ? null : value })}
                />
                <Select
                    allowClear
                    showSearch
                    optionFilterProp="label"
                    placeholder="Barcha qurilmalar"
                    value={filters.device_id ?? undefined}
                    options={devices.map((device) => ({ value: device.id, label: device.name }))}
                    onChange={(value) => onChange({ device_id: value ?? null })}
                    className="w-full sm:w-56"
                    notFoundContent="Qurilma yo‘q"
                />
                {filtered ? (
                    <button
                        type="button"
                        className="text-sm font-bold text-brand-700 transition-colors duration-150 ease-productive hover:text-brand-600"
                        onClick={() => onChange({ ...monthRange, type: null, device_id: null })}
                    >
                        Filtrni tozalash
                    </button>
                ) : null}
            </div>

            {byDate.length ? (
                <ul>
                    {byDate.map(([date, items], groupIndex) => (
                        <li key={date}>
                            <p className="border-b border-line bg-surface-muted/60 px-5 py-2 text-xs font-bold text-ink-muted">
                                {formatDayTitle(date)}
                            </p>
                            <ul>
                                {items.map((pass, index) => (
                                    <li
                                        key={pass.id}
                                        className={`flex items-center gap-4 px-5 py-2.5 ${groupIndex === byDate.length - 1 && index === items.length - 1 ? '' : 'border-b border-line'}`}
                                    >
                                        <span className="w-16 shrink-0 text-sm font-bold tabular-nums text-ink">{pass.time}</span>
                                        <PassType type={pass.type} />
                                        <span className="min-w-0 flex-1 truncate text-sm text-ink-muted">{pass.place}</span>
                                    </li>
                                ))}
                            </ul>
                        </li>
                    ))}
                </ul>
            ) : (
                <div className="px-5 py-10 text-center">
                    <p className="text-sm font-bold text-ink">Bu oraliqda kirish-chiqish yo‘q</p>
                    <p className="mt-1 text-sm text-ink-muted">Sana oralig‘ini yoki filtrni o‘zgartirib ko‘ring.</p>
                </div>
            )}

            {passes.last_page > 1 ? (
                <div className="table-footer">
                    <Pagination links={passes.links} perPage={passes.per_page} total={passes.total} />
                </div>
            ) : null}
        </section>
    );
}

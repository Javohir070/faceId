import { SearchOutlined } from '@ant-design/icons';
import { Link } from '@inertiajs/react';
import { Empty, Input, Segmented } from 'antd';
import { useMemo, useState } from 'react';
import { route } from '../../routes';
import SidePanel from '../SidePanel';
import { Photo, ReasonList, StatusBadge, TONE_TEXT } from './EmployeeBubble';

const FILTERS = [
    { value: 'all', label: 'Hammasi', match: () => true },
    { value: 'inside', label: 'Binoda', match: (employee) => employee.inside },
    { value: 'outside', label: 'Binoda emas', match: (employee) => !employee.inside },
    { value: 'excused', label: 'Sababli', match: (employee) => employee.excused },
    { value: 'unexcused', label: 'Sababsiz', match: (employee) => employee.unexcused },
];

function Stat({ label, value, tone }) {
    return (
        <div className="min-w-0 rounded-lg border border-line bg-surface-muted px-3 py-2.5">
            <p className="truncate text-xs font-semibold text-ink-muted">{label}</p>
            <p className={`mt-0.5 text-xl font-extrabold tabular-nums ${tone}`}>{value}</p>
        </div>
    );
}

function TimeLine({ employee }) {
    if (!employee.in) {
        return <span className="text-ink-muted">Bugun kelmagan</span>;
    }

    return (
        <span className="text-ink-muted">
            Kirdi <span className="font-bold tabular-nums text-ink">{employee.in}</span>
            {employee.out ? (
                <>
                    {' · '}Chiqdi <span className="font-bold tabular-nums text-ink">{employee.out}</span>
                </>
            ) : null}
        </span>
    );
}

function EmployeeRow({ employee, canOpenProfile }) {
    const content = (
        <>
            <span className={`relative shrink-0 self-start rounded-full ring-2 ring-offset-2 ring-offset-surface ${employee.inside ? 'ring-positive' : 'ring-negative'}`}>
                <Photo name={employee.name} src={employee.face_url} size="md" />
                <StatusBadge inside={employee.inside} excused={employee.excused} />
            </span>

            <span className="min-w-0 flex-1">
                <span className="block truncate text-sm font-extrabold text-ink">{employee.name}</span>
                {employee.position ? <span className="block truncate text-xs text-ink-muted">{employee.position}</span> : null}
                <span className="mt-1 block text-xs">
                    <TimeLine employee={employee} />
                </span>
                <ReasonList notes={employee.notes} className="mt-2" />
            </span>

            <span className="flex shrink-0 flex-col items-end gap-1 self-start text-xs font-bold">
                <span className={TONE_TEXT[employee.inside ? 'positive' : 'negative']}>
                    {employee.inside ? 'Binoda' : 'Binoda emas'}
                </span>
                {employee.excused ? (
                    <span className="rounded border border-caution/40 bg-caution/10 px-1.5 py-0.5 text-ink">Sababli</span>
                ) : null}
                {employee.unexcused ? <span className="rounded bg-negative/10 px-1.5 py-0.5 text-negative">Sababsiz</span> : null}
            </span>
        </>
    );

    const className = 'flex items-center gap-3.5 rounded-lg px-2 py-2.5';

    if (!canOpenProfile) {
        return <li className={className}>{content}</li>;
    }

    return (
        <li>
            <Link
                href={route('admin.employees.show', employee.id)}
                className={`${className} transition-colors duration-150 hover:bg-surface-muted`}
            >
                {content}
            </Link>
        </li>
    );
}

/**
 * Kartochka bosilganda o‘ngdan ochiladigan guruh paneli: sonlar, binoda bo‘lish ulushi va xodimlar ro‘yxati.
 * Ma’lumot sahifadagi `board` dan olinadi (alohida so‘rov yo‘q), 30 soniyalik yangilanish bilan birga yangilanadi.
 *
 * state: { isOpen, loading, panel: unit | null, close }
 */
export default function UnitPanel({ state, canOpenProfile }) {
    const [filter, setFilter] = useState('all');
    const [search, setSearch] = useState('');
    const unit = state.panel;
    const employees = unit?.employees ?? [];

    const counts = useMemo(() => ({
        all: employees.length,
        inside: employees.filter((employee) => employee.inside).length,
        outside: employees.filter((employee) => !employee.inside).length,
        excused: employees.filter((employee) => employee.excused).length,
        unexcused: employees.filter((employee) => employee.unexcused).length,
    }), [employees]);

    const rows = useMemo(() => {
        const { match } = FILTERS.find((item) => item.value === filter) ?? FILTERS[0];
        const needle = search.trim().toLowerCase();

        return employees.filter((employee) => match(employee) && (!needle || [
            employee.name,
            employee.position,
            ...employee.notes.map((note) => note.text),
        ].some((value) => String(value || '').toLowerCase().includes(needle))));
    }, [employees, filter, search]);

    const percent = counts.all > 0 ? Math.round((counts.inside / counts.all) * 100) : 0;

    return (
        <SidePanel
            state={state}
            narrow
            onClose={() => {
                setFilter('all');
                setSearch('');
            }}
            title={unit?.title ?? "Tarkibiy bo'linma"}
            subtitle={unit ? `${unit.department} · ${counts.all} ta xodim` : null}
        >
            <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                <Stat label="Binoda" value={counts.inside} tone="text-positive" />
                <Stat label="Binoda emas" value={counts.outside} tone="text-negative" />
                <Stat label="Sababli" value={counts.excused} tone="text-caution" />
                <Stat label="Sababsiz" value={counts.unexcused} tone="text-negative" />
            </div>

            <div className="mt-4">
                <div className="flex items-baseline justify-between text-xs font-semibold text-ink-muted">
                    <span>Hozir binodada</span>
                    <span className="tabular-nums">
                        <span className="font-extrabold text-ink">{counts.inside}</span> / {counts.all} · {percent}%
                    </span>
                </div>
                <div className="mt-1.5 h-2 overflow-hidden rounded-full bg-negative/15" aria-hidden="true">
                    <div className="h-full rounded-full bg-positive transition-[width] duration-300" style={{ width: `${percent}%` }} />
                </div>
            </div>

            <div className="mt-5 flex flex-col gap-2.5">
                <Input
                    allowClear
                    prefix={<SearchOutlined className="text-ink-subtle" />}
                    placeholder="Ism, lavozim yoki sabab bo‘yicha qidirish"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                />
                <div className="max-w-full overflow-x-auto pb-1">
                    <Segmented
                        size="small"
                        value={filter}
                        onChange={setFilter}
                        options={FILTERS.map((item) => ({
                            value: item.value,
                            label: (
                                <span className="whitespace-nowrap px-0.5 font-bold">
                                    {item.label} <span className="tabular-nums text-ink-muted">{counts[item.value]}</span>
                                </span>
                            ),
                        }))}
                    />
                </div>
            </div>

            {rows.length > 0 ? (
                <ul className="mt-3 divide-y divide-line">
                    {rows.map((employee) => (
                        <EmployeeRow key={employee.id} employee={employee} canOpenProfile={canOpenProfile} />
                    ))}
                </ul>
            ) : (
                <Empty className="py-10" description="Bu filtrda xodim yo‘q" />
            )}
        </SidePanel>
    );
}

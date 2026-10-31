import { router } from '@inertiajs/react';
import { Button, Input, Select } from 'antd';
import { DownloadOutlined } from '@ant-design/icons';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Pagination from '../../../Components/Pagination';
import TableShell from '../../../Components/TableShell';
import { route } from '../../../routes';
import { formatNumber } from '../../../utils/liveFormat';

function dayLabel(date) {
    const [, month, day] = date.split('-');

    return `${day}.${month}`;
}

function cleanQuery(filters) {
    return Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value !== null && value !== undefined && value !== ''),
    );
}

function Total({ label, value }) {
    return (
        <div className="rounded-lg border border-line bg-surface-muted px-4 py-3">
            <p className="text-xs font-bold uppercase tracking-wide text-ink-subtle">{label}</p>
            <p className="mt-1 text-xl font-extrabold text-ink tabular-nums">{formatNumber(value)}</p>
        </div>
    );
}

function Filter({ label, children }) {
    return (
        <label className="block">
            <span className="mb-1 block text-xs font-bold uppercase tracking-wide text-ink-subtle">{label}</span>
            {children}
        </label>
    );
}

export default function Entrants({ filters, days = [], dailyTotals = {}, entrants, groups = [], maxDays = 31 }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const rows = entrants?.data ?? [];
    const firstNumber = entrants?.from ?? 1;

    const visit = (changes) => {
        router.get(
            route('admin.live.entrants.index'),
            cleanQuery({ ...filters, ...changes }),
            { preserveScroll: true, preserveState: true },
        );
    };

    const exportHref = () => {
        const qs = new URLSearchParams(cleanQuery(filters)).toString();

        return qs ? `${route('admin.live.entrants.export')}?${qs}` : route('admin.live.entrants.export');
    };

    return (
        <AdminLayout title="Kirganlar ro‘yxati">
            <PageHeader
                title="Kirganlar ro‘yxati"
                subtitle={`Tanlangan kunlarda kamida bir marta kirganlar (tadbir mehmonlari ham). Kun ustunida — o‘sha kungi birinchi kirish vaqti. Ko‘pi bilan ${maxDays} kun.`}
                actions={(
                    <Button size="large" icon={<DownloadOutlined />} href={exportHref()} className="!font-bold">
                        Excel
                    </Button>
                )}
            />

            <div className="card mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Filter label="Boshlanish">
                    <Input
                        size="large"
                        type="date"
                        value={filters.from}
                        max={filters.to}
                        onChange={(event) => event.target.value && visit({ from: event.target.value })}
                    />
                </Filter>
                <Filter label="Tugash">
                    <Input
                        size="large"
                        type="date"
                        value={filters.to}
                        min={filters.from}
                        onChange={(event) => event.target.value && visit({ to: event.target.value })}
                    />
                </Filter>
                <Filter label="Guruh">
                    <Select
                        size="large"
                        className="w-full"
                        allowClear
                        placeholder="Hammasi"
                        value={filters.group_id ?? undefined}
                        options={groups.map((group) => ({ value: group.id, label: group.name }))}
                        onChange={(value) => visit({ group_id: value ?? null })}
                    />
                </Filter>
                <Filter label="Qidirish">
                    <Input.Search
                        size="large"
                        allowClear
                        placeholder="F.I.O yoki karta"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        onSearch={(value) => visit({ search: value.trim() || null })}
                    />
                </Filter>
            </div>

            <div className="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Total label="Jami (takrorlanmas)" value={entrants?.total ?? 0} />
                {days.map((day) => (
                    <Total key={day} label={dayLabel(day)} value={dailyTotals[day] ?? 0} />
                ))}
            </div>

            <TableShell
                empty={rows.length === 0}
                emptyState={(
                    <p className="py-10 text-center text-sm font-semibold text-ink-muted">
                        Bu kunlarda kirganlar topilmadi.
                    </p>
                )}
                footer={(
                    <Pagination links={entrants?.links} total={entrants?.total} perPage={entrants?.per_page} />
                )}
            >
                <table className="data-table">
                    <thead>
                        <tr>
                            <th className="text-right">№</th>
                            <th>F.I.O</th>
                            <th>Karta</th>
                            <th>Guruh</th>
                            <th>Bo‘lim</th>
                            {days.map((day) => (
                                <th key={day} className="text-center">{dayLabel(day)}</th>
                            ))}
                            <th className="text-right">Kunlar</th>
                            <th className="text-right">Kirishlar</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row, index) => (
                            <tr key={row.id}>
                                <td className="text-right font-medium text-ink-subtle tabular-nums">{firstNumber + index}</td>
                                <td className="font-bold text-ink">{row.name}</td>
                                <td className="tabular-nums">{row.card ?? '—'}</td>
                                <td>{row.group_name ?? '—'}</td>
                                <td>{row.department_name ?? '—'}</td>
                                {days.map((day) => (
                                    <td key={day} className="text-center tabular-nums">
                                        {row.days[day]
                                            ? <span className="font-semibold text-ink">{row.days[day]}</span>
                                            : <span className="text-ink-subtle">—</span>}
                                    </td>
                                ))}
                                <td className="text-right font-bold tabular-nums">{row.days_count}</td>
                                <td className="text-right tabular-nums">{formatNumber(row.passes)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </TableShell>
        </AdminLayout>
    );
}

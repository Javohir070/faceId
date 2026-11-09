import { Link, router, usePage } from '@inertiajs/react';
import { Drawer, Input, Spin } from 'antd';
import { useMemo, useState } from 'react';
import { route } from '../routes';
import StatusTag from './StatusTag';

const validityTags = {
    expiring: { tone: 'warning', label: 'Tugashiga yaqin' },
    expired: { tone: 'danger', label: 'Muddati tugagan' },
    upcoming: { tone: 'info', label: 'Hali boshlanmagan' },
};

function employeesHref(filter = {}) {
    const query = new URLSearchParams();

    Object.entries(filter).forEach(([key, ids]) => {
        (ids || []).forEach((id) => query.append(`${key}[]`, String(id)));
    });

    return `${route('admin.employees.index')}?${query.toString()}`;
}

/**
 * Bo‘lim yoki guruh xodimlari paneli. Ma’lumot "members" propidan qisman qayta yuklash bilan keladi:
 * sahifa butunlay yangilanmaydi.
 */
export function useMembersPanel() {
    const members = usePage().props.members ?? null;
    const [requested, setRequested] = useState(null);
    const [loading, setLoading] = useState(false);

    const open = (key) => {
        setRequested(key);
        setLoading(true);
        router.reload({
            only: ['members'],
            data: { members: key },
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setLoading(false),
        });
    };

    const close = () => setRequested(null);

    return {
        isOpen: requested !== null,
        loading: loading || (members?.key ?? null) !== requested,
        panel: members?.key === requested ? members : null,
        open,
        close,
    };
}

export default function MembersPanel({ state }) {
    const [search, setSearch] = useState('');
    const panel = state.panel;

    const rows = useMemo(() => {
        const needle = search.trim().toLowerCase();
        const employees = panel?.employees ?? [];

        if (!needle) {
            return employees;
        }

        return employees.filter((employee) => [employee.name, employee.position, employee.group]
            .some((value) => String(value || '').toLowerCase().includes(needle)));
    }, [panel, search]);

    return (
        <Drawer
            open={state.isOpen}
            onClose={() => {
                setSearch('');
                state.close();
            }}
            placement="right"
            size={460}
            title={panel ? (
                <div className="min-w-0">
                    <p className="truncate text-base font-extrabold text-ink">{panel.title}</p>
                    <p className="text-xs font-semibold text-ink-muted">
                        {panel.subtitle ? `${panel.subtitle} · ` : ''}
                        {panel.total}
                        {' ta xodim'}
                    </p>
                </div>
            ) : 'Xodimlar'}
            footer={panel && panel.total > 0 ? (
                <div className="flex items-center justify-between gap-3 text-sm">
                    <span className="text-ink-muted">
                        {panel.total > panel.employees.length
                            ? `Birinchi ${panel.employees.length} tasi ko‘rsatildi`
                            : `${panel.total} ta xodim`}
                    </span>
                    <Link href={employeesHref(panel.filter)} className="font-bold text-brand-700 hover:text-brand-600">
                        Xodimlar sahifasida ochish
                    </Link>
                </div>
            ) : null}
        >
            {state.loading || !panel ? (
                <div className="flex justify-center py-16">
                    <Spin />
                </div>
            ) : (
                <>
                    {panel.total > 0 ? (
                        <Input
                            allowClear
                            placeholder="Ism, lavozim yoki guruh"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="mb-3"
                        />
                    ) : null}

                    {rows.length === 0 ? (
                        <p className="py-10 text-center text-sm text-ink-muted">
                            {panel.total === 0 ? 'Bu yerda hali xodim yo‘q.' : 'Hech narsa topilmadi.'}
                        </p>
                    ) : (
                        <ul className="divide-y divide-line">
                            {rows.map((employee) => (
                                <li key={employee.id} className="flex items-start justify-between gap-3 py-2.5">
                                    <div className="min-w-0">
                                        <Link
                                            href={route('admin.employees.show', employee.id)}
                                            className="block truncate text-sm font-bold text-ink hover:text-brand-700"
                                        >
                                            {employee.name}
                                        </Link>
                                        <p className="truncate text-xs font-medium text-ink-muted">
                                            {[employee.position, employee.group].filter(Boolean).join(' · ') || '—'}
                                        </p>
                                    </div>
                                    {validityTags[employee.validity] ? (
                                        <StatusTag tone={validityTags[employee.validity].tone} className="shrink-0">
                                            {validityTags[employee.validity].label}
                                        </StatusTag>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    )}
                </>
            )}
        </Drawer>
    );
}

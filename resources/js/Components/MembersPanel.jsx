import { Link } from '@inertiajs/react';
import { Input } from 'antd';
import { useMemo, useState } from 'react';
import { route } from '../routes';
import EmployeeCard, { EMPLOYEE_CARD_GRID } from './EmployeeCard';
import SidePanel, { usePartialPanel } from './SidePanel';

function employeesHref(filter = {}) {
    const query = new URLSearchParams();

    Object.entries(filter).forEach(([key, ids]) => {
        (ids || []).forEach((id) => query.append(`${key}[]`, String(id)));
    });

    return `${route('admin.employees.index')}?${query.toString()}`;
}

/** Bo‘lim yoki guruh xodimlari paneli ("members" propi, ?members=group:5 / department:2). */
export function useMembersPanel() {
    return usePartialPanel('members');
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

    // Server xodimlarni guruhlar tartibida yuboradi: ketma-ket bir xil guruhdagilar bitta bo‘lim.
    const sections = useMemo(() => rows.reduce((list, employee) => {
        const name = employee.group || 'Guruhsiz';
        const last = list[list.length - 1];

        if (last && last.name === name) {
            last.employees.push(employee);
        } else {
            list.push({ name, employees: [employee] });
        }

        return list;
    }, []), [rows]);

    return (
        <SidePanel
            state={state}
            onClose={() => setSearch('')}
            title={panel?.title ?? 'Xodimlar'}
            subtitle={panel ? `${panel.subtitle ? `${panel.subtitle} · ` : ''}${panel.total} ta xodim` : null}
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
            {panel ? (
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
                        <div className="-mx-1 space-y-4">
                            {sections.map((section) => (
                                <section key={section.name}>
                                    <header className="sticky top-0 z-10 flex items-center justify-between gap-3 bg-surface px-1 py-1.5">
                                        <h3 className="min-w-0 truncate text-xs font-extrabold uppercase tracking-wide text-ink-muted">
                                            {section.name}
                                        </h3>
                                        <span className="shrink-0 rounded-full bg-surface-muted px-2 py-0.5 text-xs font-bold tabular-nums text-ink">
                                            {section.employees.length}
                                        </span>
                                    </header>
                                    <ul className={`mt-1 px-1 ${EMPLOYEE_CARD_GRID}`}>
                                        {section.employees.map((employee) => (
                                            <li key={employee.id}>
                                                <EmployeeCard employee={employee} />
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            ))}
                        </div>
                    )}
                </>
            ) : null}
        </SidePanel>
    );
}

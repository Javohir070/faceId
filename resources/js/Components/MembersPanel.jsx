import { Link, router, usePage } from '@inertiajs/react';
import { ArrowRightOutlined } from '@ant-design/icons';
import { Drawer, Input, Spin } from 'antd';
import { useMemo, useState } from 'react';
import { route } from '../routes';
import EmployeeCard, { EMPLOYEE_CARD_GRID } from './EmployeeCard';

/** Kompyuterda ekranning yarmi, telefonda butun ekran. */
function panelWidth() {
    if (typeof window === 'undefined') {
        return 720;
    }

    return window.innerWidth < 768 ? window.innerWidth : Math.max(Math.round(window.innerWidth / 2), 480);
}

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

    const close = () => {
        setSearch('');
        state.close();
    };

    return (
        <Drawer
            open={state.isOpen}
            onClose={close}
            placement="right"
            size={panelWidth()}
            rootClassName="members-panel"
            // Kompyuterda panel chap chetidagi aylana tugma bilan yopiladi (yarmi tashqarida), telefonda X bilan.
            drawerRender={(node) => (
                <div className="relative h-full">
                    {node}
                    <button
                        type="button"
                        aria-label="Panelni yopish"
                        onClick={close}
                        // Drawer qatlamlari pointer-events: none — tugma bosiladigan bo‘lishi uchun qaytariladi.
                        className="pointer-events-auto absolute -left-6 top-1/2 z-20 hidden h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-brand-600 text-lg text-white shadow-lg ring-4 ring-surface transition-colors duration-150 ease-productive hover:bg-brand-700 focus-visible:outline-none focus-visible:ring-brand-200 sm:flex"
                    >
                        <ArrowRightOutlined aria-hidden="true" />
                    </button>
                </div>
            )}
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
            )}
        </Drawer>
    );
}

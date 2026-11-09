import { Link, router, usePage } from '@inertiajs/react';
import { Drawer, Input, Spin } from 'antd';
import { useMemo, useState } from 'react';
import { route } from '../routes';
import { initials } from './InitialsAvatar';
import StatusTag from './StatusTag';

const validityTags = {
    expiring: { tone: 'warning', label: 'Tugashiga yaqin' },
    expired: { tone: 'danger', label: 'Muddati tugagan' },
    upcoming: { tone: 'info', label: 'Hali boshlanmagan' },
};

/** Kartochka rasmi: yuz bo‘lsa — kichik nusxasi (ko‘ringanda yuklanadi), bo‘lmasa yoki ochilmasa — bosh harflar. */
function EmployeePhoto({ name, src }) {
    const [failed, setFailed] = useState(false);

    if (!src || failed) {
        return (
            <span
                aria-hidden="true"
                className="flex aspect-square w-full items-center justify-center rounded-lg bg-surface-muted text-3xl font-extrabold tracking-wide text-ink-subtle"
            >
                {initials(name)}
            </span>
        );
    }

    return (
        <img
            src={src}
            alt=""
            loading="lazy"
            onError={() => setFailed(true)}
            className="aspect-square w-full rounded-lg bg-surface-muted object-cover"
        />
    );
}

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

    return (
        <Drawer
            open={state.isOpen}
            onClose={() => {
                setSearch('');
                state.close();
            }}
            placement="right"
            size={panelWidth()}
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
                                    {/* Kartochka kamida 150px: ustunlar soni panel kengligiga qarab o‘zi tanlanadi. */}
                                    <ul className="mt-1 grid grid-cols-[repeat(auto-fill,minmax(150px,1fr))] gap-3 px-1">
                                        {section.employees.map((employee) => (
                                            <li key={employee.id}>
                                                <Link
                                                    href={route('admin.employees.show', employee.id)}
                                                    className="flex h-full flex-col rounded-xl border border-line bg-surface p-2 transition-colors duration-150 ease-productive hover:border-brand-600/40 hover:bg-surface-muted"
                                                >
                                                    <EmployeePhoto name={employee.name} src={employee.face_url} />
                                                    <span className="mt-2 line-clamp-2 text-sm font-bold leading-snug text-ink">
                                                        {employee.name}
                                                    </span>
                                                    <span className="mt-0.5 truncate text-xs font-medium text-ink-muted">
                                                        {employee.position || 'Lavozim kiritilmagan'}
                                                    </span>
                                                    {validityTags[employee.validity] ? (
                                                        <span className="mt-1.5">
                                                            <StatusTag tone={validityTags[employee.validity].tone}>
                                                                {validityTags[employee.validity].label}
                                                            </StatusTag>
                                                        </span>
                                                    ) : null}
                                                </Link>
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

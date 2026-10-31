import { Link, router } from '@inertiajs/react';
import {
    ApartmentOutlined,
    ClusterOutlined,
    CompressOutlined,
    DeleteOutlined,
    EditOutlined,
    ExpandOutlined,
    EyeOutlined,
    PlusOutlined,
    RightOutlined,
    SearchOutlined,
    TeamOutlined,
    UserOutlined,
} from '@ant-design/icons';
import { App as AntApp, Button, Checkbox, Input, Select, Tooltip } from 'antd';
import { useEffect, useMemo, useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import PageHeader, { EmptyState } from '../../../Components/PageHeader';
import InitialsAvatar from '../../../Components/InitialsAvatar';
import SummaryStat from '../../../Components/SummaryStat';

// Chuqurlik bo‘yicha chekinish: kompyuterda pastki guruh ota guruh avatari ostidan boshlanadi
// (ochish tugmasi 1.5rem + oraliq 0.625rem), telefonda 1rem dan. 5-darajadan keyin o‘smaydi.
const INDENT = [
    '',
    'pl-4 sm:pl-[2.125rem]',
    'pl-8 sm:pl-[4.25rem]',
    'pl-12 sm:pl-[6.375rem]',
    'pl-16 sm:pl-[8.5rem]',
    'pl-20 sm:pl-[10.625rem]',
];

function countGroups(nodes = []) {
    return nodes.reduce((total, node) => {
        if (node.type === 'group') {
            return total + 1 + countGroups(node.children || []);
        }

        return total + countGroups(node.children || []);
    }, 0);
}

/** Guruhlar va ularning pastki guruhlaridagi xodimlar yig‘indisi. */
function sumEmployees(nodes = []) {
    return nodes.reduce(
        (total, node) => total + (node.employees_count || 0) + sumEmployees(node.children || []),
        0,
    );
}

function collectGroupIds(nodes = []) {
    return nodes.flatMap((node) => [node.id, ...collectGroupIds(node.children || [])]);
}

function collectExpandableKeys(nodes = []) {
    return nodes.flatMap((node) => {
        if (node.type === 'department' || node.children?.length) {
            return [node.key, ...collectExpandableKeys(node.children || [])];
        }

        return [];
    });
}

/** Nomi mos kelgan tugun butun shoxi bilan qoladi, aks holda faqat mos kelgan avlodlari. */
function filterTree(nodes, needle) {
    if (!needle) {
        return nodes;
    }

    return nodes
        .map((node) => {
            if (node.title.toLowerCase().includes(needle)) {
                return node;
            }

            const children = filterTree(node.children || [], needle);

            return children.length ? { ...node, children } : null;
        })
        .filter(Boolean);
}

function Highlight({ text, needle }) {
    const index = needle ? text.toLowerCase().indexOf(needle) : -1;

    if (index === -1) {
        return text;
    }

    return (
        <>
            {text.slice(0, index)}
            <mark className="rounded-sm bg-brand-100 px-0.5 text-ink">
                {text.slice(index, index + needle.length)}
            </mark>
            {text.slice(index + needle.length)}
        </>
    );
}

function ToggleIcon({ open }) {
    return (
        <RightOutlined
            aria-hidden="true"
            className={`text-xs transition-transform duration-150 ease-productive ${open ? 'rotate-90' : ''}`}
        />
    );
}

function EmployeeCount({ count }) {
    return (
        <span
            className={`inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold tabular-nums ${count ? 'bg-surface-muted text-ink' : 'text-ink-subtle'}`}
        >
            <UserOutlined aria-hidden="true" />
            {count}
            <span className="hidden sm:inline">xodim</span>
        </span>
    );
}

function GroupRow({ node, depth, ctx }) {
    const children = node.children || [];
    const hasChildren = children.length > 0;
    const open = ctx.isOpen(node.key);
    const checked = ctx.selected.has(node.id);

    return (
        <li>
            <div
                className={`group/row flex items-center gap-3 border-t border-line px-4 py-2.5 transition-colors duration-150 ease-productive sm:px-5 ${checked ? 'bg-brand-50/70' : 'hover:bg-surface-muted'}`}
            >
                <Checkbox
                    checked={checked}
                    onChange={(e) => ctx.select([node.id], e.target.checked)}
                    aria-label={`«${node.title}» guruhini tanlash`}
                />
                <div className={`flex min-w-0 flex-1 items-center gap-2.5 ${INDENT[Math.min(depth, INDENT.length - 1)]}`}>
                    {hasChildren ? (
                        <button
                            type="button"
                            className="flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-ink-muted transition-colors duration-150 ease-productive hover:bg-line/60 hover:text-ink"
                            aria-expanded={open}
                            aria-label={open ? `«${node.title}» pastki guruhlarini yopish` : `«${node.title}» pastki guruhlarini ochish`}
                            onClick={() => ctx.toggle(node.key)}
                        >
                            <ToggleIcon open={open} />
                        </button>
                    ) : (
                        <span aria-hidden="true" className="w-6 shrink-0" />
                    )}
                    <InitialsAvatar name={node.title} tone={depth === 0 ? 'brand' : 'neutral'} size={depth === 0 ? 'md' : 'sm'} />
                    <div className="min-w-0">
                        <Link
                            href={route('admin.groups.show', node.id)}
                            className="block truncate text-sm font-bold text-ink transition-colors duration-150 ease-productive hover:text-brand-700 dark:hover:text-brand-400"
                        >
                            <Highlight text={node.title} needle={ctx.needle} />
                        </Link>
                        {hasChildren ? (
                            <p className="truncate text-xs font-semibold text-ink-muted">
                                {children.length}
                                {' ta pastki guruh · jami '}
                                {node.employees_count + sumEmployees(children)}
                                {' xodim'}
                            </p>
                        ) : null}
                    </div>
                </div>
                <EmployeeCount count={node.employees_count} />
                {/* Telefonda yashirin: nom bosilsa guruh sahifasi ochiladi, tahrirlash o‘sha yerda. */}
                <div className="hidden shrink-0 items-center gap-1 opacity-0 transition-opacity duration-150 ease-productive group-hover/row:opacity-100 group-focus-within/row:opacity-100 sm:flex">
                    <Tooltip title="Ko‘rish">
                        <Link
                            href={route('admin.groups.show', node.id)}
                            className="table-action-view"
                            aria-label={`«${node.title}» guruhini ko‘rish`}
                        >
                            <EyeOutlined aria-hidden="true" />
                        </Link>
                    </Tooltip>
                    <Tooltip title="Tahrirlash">
                        <Link
                            href={route('admin.groups.edit', node.id)}
                            className="table-action-edit"
                            aria-label={`«${node.title}» guruhini tahrirlash`}
                        >
                            <EditOutlined aria-hidden="true" />
                        </Link>
                    </Tooltip>
                </div>
            </div>
            {hasChildren && open ? (
                <ul>
                    {children.map((child) => (
                        <GroupRow key={child.key} node={child} depth={depth + 1} ctx={ctx} />
                    ))}
                </ul>
            ) : null}
        </li>
    );
}

function DepartmentSection({ node, ctx }) {
    const children = node.children || [];
    const open = ctx.isOpen(node.key);
    const groupIds = useMemo(() => collectGroupIds(children), [children]);
    const selectedCount = groupIds.filter((id) => ctx.selected.has(id)).length;
    const allSelected = groupIds.length > 0 && selectedCount === groupIds.length;

    return (
        <section className="overflow-hidden rounded-xl border border-line bg-surface shadow-ambient">
            <div className="flex items-center gap-3 px-4 py-3 sm:px-5">
                <Checkbox
                    checked={allSelected}
                    indeterminate={selectedCount > 0 && !allSelected}
                    disabled={groupIds.length === 0}
                    onChange={(e) => ctx.select(groupIds, e.target.checked)}
                    aria-label={`«${node.title}» bo‘limidagi barcha guruhlarni tanlash`}
                />
                <button
                    type="button"
                    className="flex min-w-0 flex-1 items-center gap-3 rounded-lg text-left"
                    aria-expanded={open}
                    onClick={() => ctx.toggle(node.key)}
                >
                    <span
                        aria-hidden="true"
                        className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-base text-white dark:bg-brand-500 dark:text-brand-950"
                    >
                        <ApartmentOutlined />
                    </span>
                    <span className="min-w-0 flex-1">
                        <span className="block truncate text-base font-extrabold text-ink">
                            <Highlight text={node.title} needle={ctx.needle} />
                        </span>
                        <span className="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs font-semibold text-ink-muted">
                            <span>
                                {countGroups(children)}
                                {' guruh'}
                            </span>
                            <span aria-hidden="true">·</span>
                            <span>
                                {node.employees_count}
                                {' xodim'}
                            </span>
                            {node.unassigned_count > 0 ? (
                                <>
                                    <span aria-hidden="true">·</span>
                                    <span className="inline-flex items-center gap-1.5">
                                        <span aria-hidden="true" className="h-1.5 w-1.5 rounded-full bg-caution" />
                                        {node.unassigned_count}
                                        {' tasi guruhsiz'}
                                    </span>
                                </>
                            ) : null}
                        </span>
                    </span>
                    <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-ink-muted">
                        <ToggleIcon open={open} />
                    </span>
                </button>
                <Tooltip title="Shu bo‘limga guruh qo‘shish">
                    <Link
                        href={`${route('admin.groups.create')}?department_id=${node.id}`}
                        className="table-action-view hidden sm:inline-flex"
                        aria-label={`«${node.title}» bo‘limiga guruh qo‘shish`}
                    >
                        <PlusOutlined aria-hidden="true" />
                    </Link>
                </Tooltip>
            </div>

            {open ? (
                children.length ? (
                    <ul>
                        {children.map((child) => (
                            <GroupRow key={child.key} node={child} depth={0} ctx={ctx} />
                        ))}
                    </ul>
                ) : (
                    <div className="border-t border-line px-5 py-6 text-sm text-ink-muted">
                        Bu bo‘limda hali guruh yo‘q.
                        {' '}
                        <Link
                            href={`${route('admin.groups.create')}?department_id=${node.id}`}
                            className="font-bold text-brand-700 hover:text-brand-600"
                        >
                            Birinchisini qo‘shing
                        </Link>
                    </div>
                )
            ) : null}
        </section>
    );
}

export default function Index({ tree = [], filters = {}, departments = [] }) {
    const { modal } = AntApp.useApp();
    const [search, setSearch] = useState('');
    const [selectedIds, setSelectedIds] = useState([]);
    const [collapsed, setCollapsed] = useState(() => new Set());

    const needle = search.trim().toLowerCase();
    const filteredTree = useMemo(() => filterTree(tree, needle), [tree, needle]);
    const expandableKeys = useMemo(() => collectExpandableKeys(tree), [tree]);
    const totals = useMemo(() => ({
        departments: tree.length,
        groups: countGroups(tree),
        employees: tree.reduce((total, department) => total + sumEmployees(department.children || []), 0),
    }), [tree]);
    const selected = useMemo(() => new Set(selectedIds), [selectedIds]);

    useEffect(() => {
        setSelectedIds([]);
    }, [tree]);

    const ctx = {
        needle,
        selected,
        // Qidiruv paytida hamma narsa ochiq — topilgan guruh yashirinib qolmasin.
        isOpen: (key) => Boolean(needle) || !collapsed.has(key),
        toggle: (key) => setCollapsed((current) => {
            const next = new Set(current);

            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }

            return next;
        }),
        select: (ids, checked) => setSelectedIds((current) => (checked
            ? [...new Set([...current, ...ids])]
            : current.filter((id) => !ids.includes(id)))),
    };

    const updateFilter = (departmentId) => {
        router.get(route('admin.groups.index'), {
            department_id: departmentId || undefined,
        }, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const deleteSelected = () => {
        if (!selectedIds.length) {
            return;
        }

        modal.confirm({
            title: 'Tanlangan guruhlarni o‘chirish',
            content: `${selectedIds.length} ta guruh o‘chiriladi. Ichida pastki guruhlari borlar o‘chirilmaydi.`,
            okText: 'Ha, o‘chirish',
            cancelText: 'Bekor qilish',
            okButtonProps: { danger: true },
            onOk: () => new Promise((resolve, reject) => {
                router.post(route('admin.groups.bulk-destroy'), {
                    group_ids: selectedIds,
                }, {
                    preserveScroll: true,
                    onSuccess: () => {
                        setSelectedIds([]);
                        resolve();
                    },
                    onError: reject,
                });
            }),
        });
    };

    return (
        <AdminLayout title="Guruhlar">
            <PageHeader
                title="Guruhlar"
                subtitle="Bo‘limlar ichidagi guruhlar va ulardagi xodimlar."
                createHref={route('admin.groups.create')}
                createLabel="Guruh qo‘shish"
            />

            {tree.length > 0 ? (
                <div className="mb-4 grid grid-cols-3 gap-3 sm:gap-4">
                    <SummaryStat icon={<ApartmentOutlined />} label="Bo‘limlar" value={totals.departments} />
                    <SummaryStat icon={<ClusterOutlined />} label="Guruhlar" value={totals.groups} />
                    <SummaryStat icon={<TeamOutlined />} label="Guruhlardagi xodimlar" value={totals.employees} />
                </div>
            ) : null}

            <div className="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-line bg-surface p-3 shadow-ambient sm:p-4">
                <Input
                    allowClear
                    size="large"
                    className="min-w-[200px] flex-1 sm:max-w-xs"
                    placeholder="Guruh yoki bo‘lim qidirish…"
                    prefix={<SearchOutlined className="text-ink-muted" />}
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                />
                <Select
                    allowClear
                    showSearch
                    size="large"
                    className="w-full min-w-[200px] sm:w-64"
                    placeholder="Barcha bo‘limlar"
                    optionFilterProp="label"
                    value={filters.department_id}
                    options={departments.map((department) => ({
                        value: department.id,
                        label: department.name,
                    }))}
                    onChange={(value) => updateFilter(value ?? null)}
                />
                <div className="ml-auto flex flex-wrap items-center gap-2">
                    <Button
                        size="large"
                        className="!rounded-lg !font-bold"
                        icon={<ExpandOutlined />}
                        onClick={() => setCollapsed(new Set())}
                        disabled={Boolean(needle) || collapsed.size === 0}
                    >
                        Ochish
                    </Button>
                    <Button
                        size="large"
                        className="!rounded-lg !font-bold"
                        icon={<CompressOutlined />}
                        onClick={() => setCollapsed(new Set(expandableKeys))}
                        disabled={Boolean(needle) || collapsed.size === expandableKeys.length}
                    >
                        Yopish
                    </Button>
                </div>
            </div>

            {selectedIds.length > 0 ? (
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand-600/20 bg-brand-50 px-4 py-3 dark:border-brand-400/20 dark:bg-brand-950/40">
                    <p className="text-sm font-bold text-ink">
                        {selectedIds.length}
                        {' ta guruh tanlandi'}
                    </p>
                    <div className="flex items-center gap-2">
                        <Button className="!rounded-lg !font-bold" onClick={() => setSelectedIds([])}>
                            Bekor qilish
                        </Button>
                        <Button
                            danger
                            type="primary"
                            className="!rounded-lg !font-bold"
                            icon={<DeleteOutlined />}
                            onClick={deleteSelected}
                        >
                            O‘chirish
                        </Button>
                    </div>
                </div>
            ) : null}

            {filteredTree.length > 0 ? (
                <div className="space-y-4">
                    {filteredTree.map((department) => (
                        <DepartmentSection key={department.key} node={department} ctx={ctx} />
                    ))}
                </div>
            ) : (
                <div className="table-shell">
                    {needle ? (
                        <div className="px-4 py-12 text-center">
                            <p className="text-base font-extrabold text-ink">Topilmadi</p>
                            <p className="mt-2 text-sm text-ink-muted">
                                «
                                {search.trim()}
                                » bo‘yicha guruh yoki bo‘lim yo‘q. Qidiruvni o‘zgartiring.
                            </p>
                            <Button className="mt-4 !rounded-lg !font-bold" onClick={() => setSearch('')}>
                                Qidiruvni tozalash
                            </Button>
                        </div>
                    ) : (
                        <EmptyState
                            title="Hali guruh yo‘q"
                            description="Birinchi guruhni qo‘shing — u tanlangan bo‘lim ostida chiqadi."
                            createHref={route('admin.groups.create')}
                            createLabel="Guruh qo‘shish"
                        />
                    )}
                </div>
            )}
        </AdminLayout>
    );
}

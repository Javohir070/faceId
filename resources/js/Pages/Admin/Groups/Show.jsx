import { Link } from '@inertiajs/react';
import {
    ApartmentOutlined,
    ArrowRightOutlined,
    CalendarOutlined,
    ClusterOutlined,
    PlusOutlined,
    RightOutlined,
    TeamOutlined,
} from '@ant-design/icons';
import { Fragment } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import { BackLink } from '../../../Components/PageHeader';
import { ShowHeader } from '../../../Components/ShowPage';
import InitialsAvatar from '../../../Components/InitialsAvatar';
import SummaryStat from '../../../Components/SummaryStat';

const linkClass = 'font-semibold text-ink-muted transition-colors duration-150 ease-productive hover:text-brand-700 dark:hover:text-brand-400';

/** Bo‘lim › yuqori guruhlar › shu guruh. */
function GroupPath({ group }) {
    const separator = <RightOutlined aria-hidden="true" className="text-[10px] text-ink-subtle" />;

    return (
        <span className="flex flex-wrap items-center gap-x-2 gap-y-1">
            <span className="inline-flex items-center gap-1.5 font-semibold text-ink-muted">
                <ApartmentOutlined aria-hidden="true" />
                {group.department?.name || '—'}
            </span>
            {group.path.map((item) => (
                <Fragment key={item.id}>
                    {separator}
                    <Link href={route('admin.groups.show', item.id)} className={linkClass}>
                        {item.name}
                    </Link>
                </Fragment>
            ))}
            {separator}
            <span className="font-bold text-ink">{group.name}</span>
        </span>
    );
}

function PanelHeader({ title, subtitle, action }) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
            <div className="min-w-0">
                <h2 className="text-lg font-extrabold text-ink">{title}</h2>
                {subtitle ? <p className="mt-0.5 text-sm text-ink-muted">{subtitle}</p> : null}
            </div>
            {action}
        </div>
    );
}

function EmployeesPanel({ group }) {
    const { employees, employees_count: total } = group;
    const subtitle = total > employees.length
        ? `${total} xodimdan birinchi ${employees.length} tasi ko‘rsatilgan`
        : 'Shu guruhga biriktirilgan xodimlar';

    return (
        <section className="table-shell">
            <PanelHeader
                title="Xodimlar"
                subtitle={subtitle}
                action={total > 0 ? (
                    <Link
                        href={`${route('admin.employees.index')}?group_ids[]=${group.id}`}
                        className="inline-flex items-center gap-1.5 text-sm font-bold text-brand-700 transition-colors duration-150 ease-productive hover:text-brand-600"
                    >
                        Xodimlar sahifasida ochish
                        <ArrowRightOutlined aria-hidden="true" />
                    </Link>
                ) : null}
            />
            {employees.length ? (
                <ul>
                    {employees.map((employee) => (
                        <li key={employee.id} className="border-t border-line first:border-t-0">
                            <Link
                                href={route('admin.employees.show', employee.id)}
                                className="flex items-center gap-3 px-5 py-3 transition-colors duration-150 ease-productive hover:bg-surface-muted"
                            >
                                <InitialsAvatar name={employee.name} tone="neutral" round />
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-sm font-bold text-ink">{employee.name}</span>
                                    <span className="block truncate text-xs text-ink-muted">
                                        {employee.position || 'Lavozim ko‘rsatilmagan'}
                                    </span>
                                </span>
                                <span className="shrink-0 text-xs font-semibold tabular-nums text-ink-muted">
                                    {employee.card}
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            ) : (
                <div className="px-5 py-10 text-center">
                    <p className="text-sm font-bold text-ink">Bu guruhda hali xodim yo‘q</p>
                    <p className="mx-auto mt-1 max-w-sm text-sm text-ink-muted">
                        Xodimni tahrirlab, «Guruh» maydonida shu guruhni tanlang.
                    </p>
                </div>
            )}
        </section>
    );
}

function SubgroupsPanel({ group }) {
    const createHref = `${route('admin.groups.create')}?parent_id=${group.id}`;

    return (
        <section className="table-shell self-start">
            <PanelHeader
                title="Pastki guruhlar"
                action={(
                    <Link href={createHref} className="table-action-view" aria-label="Pastki guruh qo‘shish" title="Pastki guruh qo‘shish">
                        <PlusOutlined aria-hidden="true" />
                    </Link>
                )}
            />
            {group.children.length ? (
                <ul>
                    {group.children.map((child) => (
                        <li key={child.id} className="border-t border-line first:border-t-0">
                            <Link
                                href={route('admin.groups.show', child.id)}
                                className="group/sub flex items-center gap-3 px-5 py-3 transition-colors duration-150 ease-productive hover:bg-surface-muted"
                            >
                                <InitialsAvatar name={child.name} size="sm" />
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-sm font-bold text-ink">{child.name}</span>
                                    <span className="block truncate text-xs text-ink-muted">
                                        {child.employees_count}
                                        {' xodim'}
                                        {child.children_count ? ` · ${child.children_count} ta pastki guruh` : ''}
                                    </span>
                                </span>
                                <RightOutlined
                                    aria-hidden="true"
                                    className="text-xs text-ink-subtle transition-transform duration-150 ease-productive group-hover/sub:translate-x-0.5"
                                />
                            </Link>
                        </li>
                    ))}
                </ul>
            ) : (
                <div className="px-5 py-8 text-center">
                    <p className="text-sm text-ink-muted">Pastki guruh yo‘q.</p>
                    <Link
                        href={createHref}
                        className="mt-2 inline-flex text-sm font-bold text-brand-700 hover:text-brand-600"
                    >
                        Pastki guruh qo‘shish
                    </Link>
                </div>
            )}
        </section>
    );
}

export default function Show({ group }) {
    return (
        <AdminLayout title={group.name}>
            <BackLink href={route('admin.groups.index')} label="Guruhlar" />
            <ShowHeader
                title={group.name}
                subtitle={<GroupPath group={group} />}
                editHref={route('admin.groups.edit', group.id)}
                deleteHref={route('admin.groups.destroy', group.id)}
            />

            <div className="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4">
                <SummaryStat icon={<TeamOutlined />} label="Xodimlar" value={group.employees_count} />
                <SummaryStat icon={<ClusterOutlined />} label="Pastki guruhlar" value={group.children.length} />
                <SummaryStat
                    icon={<CalendarOutlined />}
                    label="Yaratilgan"
                    value={group.created_at || '—'}
                    className="col-span-2 sm:col-span-1"
                />
            </div>

            <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <EmployeesPanel group={group} />
                <SubgroupsPanel group={group} />
            </div>
        </AdminLayout>
    );
}

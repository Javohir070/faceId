import { Link, router, usePage } from '@inertiajs/react';
import {
    AppstoreOutlined,
    BankOutlined,
    CalendarOutlined,
    ClusterOutlined,
    FileTextOutlined,
    IdcardOutlined,
    KeyOutlined,
    RightOutlined,
    SafetyCertificateOutlined,
    SyncOutlined,
    TeamOutlined,
    WarningOutlined,
} from '@ant-design/icons';
import { Alert, Button } from 'antd';
import AdminLayout from '../../Layouts/AdminLayout';
import { route } from '../../routes';
import useInertiaPoll from '../../hooks/useInertiaPoll';

const quickActions = [
    {
        key: 'employees',
        href: route('admin.employees.create'),
        label: "Xodim qo'shish",
        icon: IdcardOutlined,
        primary: true,
    },
    {
        key: 'devices',
        href: route('admin.devices.create'),
        label: "Qurilma qo'shish",
        icon: ClusterOutlined,
        primary: true,
    },
    {
        key: 'sync',
        href: route('admin.sync.index'),
        label: 'Sinxronizatsiya',
        icon: SyncOutlined,
    },
    {
        key: 'reports',
        href: route('admin.reports.index'),
        label: 'Hisobotlar',
        icon: FileTextOutlined,
    },
];

const moduleGroups = [
    {
        id: 'operations',
        title: 'Biometric operatsiyalar',
        items: [
            {
                key: 'employees',
                href: route('admin.employees.index'),
                label: 'Xodimlar',
                description: 'Yuz ma’lumotlari va kirish huquqlari',
                icon: IdcardOutlined,
            },
            {
                key: 'devices',
                href: route('admin.devices.index'),
                label: 'Qurilmalar',
                description: 'Hikvision qurilmalarini boshqarish',
                icon: ClusterOutlined,
            },
            {
                key: 'sync',
                href: route('admin.sync.index'),
                label: 'Sinxronizatsiya',
                description: 'Qurilmalar bilan ma’lumot almashinuvi',
                icon: SyncOutlined,
            },
            {
                key: 'reports',
                href: route('admin.reports.index'),
                label: 'Hisobotlar',
                description: 'Kirish va davomat hodisalari',
                icon: FileTextOutlined,
            },
        ],
    },
    {
        id: 'organization',
        title: 'Tashkilot',
        items: [
            {
                key: 'departments',
                href: route('admin.departments.index'),
                label: "Bo'limlar",
                description: 'Tashkiliy tuzilma va bo‘linmalar',
                icon: BankOutlined,
            },
            {
                key: 'schedules',
                href: route('admin.schedules.index'),
                label: 'Jadvallar',
                description: 'Ish vaqti jadvallarini sozlash',
                icon: CalendarOutlined,
            },
            {
                key: 'categories',
                href: route('admin.categories.index'),
                label: 'Kategoriyalar',
                description: 'Qurilma kategoriyalari',
                icon: AppstoreOutlined,
            },
        ],
    },
    {
        id: 'settings',
        title: 'Tizim sozlamalari',
        collapsible: true,
        items: [
            {
                key: 'users',
                href: route('admin.users.index'),
                label: 'Foydalanuvchilar',
                description: 'Tizimga kiruvchi hisoblar',
                icon: TeamOutlined,
            },
            {
                key: 'roles',
                href: route('admin.roles.index'),
                label: 'Rollar',
                description: 'Foydalanuvchi rollarini boshqarish',
                icon: SafetyCertificateOutlined,
            },
            {
                key: 'permissions',
                href: route('admin.permissions.index'),
                label: 'Ruxsatlar',
                description: 'Modul va amal huquqlari',
                icon: KeyOutlined,
            },
        ],
    },
];

const statLinks = {
    employees: route('admin.employees.index'),
    devices: route('admin.devices.index'),
    departments: route('admin.departments.index'),
    users: route('admin.users.index'),
};

function greetingForHour(hour) {
    if (hour < 12) {
        return 'Xayrli tong';
    }

    if (hour < 18) {
        return 'Xayrli kun';
    }

    return 'Xayrli kech';
}

function roleLabel(roles = []) {
    if (!roles.length) {
        return 'Foydalanuvchi';
    }

    const primary = roles[0];

    if (primary === 'super-admin') {
        return 'Tizim administratori';
    }

    return String(primary).replace(/-/g, ' ');
}

function isSameDay(a, b) {
    return a.getFullYear() === b.getFullYear()
        && a.getMonth() === b.getMonth()
        && a.getDate() === b.getDate();
}

function formatEventTime(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    const now = new Date();
    const diffMinutes = Math.floor((now.getTime() - date.getTime()) / 60000);

    if (diffMinutes >= 0 && diffMinutes < 1) {
        return 'Hozirgina';
    }

    if (diffMinutes >= 1 && diffMinutes < 60) {
        return `${diffMinutes} daqiqa oldin`;
    }

    const time = new Intl.DateTimeFormat('uz-UZ', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);

    if (isSameDay(date, now)) {
        return `Bugun, ${time}`;
    }

    const yesterday = new Date(now);
    yesterday.setDate(now.getDate() - 1);

    if (isSameDay(date, yesterday)) {
        return `Kecha, ${time}`;
    }

    return new Intl.DateTimeFormat('uz-UZ', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function StatItem({ label, value, detail, href }) {
    const content = (
        <>
            <p className="text-xs font-bold uppercase tracking-wide text-ink-subtle">{label}</p>
            <p className="mt-1 text-2xl font-extrabold tabular-nums tracking-tight text-ink">{value}</p>
            {detail ? (
                <p className="mt-0.5 text-xs font-semibold text-ink-muted">{detail}</p>
            ) : null}
        </>
    );

    if (!href) {
        return (
            <div className="min-w-0 flex-1 px-4 py-3.5 sm:px-5 sm:py-4">
                {content}
            </div>
        );
    }

    return (
        <Link
            href={href}
            className="group min-w-0 flex-1 px-4 py-3.5 transition-colors duration-200 hover:bg-surface-muted sm:px-5 sm:py-4"
        >
            {content}
            <span className="mt-1 inline-flex items-center gap-1 text-xs font-bold text-brand-600 opacity-0 transition-opacity group-hover:opacity-100 dark:text-brand-400">
                Ko‘rish
                <RightOutlined className="text-[10px]" aria-hidden="true" />
            </span>
        </Link>
    );
}

function QuickLinkRow({ item }) {
    const Icon = item.icon;

    return (
        <Link
            href={item.href}
            className="group flex min-h-[52px] items-center gap-3.5 px-4 py-3.5 transition-colors duration-200 hover:bg-surface-muted active:bg-surface-muted sm:gap-4 sm:px-5"
        >
            <span className="brand-chip h-9 w-9 shrink-0">
                <Icon className="text-base" aria-hidden="true" />
            </span>
            <span className="min-w-0 flex-1">
                <span className="block font-extrabold text-ink transition-colors group-hover:text-brand-700 dark:group-hover:text-brand-400">
                    {item.label}
                </span>
                <span className="mt-0.5 block text-sm text-ink-muted">{item.description}</span>
            </span>
            <RightOutlined
                aria-hidden="true"
                className="shrink-0 text-xs text-brand-600 opacity-60 transition-all duration-200 group-hover:translate-x-0.5 group-hover:opacity-100 sm:opacity-0 dark:text-brand-400"
            />
        </Link>
    );
}

function ModuleGroupSection({ group }) {
    if (!group.collapsible) {
        return (
            <section className="overflow-hidden rounded-xl border border-line bg-surface shadow-ambient">
                <div className="border-b border-line px-4 py-3.5 sm:px-5">
                    <h2 className="text-base font-extrabold text-ink">{group.title}</h2>
                </div>
                <div className="divide-y divide-line">
                    {group.items.map((item) => (
                        <QuickLinkRow key={item.key} item={item} />
                    ))}
                </div>
            </section>
        );
    }

    return (
        <details className="group overflow-hidden rounded-xl border border-line bg-surface shadow-ambient">
            <summary className="flex cursor-pointer list-none items-center justify-between gap-3 border-b border-line px-4 py-3.5 marker:content-none sm:px-5 [&::-webkit-details-marker]:hidden">
                <h2 className="text-base font-extrabold text-ink">{group.title}</h2>
                <span className="text-xs font-bold text-brand-600 transition-transform duration-200 group-open:rotate-90 dark:text-brand-400">
                    <RightOutlined aria-hidden="true" />
                </span>
            </summary>
            <div className="divide-y divide-line">
                {group.items.map((item) => (
                    <QuickLinkRow key={item.key} item={item} />
                ))}
            </div>
        </details>
    );
}

export default function Home({ stats = {}, recentEvents = [] }) {
    const { auth, appName } = usePage().props;
    const can = usePage().props.can || {};
    const userName = auth?.user?.name || 'Foydalanuvchi';
    const greeting = greetingForHour(new Date().getHours());
    const brandName = appName || 'EIdora';

    useInertiaPoll(['stats', 'recentEvents'], 10000);

    const visibleActions = quickActions.filter((action) => can[action.key]);
    const visibleGroups = moduleGroups
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => can[item.key]),
        }))
        .filter((group) => group.items.length > 0);

    const hasStats = Object.keys(stats).length > 0;
    const offlineDevices = stats.devices !== undefined && stats.devicesOnline !== undefined
        ? stats.devices - stats.devicesOnline
        : 0;

    return (
        <AdminLayout title="Bosh sahifa">
            <section className="rounded-xl border border-line bg-surface shadow-ambient">
                <div className="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-6 sm:py-6">
                    <div className="min-w-0">
                        <div className="flex items-center gap-3">
                            <img
                                src="/admin/assets/img/eidora-icon.png"
                                alt=""
                                className="h-9 w-auto shrink-0 object-contain"
                            />
                            <div className="min-w-0">
                                <p className="text-sm font-bold text-brand-700 dark:text-brand-400">
                                    {greeting},
                                </p>
                                <h1 className="mt-0.5 text-xl font-extrabold tracking-tight text-ink text-balance sm:text-2xl">
                                    {userName}
                                </h1>
                            </div>
                        </div>
                        <p className="mt-3 max-w-[62ch] text-sm leading-6 text-ink-muted text-pretty">
                            {brandName} paneli — kerakli bo‘limni tanlang yoki quyidagi tezkor harakatlardan foydalaning.
                        </p>
                    </div>
                    <div className="inline-flex shrink-0 items-center self-start rounded-md border border-line bg-surface-muted px-3 py-1.5 text-xs font-bold text-ink-muted">
                        {roleLabel(auth?.user?.roles)}
                    </div>
                </div>
            </section>

            {visibleActions.length > 0 ? (
                <section className="mt-5" aria-label="Tezkor harakatlar">
                    <div className="flex flex-wrap gap-2.5 sm:gap-3">
                        {visibleActions.map((action) => {
                            const Icon = action.icon;

                            return (
                                <Button
                                    key={action.key}
                                    type={action.primary ? 'primary' : 'default'}
                                    size="large"
                                    icon={<Icon />}
                                    className="!h-11 !rounded-lg !px-4 !font-bold"
                                    onClick={() => router.visit(action.href)}
                                >
                                    {action.label}
                                </Button>
                            );
                        })}
                    </div>
                </section>
            ) : null}

            {offlineDevices > 0 && can.devices ? (
                <Alert
                    className="mt-5 !rounded-xl !border-caution/40 !py-3"
                    type="warning"
                    showIcon
                    icon={<WarningOutlined />}
                    message={`${offlineDevices} ta qurilma oflayn`}
                    description="Qurilmalar bo‘limida ulanish holatini tekshiring va kerak bo‘lsa qayta ulang."
                    action={(
                        <Button
                            size="small"
                            type="link"
                            className="!font-bold"
                            onClick={() => router.visit(route('admin.devices.index'))}
                        >
                            Qurilmalar
                        </Button>
                    )}
                />
            ) : null}

            {hasStats ? (
                <section
                    className="mt-5 overflow-hidden rounded-xl border border-line bg-surface shadow-ambient"
                    aria-label="Umumiy ko‘rsatkichlar"
                >
                    <div className="flex flex-col divide-y divide-line sm:flex-row sm:divide-x sm:divide-y-0">
                        {stats.employees !== undefined ? (
                            <StatItem
                                label="Xodimlar"
                                value={stats.employees}
                                href={can.employees ? statLinks.employees : null}
                            />
                        ) : null}
                        {stats.devices !== undefined ? (
                            <StatItem
                                label="Qurilmalar"
                                value={stats.devices}
                                detail={
                                    stats.devicesOnline !== undefined
                                        ? `${stats.devicesOnline} onlayn · ${offlineDevices} oflayn`
                                        : null
                                }
                                href={can.devices ? statLinks.devices : null}
                            />
                        ) : null}
                        {stats.departments !== undefined ? (
                            <StatItem
                                label="Bo‘limlar"
                                value={stats.departments}
                                href={can.departments ? statLinks.departments : null}
                            />
                        ) : null}
                        {stats.users !== undefined ? (
                            <StatItem
                                label="Foydalanuvchilar"
                                value={stats.users}
                                href={can.users ? statLinks.users : null}
                            />
                        ) : null}
                    </div>
                </section>
            ) : null}

            <div className="mt-5 grid gap-5 lg:grid-cols-5 lg:gap-6">
                <div className="space-y-5 lg:col-span-3">
                    {visibleGroups.map((group) => (
                        <ModuleGroupSection key={group.id} group={group} />
                    ))}
                </div>

                <aside className="lg:col-span-2">
                    {recentEvents.length > 0 ? (
                        <section className="overflow-hidden rounded-xl border border-line bg-surface shadow-ambient">
                            <div className="flex items-center justify-between gap-3 border-b border-line px-4 py-3.5 sm:px-5">
                                <div>
                                    <h2 className="text-base font-extrabold text-ink">So‘nggi hodisalar</h2>
                                    <p className="mt-0.5 text-xs text-ink-muted">Oxirgi kirish va davomat yozuvlari</p>
                                </div>
                                {can.reports ? (
                                    <Link
                                        href={route('admin.reports.index')}
                                        className="shrink-0 text-xs font-bold text-brand-600 transition hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300"
                                    >
                                        Barchasi
                                    </Link>
                                ) : null}
                            </div>
                            <ul className="divide-y divide-line">
                                {recentEvents.map((event) => (
                                    <li key={event.id} className="px-4 py-3.5 sm:px-5">
                                        <p className="truncate font-bold text-ink">
                                            {event.full_name || 'Noma’lum'}
                                        </p>
                                        <p className="mt-1 text-xs text-ink-muted">
                                            {formatEventTime(event.event_time)}
                                            {event.device_ip ? ` · ${event.device_ip}` : ''}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ) : (
                        <section className="overflow-hidden rounded-xl border border-line bg-surface shadow-ambient">
                            <div className="border-b border-line px-4 py-3.5 sm:px-5">
                                <h2 className="text-base font-extrabold text-ink">Boshlash uchun</h2>
                            </div>
                            <ul className="divide-y divide-line text-sm leading-6 text-ink-muted">
                                {can.departments ? (
                                    <li className="px-4 py-3.5 sm:px-5">
                                        <Link href={route('admin.departments.index')} className="font-bold text-brand-600 hover:text-brand-700 dark:text-brand-400">
                                            Bo‘lim yarating
                                        </Link>
                                        {' '}— xodimlarni tuzilma bo‘yicha guruhlang.
                                    </li>
                                ) : null}
                                {can.employees ? (
                                    <li className="px-4 py-3.5 sm:px-5">
                                        <Link href={route('admin.employees.create')} className="font-bold text-brand-600 hover:text-brand-700 dark:text-brand-400">
                                            Xodim qo‘shing
                                        </Link>
                                        {' '}— yuz ma’lumotlari va karta bilan.
                                    </li>
                                ) : null}
                                {can.devices ? (
                                    <li className="px-4 py-3.5 sm:px-5">
                                        <Link href={route('admin.devices.create')} className="font-bold text-brand-600 hover:text-brand-700 dark:text-brand-400">
                                            Qurilma ulang
                                        </Link>
                                        {' '}— keyin sinxronizatsiya qiling.
                                    </li>
                                ) : null}
                                {!can.departments && !can.employees && !can.devices ? (
                                    <li className="px-4 py-5 sm:px-5">
                                        Chap menyu orqali sizga ruxsat berilgan bo‘limlarni oching.
                                    </li>
                                ) : null}
                            </ul>
                        </section>
                    )}
                </aside>
            </div>
        </AdminLayout>
    );
}

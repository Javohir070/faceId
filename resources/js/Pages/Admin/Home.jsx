import { Link, router, usePage } from '@inertiajs/react';
import { WarningOutlined } from '@ant-design/icons';
import { Alert, Button } from 'antd';
import AdminLayout from '../../Layouts/AdminLayout';
import DeviceActionsMenu from '../../Components/DeviceActionsMenu';
import StatusTag from '../../Components/StatusTag';
import { route } from '../../routes';
import useInertiaPoll from '../../hooks/useInertiaPoll';

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

function StatItem({ label, value, detail, href }) {
    const content = (
        <>
            <p className="text-xs font-semibold text-ink-subtle">{label}</p>
            <p className="mt-0.5 flex flex-wrap items-baseline gap-x-2">
                <span className="text-lg font-extrabold tabular-nums tracking-tight text-ink">{value}</span>
                {detail ? (
                    <span className="text-xs font-semibold text-ink-muted">{detail}</span>
                ) : null}
            </p>
        </>
    );

    if (!href) {
        return (
            <div className="min-w-0 flex-1 px-4 py-2.5">
                {content}
            </div>
        );
    }

    return (
        <Link
            href={href}
            className="min-w-0 flex-1 px-4 py-2.5 transition-colors duration-200 hover:bg-surface-muted"
        >
            {content}
        </Link>
    );
}

function DevicesSection({ devices }) {
    return (
        <section className="mt-5 overflow-hidden rounded-xl border border-line bg-surface shadow-ambient">
            <div className="flex items-center justify-between gap-3 border-b border-line px-4 py-3.5 sm:px-5">
                <h2 className="text-base font-extrabold text-ink">Qurilmalar</h2>
                <Link
                    href={route('admin.devices.index')}
                    className="shrink-0 text-xs font-bold text-brand-600 transition hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300"
                >
                    Barchasi
                </Link>
            </div>
            <div className="overflow-x-auto">
                <table className="data-table">
                    <thead>
                        <tr>
                            <th>Nomi</th>
                            <th>IP</th>
                            <th>Turi</th>
                            <th>Holat</th>
                            <th>Oxirgi tekshiruv</th>
                            <th className="w-12" aria-label="Amallar" />
                        </tr>
                    </thead>
                    <tbody>
                        {devices.map((device) => (
                            <tr key={device.id}>
                                <td>
                                    <Link
                                        href={route('admin.devices.show', device.id)}
                                        className="font-medium text-brand-600 transition hover:text-brand-700 hover:underline dark:text-brand-400"
                                    >
                                        {device.name}
                                    </Link>
                                </td>
                                <td className="font-medium">{device.ip_address}</td>
                                <td className="font-medium">{device.type_label}</td>
                                <td>
                                    <StatusTag tone={device.is_online ? 'success' : 'neutral'}>
                                        {device.is_online ? 'Ulangan' : 'Ulanmagan'}
                                    </StatusTag>
                                </td>
                                <td className="whitespace-nowrap text-ink-muted">{device.last_ping_at || '—'}</td>
                                <td className="text-right">
                                    <DeviceActionsMenu device={device} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

export default function Home({ stats = {}, devices = [] }) {
    const { auth, appName } = usePage().props;
    const can = usePage().props.can || {};
    const userName = auth?.user?.name || 'Foydalanuvchi';
    const greeting = greetingForHour(new Date().getHours());
    const brandName = appName || 'EIdora';

    useInertiaPoll(['stats', 'devices'], 10000);

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
                            {brandName} paneli — kerakli bo‘limni chap menyudan tanlang.
                        </p>
                    </div>
                    <div className="inline-flex shrink-0 items-center self-start rounded-md border border-line bg-surface-muted px-3 py-1.5 text-xs font-bold text-ink-muted">
                        {roleLabel(auth?.user?.roles)}
                    </div>
                </div>
            </section>

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

            {devices.length > 0 ? <DevicesSection devices={devices} /> : null}
        </AdminLayout>
    );
}

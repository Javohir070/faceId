import { Link } from '@inertiajs/react';
import { ClusterOutlined } from '@ant-design/icons';
import { Input } from 'antd';
import { useMemo, useState } from 'react';
import { route } from '../routes';
import SidePanel, { usePartialPanel } from './SidePanel';
import StatusTag from './StatusTag';

/** Kategoriya qurilmalari paneli ("devicesPanel" propi, ?devices=category:5). */
export function useDevicesPanel() {
    return usePartialPanel('devicesPanel', 'devices');
}

/** Ro‘yxat qatori: chapda nomi va manzili, o‘ngda holati va xodimlar soni. */
function DeviceRow({ device }) {
    const notSynced = (device.employees_count ?? 0) - (device.synced_employees_count ?? 0);

    return (
        <Link
            href={route('admin.devices.show', device.id)}
            className="flex items-center gap-3 px-3 py-3 transition-colors duration-150 ease-productive hover:bg-surface-muted"
        >
            <span
                aria-hidden="true"
                className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-base ${device.is_online ? 'bg-brand-50 text-brand-700' : 'bg-surface-muted text-ink-subtle'}`}
            >
                <ClusterOutlined />
            </span>
            <span className="min-w-0 flex-1">
                <span className="block truncate text-sm font-bold text-ink">{device.name}</span>
                <span className="block truncate text-xs font-medium tabular-nums text-ink-muted">
                    {device.ip_address}
                    {device.port ? `:${device.port}` : ''}
                    {' · '}
                    {device.type_label}
                    {device.last_ping_at ? ` · Ping: ${device.last_ping_at}` : ''}
                </span>
            </span>
            <span className="flex shrink-0 flex-col items-end gap-1">
                <StatusTag tone={device.is_online ? 'success' : 'neutral'}>
                    {device.is_online ? 'Ulangan' : 'Ulanmagan'}
                </StatusTag>
                <span className="whitespace-nowrap text-xs font-semibold tabular-nums text-ink">
                    {device.employees_count ?? 0}
                    {' xodim'}
                    {notSynced > 0 ? (
                        <span className="font-medium text-ink-subtle">
                            {` · ${notSynced} tasi sinxron emas`}
                        </span>
                    ) : null}
                </span>
            </span>
        </Link>
    );
}

export default function DevicesPanel({ state }) {
    const [search, setSearch] = useState('');
    const panel = state.panel;

    const rows = useMemo(() => {
        const needle = search.trim().toLowerCase();
        const devices = panel?.devices ?? [];

        if (!needle) {
            return devices;
        }

        return devices.filter((device) => [device.name, device.ip_address, device.type_label]
            .some((value) => String(value || '').toLowerCase().includes(needle)));
    }, [panel, search]);

    const online = (panel?.devices ?? []).filter((device) => device.is_online).length;

    return (
        <SidePanel
            state={state}
            onClose={() => setSearch('')}
            title={panel?.title ?? 'Qurilmalar'}
            subtitle={panel ? `${panel.total} ta qurilma · ${online} tasi ulangan` : null}
            footer={panel && panel.total > 0 ? (
                <div className="flex justify-end text-sm">
                    <Link
                        href={`${route('admin.devices.index')}?category_id=${panel.filter.category_id}`}
                        className="font-bold text-brand-700 hover:text-brand-600"
                    >
                        Qurilmalar sahifasida ochish
                    </Link>
                </div>
            ) : null}
        >
            {panel ? (
                <>
                    {panel.total > 0 ? (
                        <Input
                            allowClear
                            placeholder="Nomi, IP yoki turi"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="mb-3"
                        />
                    ) : null}

                    {rows.length === 0 ? (
                        <p className="py-10 text-center text-sm text-ink-muted">
                            {panel.total === 0 ? 'Bu kategoriyada hali qurilma yo‘q.' : 'Hech narsa topilmadi.'}
                        </p>
                    ) : (
                        <ul className="divide-y divide-line overflow-hidden rounded-xl border border-line">
                            {rows.map((device) => (
                                <li key={device.id}>
                                    <DeviceRow device={device} />
                                </li>
                            ))}
                        </ul>
                    )}
                </>
            ) : null}
        </SidePanel>
    );
}

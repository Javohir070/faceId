import { Tag } from 'antd';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import ShowPage from '../../../Components/ShowPage';
import useInertiaPoll from '../../../hooks/useInertiaPoll';

function DetailRow({ label, children }) {
    return (
        <div className="grid grid-cols-[140px_1fr] gap-3 border-b border-line/60 py-3 last:border-b-0">
            <dt className="text-xs font-extrabold uppercase tracking-wide text-ink-subtle">{label}</dt>
            <dd className="text-sm font-semibold text-ink">{children}</dd>
        </div>
    );
}

export default function Show({ device }) {
    useInertiaPoll(['device'], 10000);

    return (
        <AdminLayout title="Qurilma">
            <ShowPage
                backHref={route('admin.devices.index')}
                title={device.name}
                subtitle={device.description}
                editHref={route('admin.devices.edit', device.id)}
                deleteHref={route('admin.devices.destroy', device.id)}
                maxWidth="max-w-2xl"
            >
                <dl>
                    <DetailRow label="Holat">
                        <Tag color={device.is_connected ? 'success' : 'default'}>
                            {device.is_connected ? 'Ulangan' : 'Ulanmagan'}
                        </Tag>
                    </DetailRow>
                    <DetailRow label="Oxirgi ping">{device.last_ping_at || '—'}</DetailRow>
                    <DetailRow label="Ulangan vaqt">{device.last_connected_at || '—'}</DetailRow>
                    <DetailRow label="Webhook">
                        <Tag color={device.webhook_configured ? 'success' : 'default'}>
                            {device.webhook_configured ? 'Sozlangan' : 'Yo‘q'}
                        </Tag>
                    </DetailRow>
                    <DetailRow label="Kategoriya">{device.category?.name || '—'}</DetailRow>
                    <DetailRow label="Egasi">{device.owner?.name || '—'}</DetailRow>
                    <DetailRow label="IP / Port">
                        {device.ip_address}
                        :
                        {device.port}
                    </DetailRow>
                    <DetailRow label="Login">{device.user}</DetailRow>
                    <DetailRow label="Turi">{device.type_label || device.type}</DetailRow>
                    <DetailRow label="Avtoulanish">
                        <Tag color={device.autoconnect ? 'success' : 'default'}>
                            {device.autoconnect ? 'Yoqilgan' : 'O‘chirilgan'}
                        </Tag>
                    </DetailRow>
                </dl>
            </ShowPage>
        </AdminLayout>
    );
}

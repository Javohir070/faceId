import { Alert, Button } from 'antd';
import { DownloadOutlined } from '@ant-design/icons';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import PageHeader, { EmptyState } from '../../../Components/PageHeader';
import TableShell from '../../../Components/TableShell';

function formatSize(bytes) {
    if (bytes >= 1024 * 1024) {
        return `${(bytes / 1024 / 1024).toFixed(1).replace('.', ',')} MB`;
    }

    return `${(bytes / 1024).toFixed(1).replace('.', ',')} KB`;
}

export default function Index({ backups, unreadable, keep }) {
    return (
        <AdminLayout title="Zaxira nusxalari">
            <PageHeader
                title="Zaxira nusxalari"
                subtitle={`Bazaning nusxasi har oy 1-sanasi soat 04:00 da avtomatik olinadi, oxirgi ${keep} tasi saqlanadi. Faylda barcha xodimlarning JSHSHIR va yuz rasmlari bor.`}
            />

            {unreadable ? (
                <Alert
                    type="warning"
                    showIcon
                    className="!mb-5"
                    message="Zaxira papkasini o‘qib bo‘lmadi"
                    description="Serverda .env ga DB_BACKUP_GROUP (masalan www-data) yozing va php artisan db:backup ni ishga tushiring."
                />
            ) : null}

            <TableShell
                empty={backups.length === 0}
                emptyState={(
                    <EmptyState
                        title="Hali zaxira nusxasi yo‘q"
                        description="Birinchi nusxa keyingi oyning 1-sanasi soat 04:00 da olinadi."
                    />
                )}
            >
                <table className="data-table">
                    <thead>
                        <tr>
                            <th className="w-16">N#</th>
                            <th>Fayl</th>
                            <th>Sana va vaqt</th>
                            <th>Hajmi</th>
                            <th className="text-right">Amallar</th>
                        </tr>
                    </thead>
                    <tbody>
                        {backups.map((backup, index) => (
                            <tr key={backup.filename}>
                                <td className="font-medium text-ink-subtle">{index + 1}</td>
                                <td className="font-bold text-ink break-all">{backup.filename}</td>
                                <td className="whitespace-nowrap tabular-nums">{backup.created_at}</td>
                                <td className="whitespace-nowrap tabular-nums">{formatSize(backup.bytes)}</td>
                                <td className="text-right">
                                    <Button
                                        icon={<DownloadOutlined />}
                                        href={route('admin.backups.download', backup.filename)}
                                    >
                                        Yuklab olish
                                    </Button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </TableShell>
        </AdminLayout>
    );
}

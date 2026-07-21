import { Tag } from 'antd';
import AdminLayout from '../../../Layouts/AdminLayout';
import ShowPage from '../../../Components/ShowPage';

export default function Show({ role }) {
    return (
        <AdminLayout title="Rol">
            <ShowPage
                backHref="/admin/roles"
                title={role.name}
                editHref={`/admin/roles/${role.id}/edit`}
                deleteHref={`/admin/roles/${role.id}`}
            >
                <div>
                    <h2 className="mb-3 text-xs font-extrabold uppercase tracking-wide text-ink-subtle">
                        Ruxsatlar
                    </h2>
                    <div className="flex flex-wrap gap-2">
                        {role.permissions?.length
                            ? role.permissions.map((permission) => (
                                <Tag key={permission.id}>{permission.name}</Tag>
                            ))
                            : (
                                <span className="text-sm font-semibold text-ink-subtle">
                                    Ruxsatlar yo‘q
                                </span>
                            )}
                    </div>
                </div>
            </ShowPage>
        </AdminLayout>
    );
}

import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import ShowPage from '../../../Components/ShowPage';
import StatusTag from '../../../Components/StatusTag';

export default function Show({ role }) {
    return (
        <AdminLayout title="Rol">
            <ShowPage
                backHref={route('admin.roles.index')}
                title={role.name}
                editHref={route('admin.roles.edit', role.id)}
                deleteHref={route('admin.roles.destroy', role.id)}
            >
                <div>
                    <h2 className="mb-3 text-xs font-extrabold uppercase tracking-wide text-ink-subtle">
                        Ruxsatlar
                    </h2>
                    <div className="flex flex-wrap gap-2">
                        {role.permissions?.length
                            ? role.permissions.map((permission) => (
                                <StatusTag key={permission.id} tone="neutral">{permission.name}</StatusTag>
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

import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import ShowPage from '../../../Components/ShowPage';

export default function Show({ permission }) {
    return (
        <AdminLayout title="Ruxsat">
            <ShowPage
                backHref={route('admin.permissions.index')}
                title={permission.name}
                subtitle={`Guard: ${permission.guard_name}`}
                editHref={route('admin.permissions.edit', permission.id)}
                deleteHref={route('admin.permissions.destroy', permission.id)}
                maxWidth="max-w-md"
            >
                <p className="text-sm leading-6 text-ink-muted">
                    Bu ruxsat rollarga biriktirilganda, foydalanuvchilar tegishli bo‘limga kirishi mumkin.
                </p>
            </ShowPage>
        </AdminLayout>
    );
}

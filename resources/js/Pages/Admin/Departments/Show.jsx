import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import ShowPage from '../../../Components/ShowPage';

export default function Show({ department }) {
    return (
        <AdminLayout title="Bo'lim">
            <ShowPage
                backHref={route('admin.departments.index')}
                title={department.name}
                editHref={route('admin.departments.edit', department.id)}
                deleteHref={route('admin.departments.destroy', department.id)}
                maxWidth="max-w-md"
            >
                <p className="text-sm leading-6 text-ink-muted">
                    Bu bo&apos;lim tashkilot strukturasini boshqarish uchun ishlatiladi.
                </p>
            </ShowPage>
        </AdminLayout>
    );
}

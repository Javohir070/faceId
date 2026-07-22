import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import ShowPage from '../../../Components/ShowPage';

export default function Show({ category }) {
    return (
        <AdminLayout title="Kategoriya">
            <ShowPage
                backHref={route('admin.categories.index')}
                title={category.name}
                subtitle={`${category.devices_count ?? 0} ta qurilma`}
                editHref={route('admin.categories.edit', category.id)}
                deleteHref={route('admin.categories.destroy', category.id)}
                maxWidth="max-w-md"
            >
                <p className="text-sm leading-6 text-ink-muted">
                    Bu kategoriya qurilmalarni guruhlash uchun ishlatiladi.
                </p>
            </ShowPage>
        </AdminLayout>
    );
}

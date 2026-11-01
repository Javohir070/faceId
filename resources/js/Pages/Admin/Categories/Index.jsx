import { router, useForm } from '@inertiajs/react';
import { Button, Drawer, Input } from 'antd';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import PageHeader, { ResourceActions } from '../../../Components/PageHeader';
import FormField from '../../../Components/FormField';
import Pagination from '../../../Components/Pagination';
import TableShell from '../../../Components/TableShell';

/** Qo‘shish / tahrirlash formasi — o‘ng paneldagi tarkib. */
function CategoryForm({ category, onDone, onCancel }) {
    const isEdit = Boolean(category);
    const { data, setData, post, put, processing, errors } = useForm({
        name: category?.name || '',
    });

    const submit = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, preserveState: true, onSuccess: onDone };

        if (isEdit) {
            put(route('admin.categories.update', category.id), options);
        } else {
            post(route('admin.categories.store'), options);
        }
    };

    return (
        <form onSubmit={submit} className="flex h-full flex-col">
            <div className="flex-1">
                <FormField label="Nomi" error={errors.name} required>
                    <Input
                        autoFocus
                        size="large"
                        placeholder="Masalan: Asosiy kirish"
                        value={data.name}
                        status={errors.name ? 'error' : undefined}
                        onChange={(e) => setData('name', e.target.value)}
                    />
                </FormField>
                <p className="mt-2 text-sm text-ink-muted">
                    Kategoriya qurilmalarni guruhlash uchun: masalan, bino yoki kirish joyi bo‘yicha.
                </p>
            </div>
            <div className="mt-6 flex justify-end gap-2 border-t border-line pt-4">
                <Button onClick={onCancel}>Bekor qilish</Button>
                <Button type="primary" htmlType="submit" loading={processing} className="!font-bold">
                    {isEdit ? 'Saqlash' : 'Qo‘shish'}
                </Button>
            </div>
        </form>
    );
}

export default function Index({ categories, drawer = null }) {
    // drawer: { mode: 'create' } | { mode: 'edit', category } — eski create/edit havolalari shu holatni ochadi.
    const [panel, setPanel] = useState(drawer);
    const hasPanelQuery = /[?&](create|edit)=/.test(window.location.search);

    const closePanel = () => {
        setPanel(null);

        if (hasPanelQuery) {
            router.get(route('admin.categories.index'), {}, {
                replace: true,
                preserveState: true,
                preserveScroll: true,
                only: ['drawer'],
            });
        }
    };

    const openCreate = () => setPanel({ mode: 'create' });
    const isEdit = panel?.mode === 'edit';

    return (
        <AdminLayout title="Kategoriyalar">
            <PageHeader
                title="Kategoriyalar"
                subtitle="Qurilmalar uchun kategoriyalarni boshqaring."
                onCreate={openCreate}
                createLabel="Kategoriya qo'shish"
            />
            <TableShell
                empty={categories.data.length === 0}
                onCreate={openCreate}
                createLabel="Kategoriya qo'shish"
                footer={categories.data.length > 0 ? (
                    <Pagination
                        links={categories.links}
                        perPage={categories.per_page}
                        total={categories.total}
                    />
                ) : null}
            >
                <table className="data-table">
                    <thead>
                        <tr>
                            <th className="w-16">N#</th>
                            <th>Nomi</th>
                            <th>Qurilmalar</th>
                            <th className="text-right">Amallar</th>
                        </tr>
                    </thead>
                    <tbody>
                        {categories.data.map((category, index) => (
                            <tr key={category.id}>
                                <td className="font-medium text-ink-subtle">{(categories.from ?? 1) + index}</td>
                                <td className="font-bold text-ink">{category.name}</td>
                                <td className="font-medium">{category.devices_count ?? 0}</td>
                                <td>
                                    <ResourceActions
                                        onEdit={() => setPanel({ mode: 'edit', category: { id: category.id, name: category.name } })}
                                        deleteHref={route('admin.categories.destroy', category.id)}
                                    />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </TableShell>

            <Drawer
                open={Boolean(panel)}
                onClose={closePanel}
                placement="right"
                size={420}
                destroyOnHidden
                title={isEdit ? 'Kategoriyani tahrirlash' : 'Yangi kategoriya'}
            >
                {panel ? (
                    <CategoryForm
                        key={isEdit ? `edit-${panel.category.id}` : 'create'}
                        category={isEdit ? panel.category : null}
                        onDone={() => setPanel(null)}
                        onCancel={closePanel}
                    />
                ) : null}
            </Drawer>
        </AdminLayout>
    );
}

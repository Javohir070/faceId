import { useForm } from '@inertiajs/react';
import { Input } from 'antd';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import FormCard from '../../../Components/FormCard';
import FormField from '../../../Components/FormField';

export default function Form({ category }) {
    const isEdit = !!category;
    const { data, setData, post, put, processing, errors } = useForm({
        name: category?.name || '',
    });

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) put(route('admin.categories.update', category.id));
        else post(route('admin.categories.store'));
    };

    return (
        <AdminLayout title={isEdit ? 'Kategoriyani tahrirlash' : "Kategoriya qo'shish"}>
            <FormCard
                title={isEdit ? 'Kategoriyani tahrirlash' : 'Yangi kategoriya'}
                subtitle="Kategoriya nomini kiriting."
                backHref={route('admin.categories.index')}
                onSubmit={submit}
                processing={processing}
                cancelHref={route('admin.categories.index')}
                width="sm"
            >
                <FormField label="Nomi" error={errors.name} required>
                    <Input
                        value={data.name}
                        status={errors.name ? 'error' : undefined}
                        onChange={(e) => setData('name', e.target.value)}
                    />
                </FormField>
            </FormCard>
        </AdminLayout>
    );
}

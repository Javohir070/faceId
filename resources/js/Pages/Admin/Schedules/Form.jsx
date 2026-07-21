import { useForm } from '@inertiajs/react';
import { Input } from 'antd';
import AdminLayout from '../../../Layouts/AdminLayout';
import FormCard from '../../../Components/FormCard';
import FormField from '../../../Components/FormField';

export default function Form({ schedule }) {
    const isEdit = !!schedule;
    const { data, setData, post, put, processing, errors } = useForm({
        name: schedule?.name || '',
        enter_time: schedule?.enter_time || '',
        exit_time: schedule?.exit_time || '',
    });

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) put(`/admin/schedules/${schedule.id}`);
        else post('/admin/schedules');
    };

    return (
        <AdminLayout title={isEdit ? 'Jadvalni tahrirlash' : "Jadval qo'shish"}>
            <FormCard
                title={isEdit ? 'Jadvalni tahrirlash' : 'Yangi jadval'}
                subtitle="Jadval nomi va kirish/chiqish vaqtlarini kiriting."
                backHref="/admin/schedules"
                onSubmit={submit}
                processing={processing}
                cancelHref="/admin/schedules"
                width="md"
            >
                <FormField label="Nomi" error={errors.name} required>
                    <Input
                        value={data.name}
                        status={errors.name ? 'error' : undefined}
                        onChange={(e) => setData('name', e.target.value)}
                    />
                </FormField>

                <div className="grid gap-4 md:grid-cols-2">
                    <FormField label="Kirish vaqti" error={errors.enter_time} required>
                        <Input
                            type="time"
                            value={data.enter_time}
                            status={errors.enter_time ? 'error' : undefined}
                            onChange={(e) => setData('enter_time', e.target.value)}
                        />
                    </FormField>

                    <FormField label="Chiqish vaqti" error={errors.exit_time} required>
                        <Input
                            type="time"
                            value={data.exit_time}
                            status={errors.exit_time ? 'error' : undefined}
                            onChange={(e) => setData('exit_time', e.target.value)}
                        />
                    </FormField>
                </div>
            </FormCard>
        </AdminLayout>
    );
}

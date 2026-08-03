import { useForm } from '@inertiajs/react';
import { Input, Select } from 'antd';
import AdminLayout from '../../../Layouts/AdminLayout';
import FormCard from '../../../Components/FormCard';
import FormField from '../../../Components/FormField';
import { route } from '../../../routes';

export default function Form({
    note,
    employees = [],
    types = [],
    prefillEmployeeId = null,
}) {
    const isEdit = Boolean(note);
    const { data, setData, post, processing, errors } = useForm({
        employee_id: note?.employee_id ?? prefillEmployeeId,
        type: note?.type ?? null,
        dt: note?.dt?.slice(0, 10) || '',
        note: note?.note || '',
        _method: isEdit ? 'put' : undefined,
    });

    const submit = (event) => {
        event.preventDefault();
        post(isEdit
            ? route('admin.notes.update', note.id)
            : route('admin.notes.store'));
    };

    return (
        <AdminLayout title={isEdit ? 'Izohni tahrirlash' : 'Izoh qo‘shish'}>
            <FormCard
                title={isEdit ? 'Izohni tahrirlash' : 'Yangi izoh'}
                subtitle="Xodim, izoh tipi va sanani kiriting."
                backHref={route('admin.notes.index')}
                onSubmit={submit}
                processing={processing}
                cancelHref={route('admin.notes.index')}
                width="lg"
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField label="Xodim" error={errors.employee_id} required>
                        <Select
                            showSearch
                            className="w-full"
                            size="large"
                            placeholder="Xodimni tanlang"
                            optionFilterProp="label"
                            value={data.employee_id}
                            status={errors.employee_id ? 'error' : undefined}
                            options={employees.map((employee) => ({
                                value: employee.id,
                                label: employee.name,
                            }))}
                            onChange={(value) => setData('employee_id', value)}
                        />
                    </FormField>

                    <FormField label="Tip" error={errors.type} required>
                        <Select
                            className="w-full"
                            size="large"
                            placeholder="Tipni tanlang"
                            value={data.type}
                            status={errors.type ? 'error' : undefined}
                            options={types}
                            onChange={(value) => setData('type', value)}
                        />
                    </FormField>
                </div>

                <FormField label="Sana" error={errors.dt} required>
                    <Input
                        size="large"
                        type="date"
                        value={data.dt}
                        status={errors.dt ? 'error' : undefined}
                        onChange={(event) => setData('dt', event.target.value)}
                    />
                </FormField>

                <FormField label="Izoh" error={errors.note} required>
                    <Input.TextArea
                        rows={5}
                        value={data.note}
                        status={errors.note ? 'error' : undefined}
                        placeholder="Izoh tafsilotlarini kiriting"
                        onChange={(event) => setData('note', event.target.value)}
                    />
                </FormField>
            </FormCard>
        </AdminLayout>
    );
}

import { router, useForm } from '@inertiajs/react';
import { Button, Input, InputNumber, Select } from 'antd';
import { PlusOutlined } from '@ant-design/icons';
import AdminLayout from '../../../Layouts/AdminLayout';
import FormField from '../../../Components/FormField';
import PageHeader, { DeleteButton } from '../../../Components/PageHeader';
import TableShell from '../../../Components/TableShell';
import { route } from '../../../routes';
import { formatNumber } from '../../../utils/liveFormat';

function splitOf(entered, maleShare) {
    const total = Number(entered) || 0;
    const male = Math.round(total * maleShare);

    return { male, female: total - male };
}

function Total({ label, value }) {
    return (
        <div className="rounded-lg border border-line bg-surface-muted px-4 py-3">
            <p className="text-xs font-bold uppercase tracking-wide text-ink-subtle">{label}</p>
            <p className="mt-1 text-xl font-extrabold text-ink tabular-nums">{formatNumber(value)}</p>
        </div>
    );
}

export default function Adjustments({ date, adjustments = [], totals, groups = [], defaultGroupId = null, maleShare = 0.7 }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        date,
        entered: null,
        group_id: defaultGroupId,
        note: '',
    });

    const preview = splitOf(data.entered, maleShare);
    const groupName = groups.find((group) => group.id === data.group_id)?.name;

    const submit = (event) => {
        event.preventDefault();
        post(route('admin.live.adjustments.store'), {
            preserveScroll: true,
            onSuccess: () => reset('entered', 'note'),
        });
    };

    const changeDate = (value) => {
        setData('date', value);
        router.get(route('admin.live.adjustments.index'), { date: value }, { preserveScroll: true, preserveState: true });
    };

    return (
        <AdminLayout title="sq">
            <PageHeader
                title="sq"
                subtitle="Tanlangan kun uchun Jonli kuzatuv va Tashrif statistikasidagi kirganlar soniga qo‘lda son qo‘shing. Faqat shu kun uchun amal qiladi."
            />

            <div className="grid gap-5 lg:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]">
                <form onSubmit={submit} className="card space-y-5">
                    <FormField label="Sana" error={errors.date} required>
                        <Input
                            size="large"
                            type="date"
                            value={data.date}
                            status={errors.date ? 'error' : undefined}
                            onChange={(event) => changeDate(event.target.value)}
                        />
                    </FormField>

                    <FormField label="Qo‘shiladigan kirishlar" error={errors.entered} required>
                        <InputNumber
                            size="large"
                            className="!w-full"
                            min={1}
                            max={1000000}
                            precision={0}
                            placeholder="Masalan, 1000"
                            value={data.entered}
                            status={errors.entered ? 'error' : undefined}
                            onChange={(value) => setData('entered', value)}
                        />
                    </FormField>

                    <FormField label="Guruh" error={errors.group_id} hint="Faqat shu guruhga qo‘shiladi.">
                        <Select
                            size="large"
                            className="w-full"
                            allowClear
                            placeholder="Guruhsiz"
                            value={data.group_id}
                            status={errors.group_id ? 'error' : undefined}
                            options={groups.map((group) => ({ value: group.id, label: group.name }))}
                            onChange={(value) => setData('group_id', value ?? null)}
                        />
                    </FormField>

                    <FormField label="Izoh" error={errors.note}>
                        <Input
                            size="large"
                            maxLength={255}
                            placeholder="Masalan, 2-kirish darvozasi"
                            value={data.note}
                            onChange={(event) => setData('note', event.target.value)}
                        />
                    </FormField>

                    <div className="rounded-lg border border-line bg-surface-muted p-4 text-sm text-ink-muted">
                        <p className="font-bold text-ink">Statistikaga qanday qo‘shiladi</p>
                        <ul className="mt-2 space-y-1">
                            <li>Kirganlar: <strong className="text-ink">+{formatNumber(Number(data.entered) || 0)}</strong></li>
                            <li>
                                Erkaklar: <strong className="text-ink">+{formatNumber(preview.male)}</strong>
                                {' · '}
                                Ayollar: <strong className="text-ink">+{formatNumber(preview.female)}</strong>
                                {' '}({Math.round(maleShare * 100)}% / {100 - Math.round(maleShare * 100)}%)
                            </li>
                            <li>Mahalliy: <strong className="text-ink">+{formatNumber(Number(data.entered) || 0)}</strong>, xorijiy o‘zgarmaydi</li>
                            <li>Guruh: <strong className="text-ink">{groupName ?? 'guruhsiz'}</strong></li>
                        </ul>
                    </div>

                    <Button
                        type="primary"
                        htmlType="submit"
                        size="large"
                        icon={<PlusOutlined />}
                        loading={processing}
                        className="!h-11 !w-full !rounded-lg !font-bold"
                    >
                        Qo‘shish
                    </Button>
                </form>

                <div className="space-y-5">
                    <div className="grid gap-3 sm:grid-cols-4">
                        <Total label="Jami qo‘shilgan" value={totals.entered} />
                        <Total label="Erkaklar" value={totals.male} />
                        <Total label="Ayollar" value={totals.female} />
                        <Total label="Mahalliy" value={totals.local} />
                    </div>

                    <TableShell
                        empty={adjustments.length === 0}
                        emptyState={(
                            <p className="py-10 text-center text-sm font-semibold text-ink-muted">
                                Bu kun uchun hali son qo‘shilmagan.
                            </p>
                        )}
                    >
                        <table className="data-table">
                            <thead>
                                <tr>
                                    <th>Vaqt</th>
                                    <th className="text-right">Kirishlar</th>
                                    <th className="text-right">Erkak / Ayol</th>
                                    <th>Guruh</th>
                                    <th>Izoh</th>
                                    <th>Kim qo‘shdi</th>
                                    <th className="text-right">Amallar</th>
                                </tr>
                            </thead>
                            <tbody>
                                {adjustments.map((adjustment) => (
                                    <tr key={adjustment.id}>
                                        <td className="font-medium text-ink-subtle tabular-nums">{adjustment.created_at}</td>
                                        <td className="text-right font-bold text-ink tabular-nums">+{formatNumber(adjustment.entered)}</td>
                                        <td className="text-right tabular-nums">
                                            {formatNumber(adjustment.male)} / {formatNumber(adjustment.female)}
                                        </td>
                                        <td>{adjustment.group_name ?? '—'}</td>
                                        <td className="max-w-xs truncate">{adjustment.note || '—'}</td>
                                        <td>{adjustment.user_name ?? '—'}</td>
                                        <td>
                                            <div className="flex justify-end">
                                                <DeleteButton
                                                    href={route('admin.live.adjustments.destroy', adjustment.id)}
                                                    confirmMessage="Bu qo‘shilgan son statistikadan olib tashlanadi. Davom etasizmi?"
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </TableShell>
                </div>
            </div>
        </AdminLayout>
    );
}

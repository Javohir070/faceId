import { router } from '@inertiajs/react';
import { ArrowRightOutlined, CalendarOutlined } from '@ant-design/icons';
import { Alert, Button, DatePicker, TimePicker } from 'antd';
import dayjs from 'dayjs';
import { useEffect, useState } from 'react';
import { route } from '../../routes';
import { validityTags } from '../EmployeeCard';
import SidePanel from '../SidePanel';
import StatusTag from '../StatusTag';

const FORMAT = 'YYYY-MM-DD HH:mm';

const presets = [
    { key: '1m', label: '+1 oy', months: 1 },
    { key: '3m', label: '+3 oy', months: 3 },
    { key: '6m', label: '+6 oy', months: 6 },
    { key: '1y', label: '+1 yil', months: 12 },
];

/** Tez tugmalar bugundan hisoblanadi va kun oxiriga qo‘yiladi: xodim oxirgi kuni ham to‘liq o‘tadi. */
function presetDate(months) {
    return dayjs().add(months, 'month').hour(23).minute(59).second(0).millisecond(0);
}

/**
 * Tanlangan xodimlarning amal qilish muddatini bitta sanaga uzaytiruvchi o‘ng panel.
 * Faqat bazani o‘zgartiradi — terminallar yangi muddatni Sinxronizatsiyadan keyin oladi.
 *
 * @param {Array<{id:number, name:string, valid_to_dt:?string, validity:?string}>} employees
 */
export default function ExtendValidityPanel({ open, employees = [], hint, onClose, onDone }) {
    const [value, setValue] = useState(null);
    const [preset, setPreset] = useState(null);
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (open) {
            setValue(null);
            setPreset(null);
            setErrors({});
        }
    }, [open]);

    const choosePreset = (item) => {
        setPreset(item.key);
        setValue(presetDate(item.months));
        setErrors({});
    };

    const changeValue = (next) => {
        setValue(next);
        setPreset(null);
        setErrors({});
    };

    // Yangi sana hozirgisidan oldin bo‘lsa, muddat uzaymaydi, qisqaradi — oldindan ogohlantiriladi.
    const shortened = value
        ? employees.filter((employee) => employee.valid_to_dt && dayjs(employee.valid_to_dt).isAfter(value))
        : [];

    const submit = () => {
        if (!value) {
            setErrors({ valid_to_dt: 'Yangi tugash sanasini tanlang.' });
            return;
        }

        setProcessing(true);
        router.post(route('admin.employees.bulk-extend'), {
            employee_ids: employees.map((employee) => employee.id),
            valid_to_dt: value.format(FORMAT),
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                onDone?.();
                onClose();
            },
            onError: setErrors,
            onFinish: () => setProcessing(false),
        });
    };

    const listError = errors.employee_ids
        ?? Object.entries(errors).find(([key]) => key.startsWith('employee_ids.'))?.[1];

    return (
        <SidePanel
            narrow
            state={{ isOpen: open, loading: false, panel: true, close: onClose }}
            title="Muddatni uzaytirish"
            subtitle={`${employees.length} ta xodim tanlandi`}
            footer={(
                <div className="flex justify-end gap-2 py-1">
                    <Button className="!rounded-lg !font-bold" onClick={onClose} disabled={processing}>
                        Bekor qilish
                    </Button>
                    <Button
                        type="primary"
                        className="!rounded-lg !font-bold"
                        icon={<CalendarOutlined />}
                        loading={processing}
                        disabled={!value || employees.length === 0}
                        onClick={submit}
                    >
                        Uzaytirish
                    </Button>
                </div>
            )}
        >
            <div className="space-y-6">
                {listError ? <Alert type="error" showIcon title={listError} /> : null}

                <section>
                    <h3 className="mb-1 text-sm font-extrabold text-ink">Yangi tugash sanasi</h3>
                    <p className="mb-3 text-xs font-medium text-ink-muted">
                        Tez tugmalar bugundan boshlab hisoblaydi, kun oxirigacha (23:59).
                    </p>
                    <div className="mb-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        {presets.map((item) => (
                            <Button
                                key={item.key}
                                type={preset === item.key ? 'primary' : 'default'}
                                className="!rounded-lg !font-bold"
                                onClick={() => choosePreset(item)}
                            >
                                {item.label}
                            </Button>
                        ))}
                    </div>
                    {/* Sana va vaqt alohida: birga ochiladigan oyna telefon ekraniga sig‘maydi. */}
                    <div className="flex gap-2">
                        <DatePicker
                            inputReadOnly
                            format="YYYY-MM-DD"
                            size="large"
                            className="min-w-0 flex-1"
                            placeholder="Sanani tanlang"
                            value={value}
                            status={errors.valid_to_dt ? 'error' : undefined}
                            disabledDate={(date) => date.isBefore(dayjs(), 'day')}
                            onChange={(date) => changeValue(date
                                ? date.hour(value?.hour() ?? 23).minute(value?.minute() ?? 59).second(0).millisecond(0)
                                : null)}
                        />
                        <TimePicker
                            inputReadOnly
                            format="HH:mm"
                            size="large"
                            className="w-28 shrink-0"
                            placeholder="Vaqt"
                            needConfirm={false}
                            disabled={!value}
                            value={value}
                            status={errors.valid_to_dt ? 'error' : undefined}
                            onChange={(time) => changeValue(time && value
                                ? value.hour(time.hour()).minute(time.minute())
                                : value)}
                        />
                    </div>
                    {errors.valid_to_dt ? (
                        <p className="mt-1.5 text-xs font-semibold text-negative">{errors.valid_to_dt}</p>
                    ) : null}
                </section>

                {shortened.length > 0 ? (
                    <Alert
                        type="warning"
                        showIcon
                        title={`${shortened.length} ta xodimning hozirgi muddati bu sanadan keyin tugaydi — ularning muddati qisqaradi.`}
                    />
                ) : null}

                <Alert type="info" showIcon title={hint} />

                <section>
                    <h3 className="mb-3 text-sm font-extrabold text-ink">
                        Tanlangan xodimlar
                        <span className="ml-1.5 text-ink-muted">{employees.length}</span>
                    </h3>
                    <ul className="divide-y divide-line overflow-hidden rounded-xl border border-line">
                        {employees.map((employee) => {
                            const tag = validityTags[employee.validity];
                            const isShortened = shortened.includes(employee);

                            return (
                                <li key={employee.id} className="flex items-center justify-between gap-3 px-4 py-3">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-bold text-ink">{employee.name}</p>
                                        <p className="flex flex-wrap items-center gap-1.5 text-xs font-medium text-ink-muted">
                                            <span>{employee.valid_to_dt || 'Muddat yo‘q'}</span>
                                            {value ? (
                                                <>
                                                    <ArrowRightOutlined aria-hidden="true" className="text-[10px]" />
                                                    <span className={isShortened ? 'font-bold text-caution' : 'font-bold text-brand-700'}>
                                                        {value.format(FORMAT)}
                                                    </span>
                                                </>
                                            ) : null}
                                        </p>
                                    </div>
                                    {tag ? <StatusTag tone={tag.tone}>{tag.label}</StatusTag> : null}
                                </li>
                            );
                        })}
                    </ul>
                </section>
            </div>
        </SidePanel>
    );
}

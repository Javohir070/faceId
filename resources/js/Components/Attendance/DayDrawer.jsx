import { Button, Drawer, Tag } from 'antd';
import { formatDayTitle } from '../../utils/uzDate';
import { STATUS_LABELS } from './AttendanceGrid';

const STATUS_TAG_COLORS = {
    on_time: 'success',
    late: 'warning',
    excused: 'processing',
    absent: 'error',
    pending: 'default',
};

function InfoCard({ label, value, note }) {
    return (
        <div className="rounded-xl bg-surface-muted p-4">
            <p className="text-sm text-ink-muted">{label}</p>
            <p className="mt-1 text-2xl font-extrabold text-ink">{value}</p>
            {note && <p className="mt-1 text-sm text-ink-muted">{note}</p>}
        </div>
    );
}

/** Kun tafsiloti: kirish-chiqishlar va shu kunga izohlar (arizalar). */
export default function DayDrawer({ day, open, canAddNote, onClose, onAddNote }) {
    return (
        <Drawer
            open={open}
            onClose={onClose}
            size={480}
            destroyOnHidden
            title={day ? (
                <div className="flex flex-wrap items-center gap-2">
                    <span>{formatDayTitle(day.date)}</span>
                    {STATUS_LABELS[day.status] && (
                        <Tag color={STATUS_TAG_COLORS[day.status] ?? 'default'} className="!m-0">
                            {STATUS_LABELS[day.status]}
                        </Tag>
                    )}
                </div>
            ) : 'Kun tafsiloti'}
            footer={(
                <div className="flex justify-end gap-2">
                    <Button onClick={onClose}>Yopish</Button>
                    {canAddNote && day && (
                        <Button type="primary" onClick={() => onAddNote(day.employee.id, day.date)}>
                            Shu kun uchun ariza berish
                        </Button>
                    )}
                </div>
            )}
        >
            {day && (
                <div className="space-y-6">
                    <div>
                        <p className="font-bold text-ink">{day.employee.name}</p>
                        {day.employee.position && <p className="text-sm text-ink-muted">{day.employee.position}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <InfoCard label="Kelgan" value={day.in ?? '—'} />
                        <InfoCard label="Ketgan" value={day.inside ? 'ishda' : (day.out ?? '—')} />
                        <InfoCard label="Ishladi" value={day.worked} note="tushlik (1 soat) chegirib" />
                        <InfoCard label="Ish vaqti" value={day.schedule} note="Dushanba–juma" />
                    </div>

                    <section>
                        <h3 className="mb-3 font-bold text-ink">Kirish-chiqishlar</h3>
                        {day.punches.length ? (
                            <ol className="space-y-4">
                                {day.punches.map((punch, index) => (
                                    <li key={`${punch.time}-${index}`} className="flex gap-4">
                                        <span
                                            className={`mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full ${punch.type === 'entry' ? 'bg-positive' : 'bg-ink-subtle'}`}
                                            aria-hidden="true"
                                        />
                                        <span className="w-12 shrink-0 font-bold text-ink">{punch.time}</span>
                                        <span>
                                            <span className="block font-semibold text-ink">{punch.label}</span>
                                            <span className="block text-sm text-ink-muted">{punch.place}</span>
                                        </span>
                                    </li>
                                ))}
                            </ol>
                        ) : (
                            <p className="text-sm text-ink-muted">Bu kunda kirish-chiqish qayd etilmagan.</p>
                        )}
                    </section>

                    <section>
                        <h3 className="mb-3 font-bold text-ink">Shu kunga arizalar</h3>
                        {day.notes.length ? (
                            <ul className="space-y-2">
                                {day.notes.map((note) => (
                                    <li key={note.id} className="rounded-lg border border-line p-3">
                                        <p className="text-sm font-bold text-ink">{note.type_label}</p>
                                        <p className="text-sm text-ink-muted">{note.note}</p>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-sm text-ink-muted">Bu kunga ariza berilmagan.</p>
                        )}
                    </section>
                </div>
            )}
        </Drawer>
    );
}

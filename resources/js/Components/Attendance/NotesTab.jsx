import { EditOutlined } from '@ant-design/icons';
import { router } from '@inertiajs/react';
import { Empty, Tag, Tooltip } from 'antd';
import Pagination from '../Pagination';
import { DeleteButton } from '../PageHeader';
import { route } from '../../routes';

const TYPE_COLORS = { 1: 'warning', 2: 'processing', 3: 'error' };

/** "Arizalar" tabi: tanlangan davrdagi izohlar (ish vaqtida ruxsatlar). */
export default function NotesTab({ notes, canManage }) {
    if (!notes) {
        return null;
    }

    if (!notes.data.length) {
        return (
            <div className="py-12">
                <Empty description="Bu davrda ariza berilmagan" />
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-line text-left text-ink-muted">
                            <th className="py-3 pl-2 font-semibold">№</th>
                            <th className="py-3 font-semibold">Xodim</th>
                            <th className="py-3 font-semibold">Tur</th>
                            <th className="py-3 font-semibold">Sana</th>
                            <th className="py-3 font-semibold">Izoh</th>
                            {canManage && <th className="py-3 pr-2 text-right font-semibold">Amallar</th>}
                        </tr>
                    </thead>
                    <tbody>
                        {notes.data.map((note, index) => (
                            <tr key={note.id} className="border-b border-line last:border-0">
                                <td className="py-3 pl-2 text-ink-muted">{(notes.from ?? 1) + index}</td>
                                <td className="py-3 font-semibold text-ink">{note.employee?.name ?? '—'}</td>
                                <td className="py-3">
                                    <Tag color={TYPE_COLORS[note.type] ?? 'default'}>{note.type_label}</Tag>
                                </td>
                                <td className="whitespace-nowrap py-3 text-ink">{note.dt}</td>
                                <td className="py-3 text-ink-muted">{note.note}</td>
                                {canManage && (
                                    <td className="py-3 pr-2">
                                        <div className="flex items-center justify-end gap-2">
                                            <Tooltip title="Tahrirlash">
                                                <button
                                                    type="button"
                                                    className="table-action-edit"
                                                    aria-label="Tahrirlash"
                                                    onClick={() => router.visit(route('admin.notes.edit', note.id))}
                                                >
                                                    <EditOutlined aria-hidden="true" />
                                                </button>
                                            </Tooltip>
                                            <DeleteButton href={route('admin.notes.destroy', note.id)} />
                                        </div>
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pagination links={notes.links} total={notes.total} perPage={notes.per_page} />
        </div>
    );
}

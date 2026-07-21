import { usePage } from '@inertiajs/react';
import { App as AntApp } from 'antd';
import { useEffect } from 'react';

const STATUS_MAP = {
    create: 'Muvaffaqiyatli yaratildi.',
    update: 'Muvaffaqiyatli yangilandi.',
    delete: "Muvaffaqiyatli o'chirildi.",
    success: 'Muvaffaqiyatli amalga oshirildi.',
    error: 'Xatolik yuz berdi.',
    'profile-updated': 'Profil yangilandi.',
    'password-updated': 'Parol yangilandi.',
};

export default function FlashMessages() {
    const { flash } = usePage().props;
    const { notification } = AntApp.useApp();

    useEffect(() => {
        const messages = [
            flash?.error ? { type: 'error', text: flash.error } : null,
            flash?.success ? { type: 'success', text: flash.success } : null,
            flash?.status
                ? { type: 'success', text: STATUS_MAP[flash.status] || flash.status }
                : null,
        ].filter(Boolean);

        messages.forEach(({ type, text }) => {
            notification[type]({
                key: `flash-${type}-${text}`,
                message: type === 'success' ? 'Muvaffaqiyatli' : 'Xatolik',
                description: text,
                placement: 'topRight',
                duration: 3.5,
                showProgress: true,
                pauseOnHover: true,
            });
        });
    }, [flash?.error, flash?.status, flash?.success, notification]);

    return null;
}

import { router, usePage } from '@inertiajs/react';
import { ArrowRightOutlined } from '@ant-design/icons';
import { Drawer, Spin } from 'antd';
import { useState } from 'react';

/**
 * O‘ngdan ochiladigan panel holati. Ma’lumot sahifaning bitta propidan qisman qayta yuklash bilan keladi
 * (sahifa butunlay yangilanmaydi). Server propni Inertia::optional() bilan beradi va "key" qaytaradi.
 *
 * @param {string} prop   sahifa propi (masalan "members")
 * @param {string} param  URL parametri (masalan "devices"); berilmasa prop nomi
 */
export function usePartialPanel(prop, param = prop) {
    const data = usePage().props[prop] ?? null;
    const [requested, setRequested] = useState(null);
    const [loading, setLoading] = useState(false);

    const open = (key) => {
        setRequested(key);
        setLoading(true);
        router.reload({
            only: [prop],
            data: { [param]: key },
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setLoading(false),
        });
    };

    return {
        isOpen: requested !== null,
        loading: loading || (data?.key ?? null) !== requested,
        panel: data?.key === requested ? data : null,
        open,
        close: () => setRequested(null),
    };
}

/** Kompyuterda ekranning yarmi, telefonda butun ekran. */
function panelWidth() {
    if (typeof window === 'undefined') {
        return 720;
    }

    return window.innerWidth < 768 ? window.innerWidth : Math.max(Math.round(window.innerWidth / 2), 480);
}

/**
 * Umumiy o‘ng panel: sarlavha, pastki qator va kompyuterda chap chetdagi aylana yopish tugmasi.
 * Ma’lumot kelguncha aylanib turuvchi belgi ko‘rinadi.
 */
export default function SidePanel({ state, title, subtitle = null, footer = null, onClose, children }) {
    const close = () => {
        onClose?.();
        state.close();
    };

    return (
        <Drawer
            open={state.isOpen}
            onClose={close}
            placement="right"
            size={panelWidth()}
            rootClassName="side-panel"
            // Kompyuterda panel chap chetidagi aylana tugma bilan yopiladi (yarmi tashqarida), telefonda X bilan.
            drawerRender={(node) => (
                <div className="relative h-full">
                    {node}
                    <button
                        type="button"
                        aria-label="Panelni yopish"
                        onClick={close}
                        // Drawer qatlamlari pointer-events: none — tugma bosiladigan bo‘lishi uchun qaytariladi.
                        className="pointer-events-auto absolute -left-6 top-1/2 z-20 hidden h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-brand-600 text-lg text-white shadow-lg ring-4 ring-surface transition-colors duration-150 ease-productive hover:bg-brand-700 focus-visible:outline-none focus-visible:ring-brand-200 sm:flex"
                    >
                        <ArrowRightOutlined aria-hidden="true" />
                    </button>
                </div>
            )}
            title={(
                <div className="min-w-0">
                    <p className="truncate text-base font-extrabold text-ink">{title}</p>
                    {subtitle ? <p className="text-xs font-semibold text-ink-muted">{subtitle}</p> : null}
                </div>
            )}
            footer={footer}
        >
            {state.loading || !state.panel ? (
                <div className="flex justify-center py-16">
                    <Spin />
                </div>
            ) : children}
        </Drawer>
    );
}

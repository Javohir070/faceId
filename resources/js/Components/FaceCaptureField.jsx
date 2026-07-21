import { useEffect, useRef, useState } from 'react';
import { CameraOutlined, CloseOutlined, UploadOutlined } from '@ant-design/icons';

export default function FaceCaptureField({
    value = null,
    existingPreview = null,
    removeFace = false,
    required = false,
    error = null,
    onChange,
    onRemoveChange,
}) {
    const fileInputRef = useRef(null);
    const [localPreview, setLocalPreview] = useState(null);
    const [fileName, setFileName] = useState('');

    useEffect(() => {
        if (value) {
            const objectUrl = URL.createObjectURL(value);
            setLocalPreview(objectUrl);
            setFileName(value.name || 'face.jpg');
            return () => URL.revokeObjectURL(objectUrl);
        }

        setLocalPreview(null);
        setFileName('');
        return undefined;
    }, [value]);

    const applyFile = (file) => {
        if (!file) return;
        onRemoveChange?.(false);
        onChange?.(file);
    };

    const handleFilePick = (event) => {
        const file = event.target.files?.[0];
        if (file) {
            applyFile(file);
        }
        event.target.value = '';
    };

    const handleClear = () => {
        onChange?.(null);
        onRemoveChange?.(true);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const showExisting = !value && !removeFace && existingPreview;
    const previewSrc = localPreview || (showExisting ? existingPreview : null);
    const hasImage = Boolean(previewSrc);

    return (
        <div className="flex h-full flex-col">
            <div className="mb-4 flex items-start justify-between gap-3">
                <div>
                    <p className="text-sm font-bold text-ink">
                        Yuz rasmi
                        {required ? <span className="ml-1 text-negative" aria-hidden="true">*</span> : null}
                    </p>
                    <p className="mt-1 text-xs font-medium leading-5 text-ink-subtle">
                        Yuz rasmini fayl sifatida yuklang.
                    </p>
                </div>
                {hasImage ? (
                    <span className="shrink-0 rounded-full bg-brand-100 px-2.5 py-1 text-[11px] font-extrabold uppercase tracking-wide text-brand-800">
                        Tayyor
                    </span>
                ) : (
                    <span className="shrink-0 rounded-full bg-surface-muted px-2.5 py-1 text-[11px] font-extrabold uppercase tracking-wide text-ink-subtle">
                        Bo‘sh
                    </span>
                )}
            </div>

            <div className="relative mb-5 flex min-h-[320px] flex-1 items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed border-line-strong bg-canvas">
                {previewSrc ? (
                    <img
                        src={previewSrc}
                        alt="Yuz rasmi"
                        className="absolute inset-0 h-full w-full object-cover"
                    />
                ) : (
                    <div className="flex flex-col items-center gap-3 px-6 text-center text-ink-subtle">
                        <span className="flex h-16 w-16 items-center justify-center rounded-full bg-surface ring-1 ring-line">
                            <CameraOutlined className="text-3xl" />
                        </span>
                        <div>
                            <p className="text-sm font-extrabold uppercase tracking-wider text-ink-muted">NO IMAGE</p>
                            <p className="mt-1 text-xs font-medium">Hali rasm tanlanmagan</p>
                        </div>
                    </div>
                )}
            </div>

            <div className="space-y-3">
                <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/jpeg,image/png,image/jpg"
                    className="hidden"
                    onChange={handleFilePick}
                />

                <div className="flex gap-2">
                    <button
                        type="button"
                        className="min-w-0 flex-1 truncate rounded-lg border border-line bg-surface px-3 py-2.5 text-left text-sm font-medium text-ink-muted hover:border-line-strong"
                        onClick={() => fileInputRef.current?.click()}
                    >
                        {fileName || 'Faylni tanlang'}
                    </button>
                    <button
                        type="button"
                        className="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-brand-700"
                        onClick={() => fileInputRef.current?.click()}
                    >
                        <UploadOutlined />
                        Yuklash
                    </button>
                </div>

                <button
                    type="button"
                    className="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-line bg-surface px-3 py-3 text-sm font-bold text-ink hover:bg-canvas"
                    onClick={handleClear}
                >
                    <CloseOutlined />
                    Tozalash
                </button>

                {error ? (
                    <p className="text-sm font-semibold text-negative">{error}</p>
                ) : null}
            </div>
        </div>
    );
}

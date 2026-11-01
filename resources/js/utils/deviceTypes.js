/** Qurilma turlari (App\Models\Device::TYPE_*). */
export const DEVICE_TYPE_OPTIONS = [
    { value: 1, label: 'Kirish' },
    { value: 2, label: 'Chiqish' },
    { value: 3, label: 'Kirish va chiqish' },
];

/** Tanlangan tur o‘tishni qanday yozishini tushuntiradi (ReportService::resolveType bilan bir xil). */
export const DEVICE_TYPE_HINTS = {
    1: 'Har bir o‘tish kirish deb yoziladi. Terminalda «Ishga keldim / Chiqdim» tugmasi bosilsa, tugma hal qiladi.',
    2: 'Har bir o‘tish chiqish deb yoziladi. Terminalda «Ishga keldim / Chiqdim» tugmasi bosilsa, tugma hal qiladi.',
    3: 'Bitta terminal ikkalasi uchun. Tugma bosilsa — tugma hal qiladi; bosilmasa — kun ichida navbat bilan: kirish → chiqish → kirish… Har ish kuni kirishdan boshlanadi, 2 daqiqa ichida qayta tanilsa navbat almashmaydi.',
};

export const DEVICE_TYPE_TONES = { 1: 'info', 2: 'warning', 3: 'success' };

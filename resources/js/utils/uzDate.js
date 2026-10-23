/** O‘zbekcha hafta kuni va oy nomlari (Date.getDay() / getMonth() tartibida). */
export const WEEKDAYS = ['Yakshanba', 'Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba'];
export const MONTHS = [
    'yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun',
    'iyul', 'avgust', 'sentabr', 'oktabr', 'noyabr', 'dekabr',
];

/** '2026-10-13' → 'Seshanba, 13-oktabr 2026' (vaqt zonasi siljimasligi uchun sana qismlaridan yig‘iladi). */
export function formatDayTitle(isoDate) {
    const [year, month, day] = String(isoDate).slice(0, 10).split('-').map(Number);
    const date = new Date(year, month - 1, day);

    return `${WEEKDAYS[date.getDay()]}, ${day}-${MONTHS[month - 1]} ${year}`;
}

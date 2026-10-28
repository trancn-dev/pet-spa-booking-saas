// Formatting in the shop's language and timezone (design 10, rule 4: times follow the shop).

const money = new Intl.NumberFormat('vi-VN');

/** "1.250.000 ₫" from a DECIMAL string. */
export const formatMoney = (value: string | number | null | undefined): string => `${money.format(Number(value ?? 0))} ₫`;

/** "28/10/2026" from a `YYYY-MM-DD` date (no timezone shift: it already is a shop-local day). */
export const formatDay = (day: string | null | undefined): string => {
    if (!day) return '—';
    const [y, m, d] = day.slice(0, 10).split('-');
    return `${d}/${m}/${y}`;
};

/** "28/10/2026 14:30" from an ISO instant, shown in the shop's timezone. */
export const formatDateTime = (iso: string | null | undefined, timeZone?: string | null): string => {
    if (!iso) return '—';
    return new Intl.DateTimeFormat('vi-VN', {
        timeZone: timeZone ?? undefined,
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));
};

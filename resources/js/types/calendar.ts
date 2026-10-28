import type { PetAlert } from '@/types/pets';

export type Group = 'medical' | 'grooming';

export interface CalendarBlock {
    key: string;
    appointment: string;
    group: Group;
    staff_id: number | null;
    start: string;
    end: string;
    service_end: string;
    status: string;
    line_status: string;
    source: 'online' | 'staff';
    customer: string;
    preferred: string | null;
    no_reminders: boolean;
    linked: boolean;
    pets: { name: string; service: string; status: string; alerts: PetAlert[] }[];
}

export interface CalendarStaff {
    id: number;
    name: string;
    title: string | null;
    color: string | null;
    groups: Group[];
    slots: { start: string; end: string; group: Group | null }[];
    off: { start: string; end: string; reason: string | null }[];
    count: number;
}

export interface CapacityCell {
    time: string;
    medical: { taken: number; on_duty: number };
    grooming: { taken: number; on_duty: number };
}

export interface CalendarDayData {
    date: string;
    weekday: number;
    closed: { reason: string | null; whole_shop: boolean } | null;
    open: { start: string; end: string }[];
    staff: CalendarStaff[];
    blocks: CalendarBlock[];
    capacity: CapacityCell[];
}

export interface ServiceOption {
    id: number;
    code: string;
    name: string;
    group: Group;
    is_combo: boolean;
    price: string;
}

/** "08:30" → minutes after midnight. */
export const minutes = (time: string): number => {
    const [h, m] = time.split(':').map(Number);
    return h * 60 + m;
};

export const hhmm = (mins: number): string => `${String(Math.floor(mins / 60)).padStart(2, '0')}:${String(mins % 60).padStart(2, '0')}`;

export const groupLabel: Record<Group, string> = { medical: 'Khám', grooming: 'Spa' };

<script setup lang="ts">
import CalendarBlock from '@/components/appointments/CalendarBlock.vue';
import CapacityBar from '@/components/appointments/CapacityBar.vue';
import { type CalendarBlock as BlockData, type CapacityCell, hhmm, minutes } from '@/types/calendar';
import { computed, ref } from 'vue';

/*
| S1 grid, hand-made with CSS (no calendar library): one column per person (or group), a 15-minute
| row height, blocks positioned by time. Overlapping blocks in a column share it side by side.
*/

export interface GridColumn {
    key: string;
    label: string;
    sub?: string;
    color?: string | null;
    staffId?: number | null;
    /** Times this column is on duty; outside them the column is striped. Undefined = no roster. */
    duty?: { start: string; end: string }[];
    off?: { start: string; end: string; reason: string | null }[];
    blocks: BlockData[];
}

const props = defineProps<{
    columns: GridColumn[];
    from: string;
    to: string;
    open: { start: string; end: string }[];
    closed: { reason: string | null } | null;
    capacity: CapacityCell[];
    now: string | null;
    staffColors: Record<number, string | null>;
}>();

const emit = defineEmits<{
    cell: [column: GridColumn, time: string];
    block: [block: BlockData];
    move: [block: BlockData, column: GridColumn, time: string];
}>();

const ROW = 18; // px per 15 minutes
const start = computed(() => minutes(props.from));
const end = computed(() => minutes(props.to));
const rows = computed(() => (end.value - start.value) / 15);
const height = computed(() => rows.value * ROW);
const y = (time: string) => ((Math.min(Math.max(minutes(time), start.value), end.value) - start.value) / 15) * ROW;
const span = (from: string, to: string) => ({ top: `${y(from)}px`, height: `${Math.max(y(to) - y(from), ROW / 2)}px` });

const hours = computed(() => {
    const list: string[] = [];
    for (let m = start.value; m < end.value; m += 60) list.push(hhmm(m));
    return list;
});

// Closed parts of the day: before, between and after opening hours.
const shut = computed(() => {
    const open = [...props.open].sort((a, b) => minutes(a.start) - minutes(b.start));
    const parts: { start: string; end: string }[] = [];
    let cursor = props.from;
    for (const o of open) {
        if (minutes(o.start) > minutes(cursor)) parts.push({ start: cursor, end: o.start });
        if (minutes(o.end) > minutes(cursor)) cursor = o.end;
    }
    if (minutes(cursor) < end.value) parts.push({ start: cursor, end: props.to });
    return props.closed ? [{ start: props.from, end: props.to }] : parts;
});

const offDuty = (column: GridColumn) => {
    if (!column.duty) return [];
    const duty = [...column.duty].sort((a, b) => minutes(a.start) - minutes(b.start));
    const parts: { start: string; end: string }[] = [];
    let cursor = props.from;
    for (const d of duty) {
        if (minutes(d.start) > minutes(cursor)) parts.push({ start: cursor, end: d.start });
        if (minutes(d.end) > minutes(cursor)) cursor = d.end;
    }
    if (minutes(cursor) < end.value) parts.push({ start: cursor, end: props.to });
    return parts;
};

/** Lanes: overlapping blocks share the column width. */
const laid = (blocks: BlockData[]) => {
    const sorted = [...blocks].sort((a, b) => minutes(a.start) - minutes(b.start) || minutes(b.end) - minutes(a.end));
    const laneEnds: number[] = [];
    const placed = sorted.map((block) => {
        let lane = laneEnds.findIndex((e) => e <= minutes(block.start));
        if (lane === -1) lane = laneEnds.push(0) - 1;
        laneEnds[lane] = minutes(block.end);
        return { block, lane };
    });
    const lanes = Math.max(1, laneEnds.length);
    return placed.map((p) => ({ ...p, lanes }));
};

const capacityAt = computed(() => new Map(props.capacity.map((c) => [c.time, c])));
const over = (c: CapacityCell) => c.medical.taken > c.medical.on_duty || c.grooming.taken > c.grooming.on_duty;

const clickCell = (column: GridColumn, event: MouseEvent) => {
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    const row = Math.floor((event.clientY - rect.top) / ROW);
    emit('cell', column, hhmm(start.value + row * 15));
};

// Drag to reschedule: the drop target is previewed per 15-minute row.
const dragging = ref<BlockData | null>(null);
const hover = ref<{ column: string; time: string } | null>(null);
const movable = (b: BlockData) => b.line_status === 'booked';
const rowAt = (event: DragEvent) => {
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    return hhmm(start.value + Math.max(0, Math.floor((event.clientY - rect.top) / ROW)) * 15);
};
const dragOver = (column: GridColumn, event: DragEvent) => {
    if (!dragging.value) return;
    event.preventDefault();
    hover.value = { column: column.key, time: rowAt(event) };
};
const drop = (column: GridColumn, event: DragEvent) => {
    const block = dragging.value;
    const time = rowAt(event);
    dragging.value = null;
    hover.value = null;
    if (block) emit('move', block, column, time);
};
const endDrag = () => {
    dragging.value = null;
    hover.value = null;
};
/** Warning text when the hovered cell is already full for the dragged block's group. */
const fullAt = (time: string) => {
    const b = dragging.value;
    const cell = capacityAt.value.get(time);
    if (!b || !cell) return null;
    const c = cell[b.group];
    return c.on_duty > 0 && c.taken >= c.on_duty ? `Đã có ${c.taken} lịch ${b.group === 'medical' ? 'khám' : 'spa'} / ${c.on_duty} người trực` : null;
};
const ghost = (column: GridColumn) => {
    const b = dragging.value;
    if (!b || hover.value?.column !== column.key) return null;
    const length = minutes(b.end) - minutes(b.start);
    return span(hover.value.time, hhmm(Math.min(minutes(hover.value.time) + length, end.value)));
};
const keyCell = (column: GridColumn, time: string) => emit('cell', column, time);
</script>

<template>
    <div class="relative overflow-auto rounded-[10px] border bg-card" role="grid" aria-label="Lịch hẹn">
        <div class="grid min-w-max" :style="{ gridTemplateColumns: `6.5rem repeat(${columns.length}, minmax(9.5rem, 1fr))` }">
            <!-- header -->
            <div class="sticky top-0 z-20 border-b bg-surface-muted" />
            <div v-for="c in columns" :key="`h-${c.key}`" class="sticky top-0 z-20 border-b border-l bg-surface-muted px-2 py-2" role="columnheader">
                <div class="flex items-center gap-1.5 text-sm font-medium">
                    <span v-if="c.color !== undefined" class="size-2.5 shrink-0 rounded-full" :style="{ backgroundColor: c.color ?? '#94a3b8' }" />
                    <span class="truncate">{{ c.label }}</span>
                </div>
                <div v-if="c.sub" class="truncate text-xs text-muted-foreground">{{ c.sub }}</div>
            </div>

            <!-- time axis + capacity per hour -->
            <div class="relative border-r" :style="{ height: `${height}px` }">
                <div v-for="h in hours" :key="h" class="absolute inset-x-0 px-1 text-[11px] leading-tight" :style="{ top: `${y(h)}px` }">
                    <div class="font-medium tabular-nums">{{ h }}</div>
                    <CapacityBar v-if="capacityAt.get(h)" :cell="capacityAt.get(h)!" />
                </div>
            </div>

            <!-- columns -->
            <div
                v-for="c in columns"
                :key="c.key"
                class="relative cursor-cell border-l"
                :style="{
                    height: `${height}px`,
                    backgroundImage: `repeating-linear-gradient(to bottom, transparent 0, transparent ${ROW * 4 - 1}px, hsl(var(--border)) ${ROW * 4 - 1}px, hsl(var(--border)) ${ROW * 4}px)`,
                }"
                role="gridcell"
                tabindex="0"
                :aria-label="`${c.label}: bấm để đặt lịch`"
                @click.self="clickCell(c, $event)"
                @dragover="dragOver(c, $event)"
                @drop.prevent="drop(c, $event)"
                @keydown.enter.self="keyCell(c, '09:00')"
            >
                <!-- closed / off duty / off -->
                <div v-for="(s, i) in shut" :key="`s${i}`" class="striped pointer-events-none absolute inset-x-0" :style="span(s.start, s.end)" />
                <div
                    v-for="(s, i) in offDuty(c)"
                    :key="`d${i}`"
                    class="striped pointer-events-none absolute inset-x-0 opacity-60"
                    :style="span(s.start, s.end)"
                />
                <div
                    v-for="(o, i) in c.off ?? []"
                    :key="`o${i}`"
                    class="pointer-events-none absolute inset-x-1 rounded border border-dashed border-muted-foreground/40 bg-muted/70 px-1.5 py-0.5 text-xs text-muted-foreground"
                    :style="span(o.start, o.end)"
                >
                    Nghỉ<template v-if="o.reason">: {{ o.reason }}</template>
                </div>

                <!-- over-capacity rows -->
                <div
                    v-for="cell in capacity.filter(over)"
                    :key="`w${cell.time}`"
                    class="pointer-events-none absolute inset-x-0 border-y border-warning/50 bg-warning/5"
                    :style="span(cell.time, hhmm(minutes(cell.time) + 15))"
                />

                <!-- drop preview -->
                <div
                    v-if="ghost(c)"
                    class="pointer-events-none absolute inset-x-1 z-10 rounded-md border-2 border-dashed px-1 text-xs"
                    :class="fullAt(hover!.time) ? 'border-warning bg-warning/20 text-warning' : 'border-primary bg-primary-soft text-primary'"
                    :style="ghost(c)!"
                >
                    {{ hover!.time }}<template v-if="fullAt(hover!.time)"> · {{ fullAt(hover!.time) }}</template>
                </div>

                <!-- blocks -->
                <CalendarBlock
                    v-for="{ block, lane, lanes } in laid(c.blocks)"
                    :key="block.key"
                    :block="block"
                    :color="block.staff_id ? staffColors[block.staff_id] : null"
                    :buffer-px="block.service_end < block.end ? y(block.end) - y(block.service_end) : 0"
                    :draggable="movable(block)"
                    :style="{ ...span(block.start, block.end), left: `calc(${(lane / lanes) * 100}% + 2px)`, width: `calc(${100 / lanes}% - 4px)` }"
                    @click="emit('block', block)"
                    @dragstart="dragging = block"
                    @dragend="endDrag"
                />

                <!-- now -->
                <div
                    v-if="now && minutes(now) >= minutes(from) && minutes(now) <= minutes(to)"
                    class="pointer-events-none absolute inset-x-0 z-10 h-0.5 bg-danger"
                    :style="{ top: `${y(now)}px` }"
                    aria-hidden="true"
                />
            </div>
        </div>

        <div v-if="closed" class="pointer-events-none absolute inset-0 top-12 grid place-items-center">
            <div class="rounded-[10px] border bg-card px-4 py-2 text-sm font-medium shadow">
                Cơ sở nghỉ<template v-if="closed.reason">: {{ closed.reason }}</template>
            </div>
        </div>
    </div>
</template>

<style scoped>
.striped {
    background-image: repeating-linear-gradient(135deg, hsl(var(--muted)) 0 6px, transparent 6px 12px);
}
</style>

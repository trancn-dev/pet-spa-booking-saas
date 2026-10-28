<script setup lang="ts" generic="Row extends Record<string, any>">
import { Skeleton } from '@/components/ui/skeleton';

/*
| Design K0 "Table": sticky grey header (12/600), 52px rows, divider lines, hover, rounded 12px border.
| Columns set the grid widths; cells are slots `cell-{key}` (default prints `row[key]`); `rowClass` for state tints;
| `loading` shows skeleton rows; the `empty` slot replaces the body when there are no rows.
*/
export interface Column {
    key: string;
    label: string;
    /** CSS grid track, e.g. "2fr", "120px" */
    width?: string;
    align?: 'left' | 'right' | 'center';
}

const props = withDefaults(
    defineProps<{
        columns: Column[];
        rows: Row[];
        rowKey?: keyof Row | ((row: Row) => string | number);
        rowClass?: (row: Row) => string | undefined;
        loading?: boolean;
        rowHeight?: number;
    }>(),
    {
        rowKey: undefined,
        rowClass: undefined,
        loading: false,
        rowHeight: 52,
    },
);
const emit = defineEmits<{ rowClick: [row: Row] }>();

const grid = () => props.columns.map((c) => c.width ?? '1fr').join(' ');
const keyOf = (row: Row, i: number) =>
    typeof props.rowKey === 'function' ? props.rowKey(row) : props.rowKey ? (row[props.rowKey] as string | number) : i;
const align = (c: Column) => (c.align === 'right' ? 'text-right' : c.align === 'center' ? 'text-center' : 'text-left');
</script>

<template>
    <div class="overflow-x-auto rounded-xl border bg-card" role="table">
        <div class="min-w-max">
            <div
                class="sticky top-0 z-10 grid h-10 items-center bg-surface-muted text-xs font-semibold text-muted-foreground"
                :style="{ gridTemplateColumns: grid() }"
                role="row"
            >
                <span v-for="c in columns" :key="c.key" class="px-4" :class="align(c)" role="columnheader">{{ c.label }}</span>
            </div>

            <template v-if="loading">
                <div
                    v-for="n in 5"
                    :key="n"
                    class="grid items-center border-t"
                    :style="{ gridTemplateColumns: grid(), minHeight: `${rowHeight}px` }"
                    aria-hidden="true"
                >
                    <div v-for="c in columns" :key="c.key" class="px-4"><Skeleton class="h-4 w-3/4" /></div>
                </div>
            </template>
            <template v-else-if="rows.length">
                <div
                    v-for="(row, i) in rows"
                    :key="keyOf(row, i)"
                    class="grid items-center border-t text-sm leading-5 transition-colors hover:bg-surface-muted/60"
                    :class="[rowClass?.(row), { 'cursor-pointer': $attrs.onRowClick }]"
                    :style="{ gridTemplateColumns: grid(), minHeight: `${rowHeight}px` }"
                    role="row"
                    @click="emit('rowClick', row)"
                >
                    <div v-for="c in columns" :key="c.key" class="min-w-0 px-4 py-1.5" :class="align(c)" role="cell">
                        <slot :name="`cell-${c.key}`" :row="row" :value="row[c.key]">{{ row[c.key] }}</slot>
                    </div>
                </div>
            </template>
            <div v-else class="border-t p-6"><slot name="empty" /></div>
        </div>
    </div>
</template>

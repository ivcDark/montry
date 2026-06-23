<script setup lang="ts">
import { computed, ref, watch } from 'vue'

type IntervalUnit = 'minutes' | 'days'

const props = withDefaults(defineProps<{
    modelValue: number
    minimumMinutes: number
    maximumMinutes?: number
    inputId: string
    unit?: IntervalUnit
}>(), {
    maximumMinutes: 1440,
    unit: 'minutes',
})

const emit = defineEmits<{
    'update:modelValue': [value: number]
}>()

const secondsPerUnit = computed(() => props.unit === 'days' ? 86400 : 60)
const unitLabel = computed(() => props.unit === 'days' ? '\u0434\u043d' : '\u043c\u0438\u043d')
const minimumValue = computed(() => props.unit === 'days'
    ? Math.max(1, Math.ceil(props.minimumMinutes / 1440))
    : props.minimumMinutes)
const maximumValue = computed(() => props.unit === 'days'
    ? Math.max(minimumValue.value, Math.floor(props.maximumMinutes / 1440))
    : props.maximumMinutes)

const stops = computed(() => {
    if (props.unit === 'days') {
        return Array.from(
            { length: maximumValue.value - minimumValue.value + 1 },
            (_, index) => minimumValue.value + index,
        )
    }

    const values = new Set<number>([minimumValue.value, maximumValue.value])

    addStopRange(values, 1, 15, 1)
    addStopRange(values, 20, 60, 5)
    addStopRange(values, 90, 360, 30)
    addStopRange(values, 420, maximumValue.value, 60)

    return Array.from(values)
        .filter((minutes) => minutes >= minimumValue.value && minutes <= maximumValue.value)
        .sort((a, b) => a - b)
})

const labelValues = computed(() => {
    if (props.unit === 'days') {
        return stops.value
    }

    return Array.from(new Set([
        minimumValue.value,
        15,
        60,
        360,
        720,
        maximumValue.value,
    ])).filter((minutes) => stops.value.includes(minutes))
})

const selectedValue = computed(() => Math.round(props.modelValue / secondsPerUnit.value))
const manualValue = ref<number | null>(selectedValue.value)

watch(selectedValue, (value) => {
    manualValue.value = value
})

const sliderIndex = computed(() => {
    let nearestIndex = 0
    let nearestDistance = Number.POSITIVE_INFINITY

    stops.value.forEach((value, index) => {
        const distance = Math.abs(value - selectedValue.value)

        if (distance < nearestDistance) {
            nearestDistance = distance
            nearestIndex = index
        }
    })

    return nearestIndex
})

const intervalLabel = computed(() => props.unit === 'days'
    ? formatDayInterval(selectedValue.value)
    : formatMinuteInterval(selectedValue.value))
const helperText = computed(() => props.unit === 'days'
    ? `\u0414\u0438\u0430\u043f\u0430\u0437\u043e\u043d: ${minimumValue.value}-${maximumValue.value} \u0434\u043d.`
    : `\u041c\u0438\u043d\u0438\u043c\u0443\u043c \u043f\u043e \u0442\u0430\u0440\u0438\u0444\u0443: ${minimumValue.value} \u043c\u0438\u043d`)

function addStopRange(values: Set<number>, start: number, end: number, step: number): void {
    const boundedEnd = Math.min(end, maximumValue.value)

    for (let minutes = start; minutes <= boundedEnd; minutes += step) {
        values.add(minutes)
    }
}

function labelPosition(value: number): string {
    const index = stops.value.indexOf(value)
    const denominator = Math.max(stops.value.length - 1, 1)

    return `${(index / denominator) * 100}%`
}

function formatMinuteInterval(minutes: number): string {
    if (minutes === 60) return '\u041a\u0430\u0436\u0434\u044b\u0439 \u0447\u0430\u0441'
    if (minutes === 1440) return '\u0420\u0430\u0437 \u0432 \u0434\u0435\u043d\u044c'
    if (minutes > 60 && minutes % 60 === 0) return `\u041a\u0430\u0436\u0434\u044b\u0435 ${minutes / 60} \u0447`

    return `\u041a\u0430\u0436\u0434\u044b\u0435 ${minutes} \u043c\u0438\u043d`
}

function formatDayInterval(days: number): string {
    if (days === 1) return '\u0420\u0430\u0437 \u0432 \u0434\u0435\u043d\u044c'

    return `\u0420\u0430\u0437 \u0432 ${days} \u0434\u043d.`
}

function stopLabel(value: number): string {
    if (props.unit === 'days') return `${value} \u0434`
    if (value === 60) return '1 \u0447'
    if (value === 1440) return '1 \u0434'
    if (value > 60 && value % 60 === 0) return `${value / 60} \u0447`

    return `${value} \u043c`
}

function emitValue(value: number): void {
    emit('update:modelValue', value * secondsPerUnit.value)
}

function updateFromSlider(event: Event): void {
    const index = Number((event.target as HTMLInputElement).value)
    const value = stops.value[index] ?? minimumValue.value

    manualValue.value = value
    emitValue(value)
}

function updateManualValue(): void {
    const value = Number(manualValue.value)

    if (!Number.isFinite(value) || value < minimumValue.value || value > maximumValue.value) return

    emitValue(Math.round(value))
}

function commitManualValue(): void {
    const value = Number(manualValue.value)
    const intervalValue = Number.isFinite(value)
        ? Math.min(maximumValue.value, Math.max(minimumValue.value, Math.round(value)))
        : selectedValue.value

    manualValue.value = intervalValue
    emitValue(intervalValue)
}
</script>

<template>
    <div>
        <div class="mb-3 flex items-center justify-between gap-3">
            <label :for="inputId" class="text-sm font-semibold text-[#26332D]">&#1063;&#1072;&#1089;&#1090;&#1086;&#1090;&#1072; &#1087;&#1088;&#1086;&#1074;&#1077;&#1088;&#1082;&#1080;</label>
            <span class="text-sm font-bold text-[#1E9B5D]">{{ intervalLabel }}</span>
        </div>

        <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_112px] sm:items-start">
            <div class="min-w-0 pt-1">
                <input
                    :id="`${inputId}-slider`"
                    type="range"
                    min="0"
                    :max="Math.max(stops.length - 1, 0)"
                    step="1"
                    :value="sliderIndex"
                    class="interval-range w-full"
                    aria-label="&#1048;&#1085;&#1090;&#1077;&#1088;&#1074;&#1072;&#1083; &#1087;&#1088;&#1086;&#1074;&#1077;&#1088;&#1082;&#1080;"
                    @input="updateFromSlider"
                >
                <div class="relative mt-2.5 h-5 text-[11px] font-medium tracking-[-0.01em] text-[#7B8B82]">
                    <span
                        v-for="value in labelValues"
                        :key="value"
                        class="interval-mark absolute top-0 -translate-x-1/2 whitespace-nowrap"
                        :style="{ left: labelPosition(value) }"
                    >
                        {{ stopLabel(value) }}
                    </span>
                </div>
            </div>

            <div>
                <div class="relative">
                    <input
                        :id="inputId"
                        v-model.number="manualValue"
                        type="number"
                        inputmode="numeric"
                        :min="minimumValue"
                        :max="maximumValue"
                        step="1"
                        class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white pl-4 pr-12 text-sm font-semibold text-[#26332D] outline-none transition focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15"
                        @input="updateManualValue"
                        @blur="commitManualValue"
                        @keydown.enter.prevent="commitManualValue"
                    >
                    <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-xs font-semibold text-[#8A9A91]">{{ unitLabel }}</span>
                </div>
                <p class="mt-2 text-[11px] leading-4 text-[#8A9A91]">{{ helperText }}</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.interval-range {
    height: 20px;
    cursor: pointer;
    appearance: none;
    background: transparent;
}

.interval-range::-webkit-slider-runnable-track {
    height: 5px;
    border-radius: 9999px;
    background: #d8e5dd;
}

.interval-range::-webkit-slider-thumb {
    width: 18px;
    height: 18px;
    margin-top: -6.5px;
    appearance: none;
    border: 3px solid #fff;
    border-radius: 9999px;
    background: #2fa568;
    box-shadow: 0 1px 4px rgb(23 59 42 / 24%);
}

.interval-range::-moz-range-track {
    height: 5px;
    border-radius: 9999px;
    background: #d8e5dd;
}

.interval-range::-moz-range-thumb {
    width: 12px;
    height: 12px;
    border: 3px solid #fff;
    border-radius: 9999px;
    background: #2fa568;
    box-shadow: 0 1px 4px rgb(23 59 42 / 24%);
}

.interval-range:focus-visible {
    outline: 3px solid rgb(47 165 104 / 18%);
    outline-offset: 3px;
    border-radius: 9999px;
}

.interval-mark:first-child {
    transform: translateX(0);
}

.interval-mark:last-child {
    transform: translateX(-100%);
}
</style>

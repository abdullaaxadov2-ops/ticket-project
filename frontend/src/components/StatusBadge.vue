<script setup>
import { computed } from 'vue'
import { STATUSES } from '@/utils/format'

const props = defineProps({
  // event | order | ticket
  kind: { type: String, required: true },
  status: { type: String, required: true },
})

// Классы целиком, а не через шаблонную строку: иначе Tailwind их не найдёт при сборке
const COLORS = {
  green: 'bg-green-100 text-green-800',
  yellow: 'bg-amber-100 text-amber-800',
  red: 'bg-red-100 text-red-800',
  blue: 'bg-blue-100 text-blue-800',
  gray: 'bg-slate-100 text-slate-700',
}

const info = computed(() => STATUSES[props.kind]?.[props.status] ?? { label: props.status, color: 'gray' })
</script>

<template>
  <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium" :class="COLORS[info.color]">
    {{ info.label }}
  </span>
</template>

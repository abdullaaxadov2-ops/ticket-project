<script setup>
// Работает с пагинатором Laravel: передаём current_page и last_page
defineProps({
  page: { type: Number, required: true },
  lastPage: { type: Number, required: true },
})

defineEmits(['change'])
</script>

<template>
  <div v-if="lastPage > 1" class="mt-8 flex items-center justify-center gap-4 text-sm">
    <button type="button" class="btn btn-secondary" :disabled="page <= 1" @click="$emit('change', page - 1)">
      ← Назад
    </button>
    <span class="text-slate-600">Страница {{ page }} из {{ lastPage }}</span>
    <button type="button" class="btn btn-secondary" :disabled="page >= lastPage" @click="$emit('change', page + 1)">
      Вперёд →
    </button>
  </div>
</template>

<script setup>
// Laravel возвращает по каждому полю массив сообщений, показываем первое
defineProps({
  messages: { type: Array, default: undefined },
})
</script>

<template>
  <p v-if="messages?.length" class="mt-1 text-xs text-red-600">{{ messages[0] }}</p>
</template>

// Laravel отдаёт даты с микросекундами ("2026-10-23T10:00:00.000000Z"), обрезаем до миллисекунд
export function parseDate(value) {
  if (!value) {
    return null
  }

  return new Date(String(value).replace(/(\.\d{3})\d+/, '$1'))
}

const dateTimeFormat = new Intl.DateTimeFormat('ru-RU', {
  day: 'numeric',
  month: 'long',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
})

const timeFormat = new Intl.DateTimeFormat('ru-RU', { hour: '2-digit', minute: '2-digit' })

export function formatDateTime(value) {
  const date = parseDate(value)

  return date ? dateTimeFormat.format(date) : '—'
}

export function formatTime(value) {
  const date = parseDate(value)

  return date ? timeFormat.format(date) : '—'
}

const moneyFormat = new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 2 })

// Деньги приходят строкой "150000.00" (decimal:2 в моделях)
export function formatMoney(value) {
  if (value === null || value === undefined) {
    return '—'
  }

  return `${moneyFormat.format(Number(value))} сум`
}

export const ROLE_LABELS = {
  admin: 'Администратор',
  organiser: 'Организатор',
  participant: 'Участник',
}

// Значения совпадают с Enums на бэкенде (EventStatus, OrderStatus, TicketStatus)
export const STATUSES = {
  event: {
    draft: { label: 'Черновик', color: 'gray' },
    published: { label: 'Опубликовано', color: 'green' },
    cancelled: { label: 'Отменено', color: 'red' },
    finished: { label: 'Завершено', color: 'gray' },
  },
  order: {
    pending: { label: 'Ожидает оплаты', color: 'yellow' },
    paid: { label: 'Оплачен', color: 'green' },
    cancelled: { label: 'Отменён', color: 'red' },
    expired: { label: 'Просрочен', color: 'gray' },
  },
  ticket: {
    active: { label: 'Действует', color: 'green' },
    used: { label: 'Использован', color: 'blue' },
    cancelled: { label: 'Аннулирован', color: 'red' },
  },
}

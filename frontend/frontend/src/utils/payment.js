import { ordersApi } from '@/api/orders'

const STORAGE_KEY = 'pendingOrderId'

// success_url кассы — одна страница на все заказы, номер заказа платёжка обратно не передаёт.
// Поэтому запоминаем его перед уходом на оплату и читаем на странице /payment/result
export function goToPayment(orderId, url) {
  try {
    sessionStorage.setItem(STORAGE_KEY, String(orderId))
  } catch {
    // Без sessionStorage (приватный режим) страница результата попросит открыть заказ вручную
  }

  window.location.href = url
}

export async function startPayment(orderId) {
  const url = await ordersApi.pay(orderId)

  goToPayment(orderId, url)
}

export function pendingOrderId() {
  try {
    return sessionStorage.getItem(STORAGE_KEY)
  } catch {
    return null
  }
}

export function forgetPendingOrder() {
  try {
    sessionStorage.removeItem(STORAGE_KEY)
  } catch {
    // ничего страшного
  }
}

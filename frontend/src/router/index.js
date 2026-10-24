import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

// meta.auth — только для вошедших, meta.guestOnly — только для гостей (вход, регистрация)
const routes = [
  { path: '/', name: 'events', component: () => import('@/views/EventsView.vue') },
  { path: '/events/:id', name: 'event', component: () => import('@/views/EventView.vue') },
  { path: '/login', name: 'login', component: () => import('@/views/LoginView.vue'), meta: { guestOnly: true } },
  { path: '/register', name: 'register', component: () => import('@/views/RegisterView.vue'), meta: { guestOnly: true } },
  { path: '/verify', name: 'verify', component: () => import('@/views/VerifyEmailView.vue') },
  { path: '/orders', name: 'orders', component: () => import('@/views/OrdersView.vue'), meta: { auth: true } },
  { path: '/orders/:id', name: 'order', component: () => import('@/views/OrderView.vue'), meta: { auth: true } },
  { path: '/payment/result', name: 'payment-result', component: () => import('@/views/PaymentResultView.vue'), meta: { auth: true } },
  { path: '/tickets', name: 'tickets', component: () => import('@/views/TicketsView.vue'), meta: { auth: true } },
  { path: '/tickets/:id', name: 'ticket', component: () => import('@/views/TicketView.vue'), meta: { auth: true } },
  { path: '/profile', name: 'profile', component: () => import('@/views/ProfileView.vue'), meta: { auth: true } },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/views/NotFoundView.vue') },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  await auth.init()

  if (to.meta.auth && auth.isGuest) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guestOnly && !auth.isGuest) {
    return { name: 'events' }
  }
})

export default router

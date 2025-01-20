import { createRouter, createWebHistory, createWebHashHistory } from 'vue-router'
import { routes } from '../app'

const router = createRouter({
  history: createWebHistory(),
  // history: createWebHashHistory(),
  routes: routes,
})

// Set Page title
router.beforeEach((to, from, next) => {
  const nearestWithTitle = to.matched.slice().reverse().find(r => r.meta && r.meta.title)
  if (nearestWithTitle) {
    document.title = nearestWithTitle.meta.title
  } else {
    document.title = 'Pie'
  }
  next()
})

export default router

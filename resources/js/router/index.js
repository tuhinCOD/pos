import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from '../stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'storefront',
      component: () => import('../layouts/storefront/StorefrontLayout.vue'),
      children: [
        {
          path: '',
          name: 'home',
          component: () => import('../views/storefront/Home.vue'),
        },
        {
          path: 'shop',
          name: 'shop',
          component: () => import('../views/storefront/Shop.vue'),
        },
        {
          path: 'shop/:id',
          name: 'product-detail',
          component: () => import('../views/storefront/ProductDetail.vue'),
        },
        {
          path: 'cart',
          name: 'cart',
          component: () => import('../views/storefront/Cart.vue'),
        },
        {
          path: 'checkout',
          name: 'checkout',
          component: () => import('../views/storefront/Checkout.vue'),
          meta: { requiresAuth: true }
        },
        {
          path: 'account/orders',
          name: 'my-orders',
          component: () => import('../views/storefront/account/Orders.vue'),
          meta: { requiresAuth: true }
        },
        {
          path: 'account/profile',
          name: 'my-profile',
          component: () => import('../views/storefront/account/Profile.vue'),
          meta: { requiresAuth: true }
        },
      ]
    },
    {
      path: '/dashlayout',
      name: 'dashlayout',
      component: () => import('../layouts/dashlayout/index.vue'),
      redirect: {path: '/dashboard'},
      meta: { requiresAuth: true, roles: ['admin', 'super admin'] },
      children: [
        {
          path: '/dashboard',
          name: 'dashboard',
          component: () => import('../views/dashboard/index.vue'),
          meta: { roles: ['super admin', 'admin', 'manager', 'cashier', 'warehouse staff'] }
        },
        {
          path: '/product/update',
          name: 'add-product',
          component: () => import('../views/add-product/index.vue'),
          meta: { roles: ['admin', 'manager'] }
        },
        {
          path: '/product/edit/:id?',
          name: 'edit-product',
          component: () => import('../views/add-product/index.vue'),
          meta: { roles: ['admin', 'manager'] }
        },
        {
          path: '/products',
          name: 'products',
          component: () => import('../views/view-products/index.vue'),
          meta: { roles: ['super admin', 'admin', 'manager', 'cashier', 'warehouse staff'] }
        },
        {
          path: '/user/update',
          name: 'add-user',
          component: () => import('../views/add-user/index.vue'),
          meta: { roles: ['admin', 'super admin', 'cashier', 'manager'] }
        },
        {
          path: '/user/edit/:id?',
          name: 'edit-user',
          component: () => import('../views/add-user/index.vue'),
          meta: { roles: ['admin', 'super admin', 'cashier', 'manager'] }
        },
        {
          path: '/users',
          name: 'users',
          component: () => import('../views/view-users/index.vue'),
          meta: { roles: ['admin', 'super admin', 'manager', 'cashier'] }
        },
        {
          path: '/suppliers',
          name: 'suppliers',
          component: () => import('../views/suppliers/index.vue'),
          meta: { roles: ['super admin', 'admin', 'manager'] }
        },
        {
          path: '/products/categories',
          name: 'categories',
          component: () => import('../views/categories/index.vue'),
          meta: { roles: ['super admin', 'admin', 'manager'] }
        },
        {
          path: '/products/units',
          name: 'units',
          component: () => import('../views/units/index.vue'),
          meta: { roles: ['super admin', 'admin', 'manager'] }
        },
        {
          path: '/branches',
          name: 'branches',
          component: () => import('../views/branches/index.vue'),
          meta: { roles: ['super admin', 'admin'] }
        },
        {
          path: '/product-price',
          name: 'product-price',
          component: () => import('../views/product-price/index.vue'),
          meta: { roles: ['super admin', 'admin', 'manager'] }
        },
        {
          path: '/purchases',
          name: 'purchases',
          component: () => import('../views/view-purchases/index.vue'),
          meta: { roles: ['super admin', 'admin', 'manager', 'warehouse staff'] }
        },
        {
          path: '/purchase/create',
          name: 'purchase-create',
          component: () => import('../views/add-purchase/index.vue'),
          meta: { roles: ['admin', 'manager', 'warehouse staff'] }
        },
        {
          path: '/purchase/edit/:id',
          name: 'purchase-edit',
          component: () => import('../views/add-purchase/index.vue'),
          meta: { roles: ['admin', 'manager', 'warehouse staff'] }
        },
        {
          path: '/sales',
          name: 'sales',
          component: () => import('../views/view-sales/index.vue'),
          meta: { roles: ['super admin', 'admin', 'manager', 'cashier'] }
        },
        {
          path: '/e-commerce-orders',
          name: 'e-commerce-orders',
          component: () => import('../views/e-commerce-orders/index.vue'),
          meta: { roles: ['super admin', 'admin', 'manager', 'warehouse staff'] }
        },
        {
          path: '/e-commerce-order/edit/:id',
          name: 'e-commerce-order-edit',
          component: () => import('../views/add-e-commerce-order/index.vue'),
          meta: { roles: ['admin', 'manager', 'warehouse staff'] }
        },
        {
          path: '/sale/edit/:id',
          name: 'sale-edit',
          component: () => import('../views/add-sale/index.vue'),
          meta: { roles: ['admin', 'manager'] }
        },
        {
          path: '/stock',
          name: 'stock',
          component: () => import('../views/stock/index.vue'),
          meta: { roles: ['super admin', 'admin', 'manager'] }
        },
        {
          path: '/stock-summary',
          name: 'stock-summary',
          component: () => import('../views/stock-summary/index.vue'),
          meta: { roles: ['super admin', 'admin', 'manager', 'cashier', 'warehouse staff'] }
        },
        {
          path: '/purchase-credits',
          name: 'purchase-credits',
          component: () => import('../views/view-purchase-credits/index.vue'),
          meta: { roles: ['super admin', 'admin', 'manager'] }
        },
        {
          path: '/purchase-credit/edit/:id',
          name: 'purchase-credit-edit',
          component: () => import('../views/add-purchase-credit/index.vue'),
          meta: { roles: ['admin', 'manager'] }
        },
        {
          path: '/purchase-returns',
          name: 'purchase-returns',
          component: () => import('../views/view-purchase-returns/index.vue'),
          meta: { roles: ['super admin', 'admin', 'manager', 'warehouse staff'] }
        },
        {
          path: '/purchase-return/create',
          name: 'purchase-return-create',
          component: () => import('../views/add-purchase-return/index.vue'),
          meta: { roles: ['admin', 'manager'] }
        },
        {
          path: '/purchase-return/edit/:id',
          name: 'purchase-return-edit',
          component: () => import('../views/add-purchase-return/index.vue'),
          meta: { roles: ['admin', 'manager'] }
        },
        {
          path: '/temps',
          name: 'temps',
          component: () => import('../views/view-temps/index.vue'),
          meta: { roles: ['super admin', 'admin', 'manager', 'cashier'] }
        },
        {
          path: '/temp/create',
          name: 'temp-create',
          component: () => import('../views/add-temp/index.vue'),
          meta: { roles: ['admin', 'manager', 'cashier'] }
        },
        {
          path: '/temp/edit/:id',
          name: 'temp-edit',
          component: () => import('../views/add-temp/index.vue'),
          meta: { roles: ['admin', 'manager', 'cashier'] }
        },
        {
          path: '/client-returns',
          name: 'client-returns',
          component: () => import('../views/view-sale-returns/index.vue'),
          meta: { roles: ['super admin', 'admin', 'manager', 'cashier', 'warehouse staff'] }
        },
        {
          path: '/client-return/create',
          name: 'client-return-create',
          component: () => import('../views/add-sale-return/index.vue'),
          meta: { roles: ['admin', 'manager', 'cashier'] }
        },
        {
          path: '/client-return/edit/:id',
          name: 'client-return-edit',
          component: () => import('../views/add-sale-return/index.vue'),
          meta: { roles: ['admin', 'manager', 'cashier'] }
        },
        {
          path: '/client-credits',
          name: 'client-credits',
          component: () => import('../views/view-sale-credits/index.vue'),
          meta: { roles: ['super admin', 'admin', 'manager', 'cashier'] }
        },
        {
          path: '/client-credit/edit/:id',
          name: 'client-credit-edit',
          component: () => import('../views/add-sale-credit/index.vue'),
          meta: { roles: ['admin', 'manager', 'cashier'] }
        },
        {
          path: '/damages',
          name: 'damages',
          component: () => import('../views/view-damages/index.vue'),
          meta: { roles: ['super admin', 'admin', 'manager', 'cashier', 'warehouse staff'] }
        },
        {
          path: '/damage/create',
          name: 'damage-create',
          component: () => import('../views/add-damage/index.vue'),
          meta: { roles: ['admin', 'manager', 'warehouse staff'] }
        },
        {
          path: '/damage/edit/:id',
          name: 'damage-edit',
          component: () => import('../views/add-damage/index.vue'),
          meta: { roles: ['admin', 'manager', 'warehouse staff'] }
        },
        {
          path: '/repairs',
          name: 'repairs',
          component: () => import('../views/view-repairs/index.vue'),
          meta: { roles: ['super admin', 'admin', 'manager'] }
        },
        {
          path: '/repair/edit/:id',
          name: 'repair-edit',
          component: () => import('../views/add-repair/index.vue'),
          meta: { roles: ['admin', 'manager'] }
        },
        {
          path: '/barcodes/generate',
          name: 'barcode-generate',
          component: () => import('../views/add-barcode/index.vue'),
          meta: { roles: ['admin', 'manager', 'warehouse staff'] }
        },
        {
          path: '/barcodes/generate-single',
          name: 'barcode-generate-single',
          component: () => import('../views/generate-barcode/index.vue'),
          meta: { roles: ['admin', 'manager', 'warehouse staff', 'cashier'] }
        },
        {
          path: '/barcodes',
          name: 'barcodes',
          component: () => import('../views/view-barcodes/index.vue'),
          meta: { roles: ['super admin', 'admin', 'manager', 'cashier', 'warehouse staff'] }
        },
        {
          path: '/barcodes/edit/:id',
          name: 'barcode-edit',
          component: () => import('../views/add-barcode/index.vue'),
          meta: { roles: ['admin', 'manager', 'warehouse staff', 'cashier'] }
        },
        {
          path: '/settings/general',
          name: 'settings-general',
          component: () => import('../views/settings/index.vue'),
          meta: { roles: ['admin'] }
        },
      ]
    },
    {
      path: '/',
      name: 'authlayout',
      component: () => import ('../layouts/Authlayout.vue'),
      redirect: { path: '/login'},
      children: [
        {
          path: '/login',
          name: 'login',
          component: () => import('../views/authentication/Login.vue')
        },
        {
          path: '/resetpass',
          name: 'resetpass',
          component: () => import('../views/authentication/Resetpass.vue'),
          meta: { requiresAuth: true }
        },
        {
          path: '/forget',
          name: 'forget',
          component: () => import('../views/authentication/Forget.vue')
        },
        {
          path: '/signup',
          name: 'signup',
          component: () => import('../views/authentication/Signup.vue')
        },
        {
          path: "/reset-pass/:token",
          name: "ResetPassword",
          component: () => import('../views/authentication/Forgetpass.vue'),
          meta: { guest: true }
        },
        {
          path: '/verification',
          name: 'verification',
          component: () => import('../views/authentication/Verification.vue')
        }
      ]
    },
    {
      path: '/:catchAll(.*)',
      name: '404-page',
      component: () => import('../views/404-page/index.vue')
    },
    {
      path: '/not-found',
      name: 'notFound',
      component: () => import('../views/404-page/index.vue')
    }
  ],
})

let isAuthChecked = false

router.beforeEach(async (to, from, next) => {
  const auth = useAuth()

  if (!isAuthChecked) {
    try {
      await auth.me()
    } catch (e) {
      console.log(e);
    }
    isAuthChecked = true
  }

  if (to.meta.requiresAuth && !auth.user) {
    return next({ path: '/login' })
  }

  if (to.meta.guestOnly && auth.user) {
    if (to.name === 'storefront' || to.name === 'home' || to.name === 'shop') {
      return next()
    }
    return next({ path: '/' })
  }

  if (to.meta.roles && auth.user) {
    const userRole = auth.user?.role?.name
    if (!userRole || !to.meta.roles.includes(userRole)) {
      return next({ path: '/not-found' })
    }
  }

  next()
})

export default router

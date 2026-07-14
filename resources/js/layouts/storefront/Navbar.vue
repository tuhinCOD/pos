<template>
  <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
    <div class="container">
      <router-link class="navbar-brand fw-bold" to="/">
        <i class="bi bi-shop me-2"></i>{{ companyName || 'Shop' }}
      </router-link>

      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#storefrontNav">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="storefrontNav">
        <ul class="navbar-nav me-auto">
          <li class="nav-item">
            <router-link class="nav-link" to="/shop">Shop</router-link>
          </li>
          <li class="nav-item" v-if="auth.user">
            <router-link class="nav-link" to="/account/orders">My Orders</router-link>
          </li>
        </ul>

        <div class="d-flex align-items-center gap-3">
          <button v-if="auth.user" class="btn position-relative text-dark border-0 p-0" @click="showWishlist = true">
            <i class="bi bi-heart fs-5"></i>
            <span v-if="wishlist.items.length" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:10px">
              {{ wishlist.items.length }}
            </span>
          </button>

          <router-link to="/cart" class="position-relative text-dark text-decoration-none">
            <i class="bi bi-cart3 fs-5"></i>
            <span v-if="cart.count" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary" style="font-size:10px">
              {{ cart.count }}
            </span>
          </router-link>

          <div v-if="auth.user" class="dropdown">
            <button class="btn btn-light dropdown-toggle border-0 shadow-none d-flex align-items-center gap-2" data-bs-toggle="dropdown">
              <i class="bi bi-person-circle fs-5"></i>
              <span class="d-none d-md-inline">{{ auth.user.name }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><router-link class="dropdown-item" to="/account/profile">Profile</router-link></li>
              <li><router-link class="dropdown-item" to="/account/orders">My Orders</router-link></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="#" @click.prevent="auth.logout()">Logout</a></li>
            </ul>
          </div>

          <template v-else>
            <router-link to="/login" class="btn btn-outline-primary btn-sm">Login</router-link>
            <router-link to="/signup" class="btn btn-primary btn-sm">Sign Up</router-link>
          </template>
        </div>
      </div>
    </div>
  </nav>

  <div v-if="showWishlist" class="offcanvas-backdrop fade show" @click="showWishlist = false"></div>
  <div class="offcanvas offcanvas-end show" tabindex="-1" v-if="showWishlist">
    <div class="offcanvas-header border-bottom">
      <h5 class="offcanvas-title fw-bold"><i class="bi bi-heart me-2"></i>Wishlist</h5>
      <button type="button" class="btn-close" @click="showWishlist = false"></button>
    </div>
    <div class="offcanvas-body p-0">
      <div v-if="!wishlist.items.length" class="text-center py-5 text-secondary">
        <i class="bi bi-heartbreak display-4"></i>
        <p class="mt-2">Your wishlist is empty</p>
      </div>
      <div v-else class="list-group list-group-flush">
        <div v-for="item in wishlist.items" :key="item.id" class="list-group-item d-flex gap-3 align-items-center">
          <router-link :to="`/shop/${item.product_id}`" class="text-decoration-none flex-shrink-0" @click="showWishlist = false">
            <img
              :src="item.product?.images?.[0]?.image ? '/storage/'+item.product.images[0].image : 'https://placehold.co/60x60/e2e8f0/94a3b8?text=N'"
              style="width:60px;height:60px;object-fit:cover;border-radius:8px;"
            >
          </router-link>
          <div class="flex-grow-1 min-width-0">
            <router-link :to="`/shop/${item.product_id}`" class="text-decoration-none" @click="showWishlist = false">
              <h6 class="fw-bold text-dark mb-1 text-truncate">{{ item.product?.name }}</h6>
            </router-link>
            <span class="fw-bold text-primary small">৳{{ item.product?.product_price?.price || 0 }}</span>
          </div>
          <button class="btn btn-outline-danger btn-sm flex-shrink-0" @click="wishlist.toggle(item.product_id)">
            <i class="bi bi-trash"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, inject } from 'vue';
import { useAuth } from '@/stores/auth';
import { useCart } from '@/stores/cart';
import { useWishlist } from '@/stores/wishlist';

const auth = useAuth();
const cart = useCart();
const wishlist = useWishlist();
const companyName = inject('companyName', 'Store');
const showWishlist = ref(false);
</script>

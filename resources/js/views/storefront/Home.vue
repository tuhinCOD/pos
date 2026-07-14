<template>
  <div>
    <section class="bg-primary text-white py-5">
      <div class="container text-center py-5">
        <h1 class="display-4 fw-bold mb-3">Welcome to {{ companyName }}</h1>
        <p class="lead mb-4 opacity-75">Discover amazing products at unbeatable prices</p>
        <router-link to="/shop" class="btn btn-light btn-lg px-5 fw-semibold">Shop Now</router-link>
      </div>
    </section>

    <section class="container my-4" v-if="parentCategories.length || categories.length">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold mb-0">Shop by Category</h2>
        <button class="btn btn-outline-primary btn-sm" @click="showCategoryModal = true">
          View All Categories
        </button>
      </div>
      <div class="d-flex flex-nowrap gap-2 pb-2" style="overflow-x: auto;">
        <router-link
          v-for="cat in categories"
          :key="cat.id"
          :to="`/shop?category=${cat.id}`"
          class="text-decoration-none flex-shrink-0"
        >
          <div class="card border-0 bg-light text-center p-2 shadow-sm" style="width: 150px;">
            <h6 class="fw-bold mb-0 small text-dark">{{ cat.name }}</h6>
          </div>
        </router-link>
      </div>
    </section>

    <section class="container my-5">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0">Featured Products</h2>
        <router-link to="/shop" class="btn btn-outline-primary btn-sm">View All</router-link>
      </div>
      <div v-if="loading" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
      </div>
      <div v-else class="row g-3">
        <div v-for="product in products" :key="product.id" class="col-6 col-md-4 col-lg-3">
          <div class="card border-0 shadow-sm h-100">
            <router-link :to="`/shop/${product.id}`">
              <img
                :src="product.images?.[0]?.image ? '/storage/' + product.images[0].image : 'https://placehold.co/300x300/e2e8f0/94a3b8?text=No+Image'"
                class="card-img-top"
                :alt="product.name"
                style="height: 200px; object-fit: cover;"
              >
            </router-link>
            <div class="card-body d-flex flex-column">
              <small class="text-secondary mb-1">{{ product.category?.name }}</small>
              <router-link :to="`/shop/${product.id}`" class="text-decoration-none">
                <h6 class="fw-bold text-dark mb-2">{{ product.name }}</h6>
              </router-link>
              <div class="mt-auto d-flex justify-content-between align-items-center">
                <span class="fw-bold text-primary fs-5">
                  ৳{{ product?.product_price?.price || 0 }}
                </span>
                <button v-if="auth.user" class="btn btn-outline-danger btn-sm" @click="wishlist.toggle(product.id)">
                  <i :class="wishlist.isWishlisted(product.id) ? 'bi bi-heart-fill' : 'bi bi-heart'"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <div v-if="showCategoryModal" class="modal-backdrop fade show" style="z-index: 1050;"></div>
    <div v-if="showCategoryModal" class="modal d-block fade show" tabindex="-1" style="z-index: 1055;">
      <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
          <div class="modal-header border-0 pb-0">
            <h5 class="fw-bold">All Categories</h5>
            <button type="button" class="btn-close" @click="showCategoryModal = false"></button>
          </div>
          <div class="modal-body">
            <div v-for="(children, parentName) in childCategories" :key="parentName" class="mb-3">
              <router-link
                :to="`/shop?category=${children[0]?.parent_id}`"
                class="text-decoration-none"
                @click="showCategoryModal = false"
              >
                <h6 class="fw-bold mb-2" style="font-size: 13px;">{{ parentName }}</h6>
              </router-link>
              <div class="d-flex flex-wrap gap-2">
                <router-link
                  v-for="cat in children"
                  :key="cat.id"
                  :to="`/shop?category=${cat.id}`"
                  class="btn btn-outline-dark btn-sm rounded-pill"
                  @click="showCategoryModal = false"
                >
                  {{ cat.name }}
                </router-link>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, inject } from 'vue';
import axios from 'axios';
import { useWishlist } from '@/stores/wishlist';
import { useAuth } from '@/stores/auth';

const auth = useAuth();
const wishlist = useWishlist();
const companyName = inject('companyName', 'Store');

const products = ref([]);
const categories = ref([]);
const parentCategories = ref([]);
const childCategories = ref({});
const loading = ref(true);
const showCategoryModal = ref(false);

onMounted(async () => {
  try {
    const [prodRes, catRes] = await Promise.all([
      axios.get('/api/storefront/featured-products'),
      axios.get('/api/storefront/categories'),
    ]);
    products.value = prodRes.data.products || [];
    const catData = catRes.data;
    if (catData.parent_categories) {
      parentCategories.value = catData.parent_categories || [];
      childCategories.value = catData.child_categories || {};
      categories.value = parentCategories.value;
    } else {
      categories.value = catData.categories || [];
    }
  } catch (e) {
    console.error('Failed to load homepage:', e);
  } finally {
    loading.value = false;
  }
});

</script>

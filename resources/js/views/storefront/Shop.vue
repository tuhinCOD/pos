<template>
  <div class="container my-4">
    <div class="mb-3 d-flex justify-content-center">
      <input
        type="text"
        class="form-control rounded-pill"
        v-model="filters.search"
        placeholder="Search products..."
        @input="onSearchInput"
        style="max-width: 600px;"
      >
    </div>

    <div class="d-flex flex-nowrap gap-2 mb-4 pb-2" style="overflow-x: auto;" v-if="allCategories.length">
      <button
        class="btn btn-sm rounded-pill flex-shrink-0"
        :class="!filters.category ? 'btn-dark' : 'btn-outline-secondary'"
        @click="selectCategory('')"
      >All</button>
      <button
        v-for="cat in allCategories"
        :key="cat.id"
        class="btn btn-sm rounded-pill flex-shrink-0"
        :class="filters.category == cat.id ? 'btn-dark' : 'btn-outline-secondary'"
        @click="selectCategory(cat.id)"
      >{{ cat.name }}</button>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="fw-bold mb-0">Products</h5>
      <small class="text-secondary">{{ products.total || 0 }} products found</small>
    </div>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status"></div>
    </div>

    <div v-else-if="!products.data?.length" class="text-center py-5 text-secondary">
      <i class="bi bi-box display-4"></i>
      <p class="mt-2">No products found</p>
    </div>

    <div v-else class="row g-3">
      <div v-for="product in products.data" :key="product.id" class="col-6 col-md-4 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
          <router-link :to="`/shop/${product.id}`">
            <img
              :src="product.images?.[0]?.image ? '/storage/' + product.images[0].image : 'https://placehold.co/300x300/e2e8f0/94a3b8?text=No+Image'"
              class="card-img-top"
              :alt="product.name"
              style="height: 180px; object-fit: cover;"
            >
          </router-link>
          <div class="card-body d-flex flex-column">
            <small class="text-secondary mb-1">{{ product.category?.name }}</small>
            <router-link :to="`/shop/${product.id}`" class="text-decoration-none">
              <h6 class="fw-bold text-dark mb-2" style="font-size:0.9rem">{{ product.name }}</h6>
            </router-link>
            <div class="mt-auto d-flex justify-content-between align-items-center">
              <span class="fw-bold text-primary">৳{{ product.product_price?.price || 0 }}</span>
              <button v-if="auth.user" class="btn btn-outline-danger btn-sm" @click="wishlist.toggle(product.id)">
                <i :class="wishlist.isWishlisted(product.id) ? 'bi bi-heart-fill' : 'bi bi-heart'"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <nav v-if="products.last_page > 1" class="mt-4">
      <ul class="pagination pagination-sm justify-content-center">
        <li class="page-item" :class="{ disabled: products.current_page === 1 }">
          <button class="page-link" @click="changePage(products.current_page - 1)">Prev</button>
        </li>
        <li v-for="page in products.last_page" :key="page" class="page-item" :class="{ active: page === products.current_page }">
          <button class="page-link" @click="changePage(page)">{{ page }}</button>
        </li>
        <li class="page-item" :class="{ disabled: products.current_page === products.last_page }">
          <button class="page-link" @click="changePage(products.current_page + 1)">Next</button>
        </li>
      </ul>
    </nav>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useRoute } from 'vue-router';
import { useWishlist } from '@/stores/wishlist';
import { useAuth } from '@/stores/auth';

const route = useRoute();
const wishlist = useWishlist();
const auth = useAuth();

const products = ref({ data: [], current_page: 1, last_page: 1, total: 0 });
const parentCategories = ref([]);
const childCategories = ref({});
const loading = ref(true);

const allCategories = computed(() => {
  const cats = [];
  for (const parent of parentCategories.value) {
    cats.push(parent);
  }
  for (const children of Object.values(childCategories.value)) {
    cats.push(...children);
  }
  return cats;
});

const filters = ref({
  search: route.query.search || '',
  category: route.query.category || '',
  min_price: '',
  max_price: '',
  sort: 'newest',
});

onMounted(async () => {
  try {
    const catRes = await axios.get('/api/storefront/categories');
    const catData = catRes.data;
    if (catData.parent_categories) {
      parentCategories.value = catData.parent_categories || [];
      childCategories.value = catData.child_categories || {};
    } else {
      for (const cat of (catData.categories || [])) {
        if (!cat.parent_id) {
          parentCategories.value.push(cat);
        }
      }
    }
  } catch { /* silent */ }
  await loadProducts();
});

const page = ref(1);

const loadProducts = async () => {
  loading.value = true;
  try {
    const params = { ...filters.value, page: page.value };
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
    const res = await axios.get('/api/storefront/products', { params });
    products.value = res.data.products;
  } catch (e) {
    console.error('Failed to load products:', e);
  } finally {
    loading.value = false;
  }
};

const selectCategory = (id) => {
  filters.value.category = id;
  page.value = 1;
  loadProducts();
};

let searchTimer;
const onSearchInput = () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    page.value = 1;
    loadProducts();
  }, 400);
};

const changePage = (newPage) => {
  if (newPage < 1 || newPage > products.value.last_page) return;
  page.value = newPage;
  loadProducts();
};

</script>

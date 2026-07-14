<template>
  <div class="container my-4">
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb small">
        <li class="breadcrumb-item"><router-link to="/">Home</router-link></li>
        <li class="breadcrumb-item"><router-link to="/shop">Shop</router-link></li>
        <li class="breadcrumb-item active">{{ product?.name }}</li>
      </ol>
    </nav>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status"></div>
    </div>

    <template v-else-if="product">
      <div class="row g-4 mb-5">
        <div class="col-md-6">
          <div class="d-flex gap-2">
            <div v-if="product.images?.length > 1" class="d-flex flex-column gap-2">
              <img
                v-for="(img, index) in product.images"
                :key="img.id"
                :src="'/storage/' + img.image"
                @click="selectImage(index)"
                :class="{ 'border-primary': selectedImageIndex === index }"
                class="rounded cursor-pointer"
                style="width: 80px; height: 80px; object-fit: cover; border: 2px solid transparent; cursor: pointer;"
              >
            </div>
            <div class="flex-grow-1">
              <img
                :src="selectedImage ? '/storage/' + selectedImage : 'https://placehold.co/600x600/e2e8f0/94a3b8?text=No+Image'"
                class="img-fluid rounded shadow-sm w-100"
                :alt="product.name"
                style="max-height: 450px; object-fit: cover;"
              >
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <small class="text-secondary text-uppercase">{{ product.category?.name }}</small>
          <h2 class="fw-bold">{{ product.name }}</h2>
          <div class="d-flex align-items-center gap-3 mb-3">
            <span class="fw-bold text-primary fs-3">৳{{ product.product_price?.price || 0 }}</span>
            <span v-if="product.stock" class="badge bg-success">In Stock</span>
            <span v-else class="badge bg-danger">Out of Stock</span>
          </div>

          <p class="text-secondary">{{ product.description || 'No description available.' }}</p>

          <div v-if="parsedAttributes && Object.keys(parsedAttributes).length" class="mb-4">
            <div v-for="(values, key) in parsedAttributes" :key="key" class="mb-3">
              <label class="form-label fw-semibold text-capitalize">{{ key }}</label>
              <div v-if="Array.isArray(values)" class="d-flex flex-wrap gap-2">
                <label v-for="val in values" :key="val" class="btn btn-outline-secondary btn-sm" :class="{ 'active border-primary text-light': selectedAttributes[key] === val }">
                  <input type="radio" :name="'attr_' + key" :value="val" v-model="selectedAttributes[key]" class="d-none">
                  {{ val }}
                </label>
              </div>
              <p v-else class="text-secondary small mb-0">{{ values }}</p>
            </div>
          </div>
          <div class="mb-3">
            <label class="fw-semibold mb-1 d-block">Quantity</label>
            <div class="d-flex align-items-center gap-3">
              <div class="input-group" style="width: 140px">
                <button class="btn btn-outline-secondary btn-sm" @click="decreaseQty">-</button>
                <input type="number" class="form-control text-center form-control-sm" v-model.number="qty" min="1">
                <button class="btn btn-outline-secondary btn-sm" @click="qty++">+</button>
              </div>
              <span class="text-success fw-semibold small" v-if="availableStock > 0">
                <i class="bi bi-box me-1"></i>Available: {{ availableStock }}
              </span>
              <span class="text-danger fw-semibold small" v-else>
                Out of Stock
              </span>
            </div>
          </div>

          <div class="d-flex align-items-center gap-3 mb-4">
            <button class="btn btn-primary px-4" @click="addToCart" :disabled="!auth.user">
              <i class="bi bi-cart-plus me-2"></i>Add to Cart
            </button>
            <button class="btn btn-success px-4" @click="buyNow" :disabled="!auth.user">
              <i class="bi bi-lightning me-2"></i>Buy Now
            </button>
            <button v-if="auth.user" class="btn btn-outline-danger" @click="toggleWishlist">
              <i :class="wishlist.isWishlisted(product.id) ? 'bi bi-heart-fill' : 'bi bi-heart'"></i>
            </button>
          </div>

          <p v-if="!auth.user" class="text-warning small">
            <i class="bi bi-info-circle me-1"></i>
            <router-link to="/login" class="text-warning">Login</router-link> to add items to cart
          </p>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-md-8">
          <div class="card border-0 shadow-sm p-3">
            <h5 class="fw-bold mb-3">Reviews ({{ reviews.length }})</h5>
            <div v-if="!reviews.length" class="text-center py-4 text-secondary">
              <i class="bi bi-chat display-6"></i>
              <p class="mt-2">No reviews yet</p>
            </div>
            <div v-for="review in reviews" :key="review.id" class="border-bottom pb-3 mb-3">
              <div class="d-flex justify-content-between">
                <strong>{{ review.client?.name || 'Anonymous' }}</strong>
                <span class="text-warning">{{ '★'.repeat(Math.round(review.rating)) }}{{ '☆'.repeat(5-Math.round(review.rating)) }}</span>
              </div>
              <p class="small text-secondary mt-1 mb-0">{{ review.review }}</p>
              <div v-if="review.images?.length" class="d-flex gap-2 mt-2">
                <img v-for="img in review.images" :key="img.id" :src="'/storage/'+img.image" class="rounded" style="width:50px;height:50px;object-fit:cover;">
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-4" v-if="relatedProducts.length">
          <h5 class="fw-bold mb-3">Related Products</h5>
          <div v-for="rp in relatedProducts" :key="rp.id" class="card border-0 shadow-sm mb-2">
            <div class="row g-0">
              <div class="col-4">
                <img :src="rp.images?.[0]?.image ? '/storage/'+rp.images[0].image : 'https://placehold.co/100x100/e2e8f0/94a3b8?text=N/A'" class="img-fluid rounded-start" style="height:80px;object-fit:cover;width:100%;">
              </div>
              <div class="col-8">
                <div class="card-body py-2 px-3">
                  <router-link :to="`/shop/${rp.id}`" class="text-decoration-none">
                    <h6 class="fw-bold text-dark mb-1" style="font-size:0.8rem">{{ rp.name }}</h6>
                  </router-link>
                  <span class="fw-bold text-primary small">৳{{ rp.product_price?.price || 0 }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <div v-else class="text-center py-5">
      <p class="text-secondary">Product not found</p>
      <router-link to="/shop" class="btn btn-primary">Back to Shop</router-link>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { useCart } from '@/stores/cart';
import { useWishlist } from '@/stores/wishlist';
import { useAuth } from '@/stores/auth';

const route = useRoute();
const router = useRouter();
const cart = useCart();
const wishlist = useWishlist();
const auth = useAuth();

const product = ref(null);
const reviews = ref([]);
const relatedProducts = ref([]);
const loading = ref(true);
const qty = ref(1);
const selectedImageIndex = ref(0);
const selectedAttributes = ref({});
const stockByAttributes = ref({});

const selectedImage = computed(() => {
  return product.value?.images?.[selectedImageIndex.value]?.image || null;
});

const availableStock = computed(() => {
  const attrs = selectedAttributes.value;
  const keys = Object.keys(attrs);
  if (!keys.length) {
    return stockByAttributes.value['{}'] || 0;
  }
  const key = JSON.stringify(attrs);
  return stockByAttributes.value[key] || 0;
});

const parsedAttributes = computed(() => {
  if (!product.value?.attributes) return null;
  const attrs = product.value.attributes;
  if (typeof attrs === 'string') {
    try { return JSON.parse(attrs); } catch { return null; }
  }
  return attrs;
});

onMounted(async () => {
  try {
    const res = await axios.get(`/api/storefront/products/${route.params.id}`);
    product.value = res.data.product;
    reviews.value = res.data.reviews || [];
    relatedProducts.value = res.data.related_products || [];
    stockByAttributes.value = res.data.stock_by_attributes || {};
    const attrs = parsedAttributes.value;
    if (attrs) {
      for (const key of Object.keys(attrs)) {
        const values = attrs[key];
        selectedAttributes.value[key] = Array.isArray(values) ? values[0] : values;
      }
    }
  } catch (e) {
    console.error('Failed to load product:', e);
  } finally {
    loading.value = false;
  }
});

const selectImage = (index) => {
  selectedImageIndex.value = index;
};

const decreaseQty = () => { if (qty.value > 1) qty.value--; };

const getAttributesString = () => {
  const attrs = parsedAttributes.value;
  if (!attrs || !Object.keys(attrs).length) return null;
  return JSON.stringify(selectedAttributes.value);
};

const addToCart = async () => {
  if (!auth.user) return;
  const attrs = getAttributesString();
  await cart.add(product.value.id, qty.value, attrs);
};

const buyNow = async () => {
  if (!auth.user) return;
  const attrs = getAttributesString();
  await cart.add(product.value.id, qty.value, attrs);
  router.push('/checkout');
};

const toggleWishlist = async () => {
  await wishlist.toggle(product.value.id);
};
</script>

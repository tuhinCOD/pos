<template>
  <div class="container my-4">
    <h4 class="fw-bold mb-4"><i class="bi bi-box me-2"></i>My Orders</h4>

    <div v-if="!auth.user" class="text-center py-5">
      <p>Please <router-link to="/login">login</router-link> to view orders</p>
    </div>

    <div v-else-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary"></div>
    </div>

    <div v-else-if="!Object.keys(orders).length" class="text-center py-5">
      <i class="bi bi-box display-3 text-secondary"></i>
      <p class="mt-3 text-secondary">No orders yet</p>
      <router-link to="/shop" class="btn btn-primary">Start Shopping</router-link>
    </div>

    <div v-else>
      <div v-for="(group, invoice) in orders" :key="invoice" class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <div>
            <strong>Invoice: {{ invoice }}</strong>
            <span class="badge bg-primary ms-2">{{ group[0]?.status?.name }}</span>
          </div>
          <small class="text-secondary">{{ new Date(group[0]?.created_at).toLocaleDateString() }}</small>
        </div>
        <div class="card-body">
          <div v-for="item in group" :key="item.id" class="d-flex align-items-center gap-3 mb-2 border-bottom pb-2">
            <img
              :src="item.product?.images?.[0]?.image ? '/storage/'+item.product.images[0].image : 'https://placehold.co/60x60/e2e8f0/94a3b8?text=N'"
              class="rounded"
              style="width:60px;height:60px;object-fit:cover;"
            >
            <div class="flex-grow-1">
              <h6 class="fw-bold mb-0" style="font-size:0.9rem">{{ item.product?.name }}</h6>
              <small class="text-secondary">Qty: {{ item.qty }} x ৳{{ item.price }}</small>
            </div>
            <div class="d-flex flex-column align-items-end gap-1">
              <span class="fw-bold">৳{{ (item.qty * item.price).toFixed(2) }}</span>
              <button
                v-if="isDelivered(group)"
                type="button"
                class="btn btn-sm btn-outline-warning rounded-pill px-3"
                @click="openReview(item)"
              >
                <i class="bi bi-star me-1"></i>Review
              </button>
            </div>
          </div>
          <div class="text-end mt-2">
            <small class="text-secondary">Subtotal: ৳{{ group.reduce((s,i) => s + (i.qty * i.price), 0).toFixed(2) }}</small>
          </div>
        </div>
      </div>
    </div>

    <ReviewModal
      v-if="reviewProduct"
      :product-id="reviewProduct.product_id"
      :product-name="reviewProduct.product?.name || ''"
      :product-image="getProductImage(reviewProduct)"
      :client-id="auth.user.id"
      @close="reviewProduct = null"
      @review-submitted="handleReviewSubmitted"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useAuth } from '@/stores/auth';
import ReviewModal from '@/components/ReviewModal.vue';

const auth = useAuth();
const orders = ref({});
const loading = ref(true);
const reviewProduct = ref(null);

const isDelivered = (group) => {
  return group[0]?.status?.name?.toLowerCase() === 'delivered';
};

const getProductImage = (item) => {
  return item.product?.images?.[0]?.image
    ? '/storage/' + item.product.images[0].image
    : 'https://placehold.co/60x60/e2e8f0/94a3b8?text=N';
};

const openReview = (item) => {
  reviewProduct.value = item;
};

const handleReviewSubmitted = () => {
  reviewProduct.value = null;
};

onMounted(async () => {
  if (!auth.user) { loading.value = false; return; }
  try {
    const res = await axios.get('/api/storefront/my-orders');
    orders.value = res.data.orders || {};
  } catch { /* silent */ }
  finally { loading.value = false; }
});
</script>

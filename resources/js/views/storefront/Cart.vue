<template>
  <div class="container my-4">
    <h4 class="fw-bold mb-4"><i class="bi bi-cart3 me-2"></i>Shopping Cart</h4>

    <div v-if="!auth.user" class="text-center py-5">
      <i class="bi bi-cart-x display-3 text-secondary"></i>
      <p class="mt-3">Please <router-link to="/login">login</router-link> to view your cart</p>
    </div>

    <template v-else-if="cart.loading">
      <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
    </template>

    <template v-else-if="!cart.items.length">
      <div class="text-center py-5">
        <i class="bi bi-cart display-3 text-secondary"></i>
        <p class="mt-3 text-secondary">Your cart is empty</p>
        <router-link to="/shop" class="btn btn-primary">Continue Shopping</router-link>
      </div>
    </template>

    <template v-else>
      <form @submit.prevent="saveAll">
        <div class="row g-4">
          <div class="col-lg-8">
            <div v-for="item in cart.items" :key="item.id" class="card border-0 shadow-sm mb-3">
              <div class="card-body">
                <div class="row g-3 align-items-center">
                  <div class="col-md-2 col-4">
                    <img
                      :src="item.product?.images?.[0]?.image ? '/storage/' + item.product.images[0].image : 'https://placehold.co/100x100/e2e8f0/94a3b8?text=N/A'"
                      class="img-fluid rounded"
                      style="height:80px;object-fit:cover;width:100%;"
                    >
                  </div>
                  <div class="col-md-4 col-8">
                    <router-link :to="`/shop/${item.product_id}`" class="text-decoration-none">
                      <h6 class="fw-bold text-dark mb-1">{{ item.product?.name }}</h6>
                    </router-link>
                    <small class="text-secondary">৳{{ item.product?.product_price?.price || 0 }}</small>
                    <div v-if="item.attributes" class="mt-1">
                      <span v-for="(val, key) in JSON.parse(item.attributes)" :key="key" class="badge bg-light text-dark me-1 border">{{ key }}: {{ val }}</span>
                    </div>
                  </div>
                  <div class="col-md-3 col-6">
                    <div class="input-group input-group-sm" style="width:120px">
                      <button type="button" class="btn btn-outline-secondary" @click="adjustQty(item, -1)">-</button>
                      <input type="text" class="form-control text-center" :value="getQty(item)" readonly>
                      <button type="button" class="btn btn-outline-secondary" @click="adjustQty(item, 1)">+</button>
                    </div>
                  </div>
                  <div class="col-md-2 col-4 text-end">
                    <span class="fw-bold">৳{{ (getQty(item) * (item.product?.product_price?.price || 0)).toFixed(2) }}</span>
                  </div>
                  <div class="col-md-1 col-2 text-end">
                    <button type="button" class="btn btn-sm text-danger" @click="cart.remove(item.id)">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
              <div class="card-body">
                <h5 class="fw-bold mb-3">Order Summary</h5>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-secondary">Subtotal</span>
                  <span>৳{{ localSubtotal.toFixed(2) }}</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between fw-bold fs-5">
                  <span>Total</span>
                  <span class="text-primary">৳{{ localSubtotal.toFixed(2) }}</span>
                </div>
                <button type="submit" class="btn btn-success w-100 mt-3" :disabled="!hasChanges">
                  <i class="bi bi-check2 me-1"></i>Save Changes
                </button>
                <router-link to="/checkout" class="btn btn-primary w-100 mt-2">
                  Checkout
                </router-link>
                <router-link to="/shop" class="btn btn-secondary w-100 mt-2">
                  Continue Shopping
                </router-link>
              </div>
            </div>
          </div>
        </div>
      </form>
    </template>
  </div>
</template>

<script setup>
import { reactive, computed, onMounted } from 'vue';
import { useCart } from '@/stores/cart';
import { useAuth } from '@/stores/auth';

const cart = useCart();
const auth = useAuth();

const editedQuantities = reactive({});

onMounted(() => {
  if (auth.user) cart.fetch();
});

const getQty = (item) => {
  return editedQuantities[item.id] ?? item.qty;
};

const adjustQty = (item, delta) => {
  const current = Number(getQty(item));
  const newQty = Math.max(1, current + delta);
  editedQuantities[item.id] = newQty;
};

const hasChanges = computed(() =>
  cart.items.some(item => editedQuantities[item.id] !== undefined && editedQuantities[item.id] !== Number(item.qty))
);

const localSubtotal = computed(() =>
  cart.items.reduce((sum, item) => {
    const qty = getQty(item);
    return sum + Number(qty) * Number(item.product?.product_price?.price || 0);
  }, 0)
);

const saveAll = async () => {
  const updates = cart.items
    .filter(item => editedQuantities[item.id] !== undefined && editedQuantities[item.id] !== Number(item.qty))
    .map(item => cart.update(item.id, editedQuantities[item.id]));

  await Promise.all(updates);

  for (const item of cart.items) {
    delete editedQuantities[item.id];
  }
};
</script>

<template>
  <div class="container my-4">
    <h4 class="fw-bold mb-4"><i class="bi bi-credit-card me-2"></i>Checkout</h4>

    <div v-if="!auth.user" class="text-center py-5">
      <i class="bi bi-shield-lock display-3 text-secondary"></i>
      <p class="mt-3">Please <router-link to="/login">login</router-link> to checkout</p>
    </div>

    <template v-else-if="!cart.items.length">
      <div class="text-center py-5">
        <i class="bi bi-cart-x display-3 text-secondary"></i>
        <p class="mt-3 text-secondary">Your cart is empty</p>
        <router-link to="/shop" class="btn btn-primary">Go Shopping</router-link>
      </div>
    </template>

    <template v-else>
      <div v-if="orderPlaced" class="text-center py-5">
        <i class="bi bi-check-circle-fill display-3 text-success"></i>
        <h4 class="fw-bold mt-3">Order Placed Successfully!</h4>
        <p class="text-secondary">Invoice No: <strong>{{ invoiceNo }}</strong></p>
        <router-link to="/account/orders" class="btn btn-primary">View My Orders</router-link>
        <router-link to="/shop" class="btn btn-outline-primary ms-2">Continue Shopping</router-link>
      </div>

      <div v-else class="row g-4">
        <div class="col-lg-5">
          <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
              <h5 class="fw-bold mb-3">Shipping Details</h5>
              <div class="mb-3">
                <label class="form-label small fw-semibold">Full Name</label>
                <input type="text" class="form-control" :class="{ 'is-invalid': formErrors.shipping_name }" v-model="shippingName" @input="clearError('shipping_name')" placeholder="Enter your full name">
                <div v-if="formErrors.shipping_name" class="invalid-feedback">{{ formErrors.shipping_name }}</div>
              </div>
              <div class="mb-3">
                <label class="form-label small fw-semibold">Contact</label>
                <input type="text" class="form-control" :class="{ 'is-invalid': formErrors.shipping_contact }" v-model="shippingContact" @input="clearError('shipping_contact')" placeholder="Enter your phone number">
                <div v-if="formErrors.shipping_contact" class="invalid-feedback">{{ formErrors.shipping_contact }}</div>
              </div>
              <div class="mb-3">
                <label class="form-label small fw-semibold">City</label>
                <select class="form-select" :class="{ 'is-invalid': formErrors.shipping_city_id }" v-model="shippingCityId" @change="clearError('shipping_city_id')">
                  <option value="" disabled>Select city</option>
                  <option v-for="c in cities" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <div v-if="formErrors.shipping_city_id" class="invalid-feedback">{{ formErrors.shipping_city_id }}</div>
              </div>
              <div class="mb-3">
                <label class="form-label small fw-semibold">Address</label>
                <textarea class="form-control" :class="{ 'is-invalid': formErrors.shipping_address }" v-model="shippingAddress" @input="clearError('shipping_address')" rows="2" placeholder="Enter your address"></textarea>
                <div v-if="formErrors.shipping_address" class="invalid-feedback">{{ formErrors.shipping_address }}</div>
              </div>
              <div class="mb-3">
                <label class="form-label small fw-semibold">Note</label>
                <textarea class="form-control" v-model="note" rows="2" placeholder="Optional note for your order"></textarea>
              </div>
            </div>
          </div>

          <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
              <h5 class="fw-bold mb-3">Coupon Code</h5>
              <div class="input-group">
                <input type="text" class="form-control" v-model="couponCode" placeholder="Enter coupon code">
                <button class="btn btn-outline-primary" @click="applyCoupon" :disabled="applyingCoupon">{{ applyingCoupon ? '...' : 'Apply' }}</button>
              </div>
              <small v-if="couponMessage" :class="couponError ? 'text-danger' : 'text-success'">{{ couponMessage }}</small>
            </div>
          </div>
        </div>

        <div class="col-lg-7">
          <div class="card border-0 shadow-sm">
            <div class="card-body">
              <h5 class="fw-bold mb-3">Items</h5>

              <div v-for="item in cart.items" :key="item.id" class="border-bottom pb-3 mb-3">
                <div class="d-flex gap-3 align-items-start">
                  <img
                    :src="item.product?.images?.[0]?.image ? '/storage/' + item.product.images[0].image : 'https://placehold.co/80x80/e2e8f0/94a3b8?text=N/A'"
                    class="rounded flex-shrink-0"
                    style="width: 70px; height: 70px; object-fit: cover;"
                  >
                  <div class="flex-grow-1 min-w-0">
                    <h6 class="fw-bold text-dark mb-1 text-truncate">{{ item.product?.name }}</h6>

                    <div v-if="getProductAttributes(item)" class="d-flex flex-wrap gap-2 mb-2">
                      <div v-for="(options, key) in getProductAttributes(item)" :key="key" class="d-flex align-items-center gap-1">
                        <small class="text-capitalize text-secondary">{{ key }}:</small>
                        <select class="form-select form-select-sm" style="width: auto;" @change="updateItemAttribute(item, key, $event.target.value)">
                          <option v-for="opt in (Array.isArray(options) ? options : [options])" :key="opt" :value="opt" :selected="getSelectedAttr(item, key) === opt">{{ opt }}</option>
                        </select>
                      </div>
                    </div>

                  </div>
                  <div class="d-flex flex-column align-items-end gap-1 flex-shrink-0">
                    <div class="input-group input-group-sm" style="width: 100px;">
                      <button class="btn btn-outline-secondary btn-sm" @click="adjustQty(item, -1)">-</button>
                      <input type="text" class="form-control text-center" :value="item.qty" readonly>
                      <button class="btn btn-outline-secondary btn-sm" @click="adjustQty(item, 1)">+</button>
                    </div>
                    <span class="fw-bold small">৳{{ (item.qty * (item.product?.product_price?.price || 0)).toFixed(2) }}</span>
                  </div>
                </div>
              </div>

              <hr>
              <div class="d-flex justify-content-between small mb-1">
                <span class="text-secondary">Subtotal</span>
                <span>৳{{ cart.subtotal.toFixed(2) }}</span>
              </div>
              <div v-if="discountPercent > 0" class="d-flex justify-content-between small text-success mb-1">
                <span>Discount ({{ discountPercent }}%)</span>
                <span>-৳{{ ((cart.subtotal * discountPercent) / 100).toFixed(2) }}</span>
              </div>
              <hr>
              <div class="d-flex justify-content-between fw-bold fs-5 mb-3">
                <span>Total</span>
                <span class="text-primary">৳{{ total.toFixed(2) }}</span>
              </div>

              <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" v-model="agreedToTerms" id="agreeTerms">
                <label class="form-check-label small" for="agreeTerms">
                  I agree to the <a href="#" class="text-primary">Return Policy</a> and <a href="#" class="text-primary">Terms & Conditions</a>
                </label>
              </div>

              <button class="btn btn-primary w-100 btn-lg" @click="placeOrder" :disabled="placing || !agreedToTerms">
                <span v-if="placing" class="spinner-border spinner-border-sm me-2"></span>
                {{ placing ? 'Processing...' : 'Pay Now' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useCart } from '@/stores/cart';
import { useAuth } from '@/stores/auth';

const cart = useCart();
const auth = useAuth();

const cities = ref([]);
const shippingName = ref('');
const shippingContact = ref('');
const shippingCityId = ref('');
const shippingAddress = ref('');
const note = ref('');
const couponCode = ref('');
const couponMessage = ref('');
const couponError = ref(false);
const discountPercent = ref(0);
const applyingCoupon = ref(false);
const placing = ref(false);
const orderPlaced = ref(false);
const invoiceNo = ref('');
const agreedToTerms = ref(false);
const formErrors = ref({});

const clearError = (field) => {
  delete formErrors.value[field];
};

const total = computed(() => {
  const sub = cart.subtotal;
  const discount = (sub * discountPercent.value) / 100;
  return sub - discount;
});

onMounted(async () => {
  if (auth.user) {
    cart.fetch();
    fetchCities();
    if (auth.user.name) shippingName.value = auth.user.name;
    if (auth.user.contact) shippingContact.value = auth.user.contact;
    if (auth.user.city_id) shippingCityId.value = auth.user.city_id;
    if (auth.user.address) shippingAddress.value = auth.user.address;
  }
});

const fetchCities = async () => {
  try {
    const res = await axios.get('/api/storefront/cities');
    cities.value = res.data.cities || [];
  } catch { /* silent */ }
};

const getProductAttributes = (item) => {
  if (!item.product?.attributes) return null;
  const attrs = item.product.attributes;
  if (typeof attrs === 'string') {
    try { return JSON.parse(attrs); } catch { return null; }
  }
  return attrs;
};

const getSelectedAttr = (item, key) => {
  if (!item.attributes) return '';
  try {
    const parsed = JSON.parse(item.attributes);
    return parsed[key] || '';
  } catch { return ''; }
};

const updateItemAttribute = async (item, key, value) => {
  let parsed = {};
  try { parsed = item.attributes ? JSON.parse(item.attributes) : {}; } catch {}
  parsed[key] = value;
  const newAttrs = JSON.stringify(parsed);

  await axios.post(`/api/cart/update/${item.id}`, {
    product: item.product_id,
    qty: item.qty,
    attributes: newAttrs,
  });
  await cart.fetch();
};

const adjustQty = async (item, delta) => {
  const newQty = Math.max(1, Number(item.qty) + delta);
  if (newQty !== Number(item.qty)) {
    await cart.update(item.id, newQty);
  }
};

const applyCoupon = async () => {
  if (!couponCode.value) return;
  applyingCoupon.value = true;
  couponMessage.value = '';
  try {
    const res = await axios.post('/api/storefront/validate-coupon', { code: couponCode.value });
    discountPercent.value = res.data.coupon?.discount || 0;
    couponMessage.value = `Coupon applied! ${discountPercent.value}% off`;
    couponError.value = false;
  } catch (e) {
    couponMessage.value = e.response?.data?.message || 'Invalid coupon';
    couponError.value = true;
    discountPercent.value = 0;
  } finally {
    applyingCoupon.value = false;
  }
};

const placeOrder = async () => {
  placing.value = true;
  try {
    const items = cart.items.map(i => ({
      product_id: i.product_id,
      product_price_id: i.product?.product_price?.id || i.product_id,
      unit_id: i.product?.product_price?.unit_id || 1,
      qty: i.qty,
      price: i.product?.product_price?.price || 0,
      attributes: i.attributes || null,
    }));

    const res = await axios.post('/api/storefront/place-order', {
      items,
      shipping_name: shippingName.value,
      shipping_contact: shippingContact.value,
      shipping_city_id: shippingCityId.value,
      shipping_address: shippingAddress.value,
      note: note.value || null,
      coupon_code: couponCode.value || null,
    });

    if (res.data.status === 'success') {
      invoiceNo.value = res.data.invoice_no;
      orderPlaced.value = true;
      cart.clear();
    }
  } catch (e) {
    const errData = e.response?.data;
    if (errData?.errors) {
      formErrors.value = {};
      for (const [key, msgs] of Object.entries(errData.errors)) {
        formErrors.value[key] = Array.isArray(msgs) ? msgs[0] : msgs;
      }
    } else {
      alert(errData?.message || 'Failed to place order');
    }
  } finally {
    placing.value = false;
  }
};
</script>

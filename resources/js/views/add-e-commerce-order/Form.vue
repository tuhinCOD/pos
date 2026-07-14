<template>
    <form id="order-form" @submit.prevent="save">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center gap-2">
                <h4 class="m-0">Edit Order</h4>
            </div>
            <router-link to="/e-commerce-orders" class="btn btn-primary btn-sm">
                <i class="bi bi-eye"></i> View Orders
            </router-link>
        </div>

        <div v-if="Object.values(localErrors).some(v => v)" class="alert alert-danger py-2 mb-2" @click.stop>
            <i class="bi bi-exclamation-triangle me-1"></i>
            {{ Object.values(localErrors).filter(v => v).join(', ') }}
        </div>

        <div class="my-3">
            <div class="row g-2">
                <div class="col-4">
                    <label class="form-label">Invoice No</label>
                    <input class="form-control" type="text" v-model="field.invoice_no" disabled />
                </div>
                <div class="col-4">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" v-model="field.status" id="status" name="status">
                        <option value="">Select Status</option>
                        <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <div class="form-text text-danger fw-bold" style="font-size: 10px;">{{ localErrors.status || errors.status?.[0] }}</div>
                </div>
                <div class="col-4">
                    <label class="form-label">Client</label>
                    <input class="form-control" type="text" :value="clientDisplay" disabled />
                </div>
            </div>
        </div>

        <hr>

        <div class="table-responsive mb-3">
            <table class="table table-bordered table-sm align-middle">
                <thead class="table-light">
                    <tr class="text-center">
                        <th style="width: 5%;">#</th>
                        <th style="width: 25%;">Product</th>
                        <th style="width: 7%;">Qty</th>
                        <th style="width: 10%;">Price</th>
                        <th style="width: 7%;">VAT</th>
                        <th style="width: 7%;">Discount</th>
                        <th style="width: 10%;">Shipping</th>
                        <th style="width: 10%;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="cartItems.length === 0">
                        <td colspan="8" class="text-center text-muted py-3">No items found.</td>
                    </tr>
                    <tr v-for="(item, index) in cartItems" :key="index" class="text-center">
                        <td>{{ index + 1 }}</td>
                        <td class="text-start">
                            <div>{{ item.product_name }}</div>
                            <small class="text-muted">Unit: {{ item.unit_name }}</small>
                            <div v-if="item.attributes" class="mt-1">
                                <span v-for="(val, key) in parseAttrs(item.attributes)" :key="key" class="badge bg-light text-dark me-1 border"> {{ key }}: {{ val }}</span>
                            </div>
                        </td>
                        <td>
                            <input type="number" step="0.001" class="form-control form-control-sm text-center" v-model.number="item.qty" style="width: 80px;" />
                        </td>
                        <td>
                            <input type="number" step="0.01" class="form-control form-control-sm text-end" v-model.number="item.price" style="width: 100px;" />
                        </td>
                        <td>
                            <input type="number" step="0.01" class="form-control form-control-sm text-end" v-model.number="item.vat" style="width: 80px;" />
                        </td>
                        <td>
                            <input type="number" step="0.01" class="form-control form-control-sm text-end" v-model.number="item.discount" style="width: 80px;" />
                        </td>
                        <td>
                            <input type="number" step="0.01" class="form-control form-control-sm text-end" v-model.number="item.shipping_fee" style="width: 80px;" />
                        </td>
                        <td class="fw-bold">{{ ((item.qty * item.price) + (item.qty * item.vat) - (item.qty * item.discount)).toFixed(2) }}</td>
                    </tr>
                </tbody>
                <tfoot v-if="cartItems.length > 0">
                    <tr class="table-secondary fw-bold">
                        <td colspan="7" class="text-end">Sub Total:</td>
                        <td class="text-center">{{ subTotal.toFixed(2) }}</td>
                    </tr>
                    <tr class="table-secondary">
                        <td colspan="7" class="text-end">Total VAT:</td>
                        <td class="text-center">{{ totalVat.toFixed(2) }}</td>
                    </tr>
                    <tr class="table-secondary">
                        <td colspan="7" class="text-end">Total Discount:</td>
                        <td class="text-center">{{ totalDiscount.toFixed(2) }}</td>
                    </tr>
                    <tr class="table-secondary">
                        <td colspan="7" class="text-end">Total Shipping:</td>
                        <td class="text-center">{{ totalShipping.toFixed(2) }}</td>
                    </tr>
                    <tr class="table-primary fw-bold">
                        <td colspan="7" class="text-end">Grand Total:</td>
                        <td class="text-center">{{ grandTotal.toFixed(2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-12 d-flex justify-content-end gap-2">
                <Button label="Reset" type="button" icon="bi bi-x-circle" color="secondary" @click="onReset" />
                <Button label="Save" type="submit" icon="bi bi-check-circle" color="primary" :disabled="cartItems.length === 0" />
            </div>
        </div>
    </form>
</template>

<script setup>
    import Button from '../../components/Button.vue';
    import { computed, reactive, ref, watch } from 'vue';
    import axios from 'axios';

    const props = defineProps({
        statuses: { type: Array, default: () => [] },
        products: { type: Array, default: () => [] },
        productPrices: { type: Array, default: () => [] },
        clients: { type: Array, default: () => [] },
        units: { type: Array, default: () => [] },
        errors: { type: Object, default: () => ({}) },
        editOrder: { type: Object, default: null },
    });

    const emit = defineEmits(["reset-form"]);

    const field = reactive({
        invoice_no: '',
        status: '',
        client: '',
    });

    const cartItems = ref([]);
    const saving = ref(false);
    const clientDisplay = ref('');

    const localErrors = reactive({});

    const clearLocalErrors = () => {
        for (const k in localErrors) delete localErrors[k];
    };

    const subTotal = computed(() => {
        return cartItems.value.reduce((sum, item) => sum + (item.qty * item.price), 0);
    });

    const totalVat = computed(() => {
        return cartItems.value.reduce((sum, item) => sum + (item.qty * item.vat), 0);
    });

    const totalDiscount = computed(() => {
        return cartItems.value.reduce((sum, item) => sum + (item.qty * item.discount), 0);
    });

    const totalShipping = computed(() => {
        return cartItems.value.reduce((sum, item) => sum + (item.qty * (item.shipping_fee || 0)), 0);
    });

    const grandTotal = computed(() => {
        return subTotal.value + totalVat.value - totalDiscount.value + totalShipping.value;
    });

    const parseAttrs = (attrs) => {
        if (!attrs) return {};
        if (typeof attrs === 'string') {
            try { return JSON.parse(attrs); } catch { return {}; }
        }
        return attrs;
    };

    const loadCartFromOrders = (orders) => {
        cartItems.value = orders.map(o => ({
            order_id: o.id,
            product_id: o.product_id,
            product_name: o.product?.name || '',
            unit_name: o.unit?.name || '',
            qty: o.qty,
            price: o.price,
            vat: o.vat,
            discount: o.discount || 0,
            shipping_fee: o.shipping_fee || 0,
            unit_id: o.unit_id,
            product_price_id: o.product_price_id,
            attributes: o.attributes || null,
        }));
        if (orders.length) {
            const o = orders[0];
            field.invoice_no = o.invoice_no || '';
            field.status = o.status_id || '';
            field.client = o.client_id || '';
            clientDisplay.value = o.client?.name || o.client?.contact || '';
        }
    };

    const save = async () => {
        clearLocalErrors();
        if (!field.status) {
            localErrors.status = 'Please select a status';
            return;
        }
        saving.value = true;

        let success = true;
        let savedCount = 0;

        for (const item of cartItems.value) {
            const payload = new FormData();
            payload.append('invoice_no', field.invoice_no);
            payload.append('status', field.status);
            payload.append('product', item.product_id);
            payload.append('product_price', item.product_price_id || '');
            payload.append('unit', item.unit_id || '');
            payload.append('qty', item.qty);
            payload.append('price', item.price);
            payload.append('vat', item.vat);
            payload.append('discount', item.discount || 0);
            payload.append('shipping_fee', item.shipping_fee || 0);
            payload.append('client', field.client);

            try {
                await axios.post(`/api/orders/update/${item.order_id}`, payload);
                savedCount++;
            } catch (e) {
                success = false;
                const errData = e.response?.data?.errors;
                if (errData) {
                    for (const key in errData) {
                        localErrors[key] = Array.isArray(errData[key]) ? errData[key][0] : errData[key];
                    }
                } else {
                    localErrors.general = e.response?.data?.message || e.message || 'Save failed';
                }
                break;
            }
        }

        saving.value = false;
        if (success) {
            emit('reset-form');
        }
    };

    const resetForm = () => {
        clearLocalErrors();
        cartItems.value = [];
        field.status = '';
    };

    const onReset = () => {
        emit("reset-form");
    };

    defineExpose({
        resetForm,
        loadCartFromOrders,
    });
</script>

<style lang="scss" scoped>
    input[type="number"]::-webkit-inner-spin-button,
    input[type="number"]::-webkit-outer-spin-button {
        opacity: 1;
    }
</style>

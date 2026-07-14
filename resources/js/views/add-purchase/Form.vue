<template>
    <form id="purchase-form" @submit.prevent="saveAsPending">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center gap-2">
                <h4 class="m-0">{{ isEditing ? 'Edit Purchase' : 'Add Purchase' }}</h4>
                <div class="dropdown" @click.stop>
                    <button class="btn btn-outline-secondary btn-sm position-relative" type="button" @click="togglePendingDropdown">
                        <i class="bi bi-inbox"></i>
                        <span class="ms-1">Pending</span>
                        <span v-if="pendingPurchases.length" class="badge bg-danger ms-1">{{ pendingPurchases.length }}</span>
                    </button>
                    <div v-if="showPendingDropdown" class="dropdown-menu show" style="min-width: 360px; max-height: 400px; overflow-y: auto; inset: 100% auto auto 0;">
                        <div class="dropdown-header d-flex justify-content-between align-items-center py-1">
                            <span class="fw-bold">Pending Purchases</span>
                            <button class="btn btn-sm btn-outline-primary py-0 px-1" @click="emit('refresh-pending')" title="Refresh" type="button">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                        <div v-if="loadingPending" class="text-center py-3 text-muted small">
                            <div class="spinner-border spinner-border-sm" role="status"></div> Loading...
                        </div>
                        <div v-else-if="!pendingPurchases.length" class="text-center py-3 text-muted small">
                            No pending purchases.
                        </div>
                        <div
                            v-for="group in pendingPurchases"
                            :key="group.invoice_no"
                            class="dropdown-item px-3 py-2 border-bottom"
                            style="cursor: pointer;"
                            @mousedown.prevent="loadInvoice(group.invoice_no, group.items)"
                        >
                            <div class="d-flex justify-content-between align-items-center">
                                <strong class="small">{{ group.invoice_no }}</strong>
                                <span class="badge bg-warning text-dark">{{ group.items.length }} items</span>
                            </div>
                            <div class="small text-muted mt-1">
                                <span v-if="group.supplier">Supplier: {{ group.supplier?.name || 'N/A' }}</span>
                                <span v-if="group.branch"> | {{ group.branch?.name || '' }}</span>
                            </div>
                            <div class="small text-muted">
                                Total: {{ calculateTotal(group.items).toFixed(2) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <router-link to="/purchases" class="btn btn-primary btn-sm">
                <i class="bi bi-eye"></i> View Purchases
            </router-link>
        </div>

        <div v-if="Object.values(localErrors).some(v => v)" class="alert alert-danger py-2 mb-2">
            <i class="bi bi-exclamation-triangle me-1"></i>
            {{ Object.values(localErrors).filter(v => v).join(', ') }}
        </div>

        <div class="my-3">
            <div class="row g-2">
                <div class="col-3">
                    <label class="form-label">Invoice No <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input class="form-control" type="text" v-model="field.invoice_no" placeholder="Invoice No" :disabled="isEditing" @blur="loadInvoiceData" />
                        <button v-if="!isEditing" class="btn btn-outline-primary" type="button" @click="regenerateInvoiceNo" title="Generate New Invoice">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                    <div class="form-text text-danger fw-bold" style="font-size: 10px;">{{ localErrors.invoice_no || errors.invoice_no?.[0] }}</div>
                </div>
                <div class="col-3">
                    <label for="branch" class="form-label">Branch <span class="text-danger">*</span></label>
                    <select class="form-select" v-model="field.branch" id="branch" name="branch">
                        <option value="">Select Branch</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                    <div class="form-text text-danger fw-bold" style="font-size: 10px;">{{ localErrors.branch || errors.branch?.[0] }}</div>
                </div>
                <div class="col-3">
                    <label for="supplier" class="form-label">Supplier</label>
                    <select class="form-select" v-model="field.supplier" id="supplier" name="supplier">
                        <option value="">Select Supplier</option>
                        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <div class="form-text text-danger fw-bold" style="font-size: 10px;">{{ localErrors.supplier || errors.supplier?.[0] }}</div>
                </div>
                <div class="col-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" v-model="field.status" id="status" name="status">
                        <option value="">Select Status</option>
                        <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <div class="form-text text-danger fw-bold" style="font-size: 10px;">{{ localErrors.status }}</div>
                </div>
            </div>
        </div>

        <hr>

        <div class="row g-2 mb-3">
            <div class="col-3 position-relative">
                <label class="form-label">Search Product</label>
                <div class="input-group" v-if="selectedProduct">
                    <input class="form-control" type="text" :value="selectedProduct.name" disabled />
                    <button class="btn btn-outline-secondary" type="button" @click="clearSelection">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
                <template v-else>
                    <input
                        class="form-control"
                        type="text"
                        v-model="productSearchTerm"
                        placeholder="Type product name..."
                        @input="showDropdown = true"
                        @focus="showDropdown = true"
                        @blur="hideDropdown"
                        ref="searchInput"
                    />
                    <ul v-if="showDropdown && filteredProducts.length" class="product-dropdown list-unstyled">
                        <li v-for="p in filteredProducts" :key="p.id" class="product-dropdown-item px-3 py-2" @mousedown.prevent="selectProduct(p)">
                            <span class="fw-bold">{{ p.name }}</span>
                            <span class="text-muted small ms-2">({{ p.barcode }})</span>
                        </li>
                    </ul>
                    <div v-if="showDropdown && productSearchTerm && !filteredProducts.length" class="product-dropdown list-unstyled">
                        <div class="px-3 py-2 text-muted">No products found.</div>
                    </div>
                </template>
            </div>
            <div class="col-2" v-if="selectedProduct">
                <label class="form-label">Product Unit</label>
                <select class="form-select" v-model="addProdUnit">
                    <option value="">Select Prod Unit</option>
                    <option v-if="selectedProduct.unit" :value="selectedProduct.unit?.id">{{ selectedProduct.unit?.name }}</option>
                </select>
            </div>
            <div class="col-1" v-if="selectedProduct">
                <label class="form-label">Prod Qty</label>
                <input type="number" step="0.001" class="form-control" v-model.number="addProdQty" min="0.001" />
            </div>
            <div class="col-2" v-if="selectedProduct">
                <label class="form-label">Unit</label>
                <select class="form-select" v-model="addUnit">
                    <option value="">Select Unit</option>
                    <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
            </div>
            <div class="col-1" v-if="selectedProduct">
                <label class="form-label">Qty</label>
                <input type="number" step="0.001" class="form-control" v-model.number="addQty" min="0.001" />
            </div>
            <div class="col-1" v-if="selectedProduct">
                <label class="form-label">Price</label>
                <input type="number" step="0.01" class="form-control" v-model.number="addPrice" min="0" />
            </div>
            <div class="col-2 d-flex align-items-end gap-2" v-if="selectedProduct">
                <button type="button" class="btn btn-success" @click="addToCart" :disabled="!addQty || !addUnit">
                    <i class="bi bi-cart-plus"></i> Add
                </button>
                <button type="button" class="btn btn-outline-secondary" @click="clearSelection">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        </div>

        <div v-if="selectedProduct?.attributes && typeof selectedProduct.attributes === 'object' && Object.keys(selectedProduct.attributes).length" class="row g-2 mb-3">
            <div class="col-12">
                <label class="form-label fw-bold">Product Attributes</label>
                <div class="row g-2">
                        <div v-for="(values, key) in selectedProduct.attributes" :key="key" class="col-auto">
                        <label class="form-label text-capitalize">{{ key }}</label>
                        <div class="d-flex flex-wrap gap-2">
                            <div v-for="val in (Array.isArray(values) ? values : [values])" :key="val" class="form-check">
                                <input class="form-check-input" type="radio" :value="val" v-model="selectedAttributes[key]" :name="key" />
                                <label class="form-check-label">{{ val }}</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive mb-3">
            <table class="table table-bordered table-sm align-middle">
                <thead class="table-light">
                    <tr class="text-center">
                        <th style="width: 5%;">#</th>
                        <th style="width: 20%;">Product</th>
                        <th style="width: 12%;">Attributes</th>
                        <th style="width: 6%;">Unit</th>
                        <th style="width: 6%;">Qty</th>
                        <th style="width: 8%;">Prod Unit</th>
                        <th style="width: 6%;">Prod Qty</th>
                        <th style="width: 10%;">Price</th>
                        <th style="width: 7%;">VAT</th>
                        <th style="width: 7%;">Discount</th>
                        <th style="width: 10%;">Subtotal</th>
                        <th style="width: 5%;"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="cartItems.length === 0">
                        <td colspan="12" class="text-center text-muted py-3">No items added. Search and add products above.</td>
                    </tr>
                    <tr v-for="(item, index) in cartItems" :key="index" class="text-center">
                        <td>{{ index + 1 }}</td>
                        <td class="text-start">{{ item.product_name }}</td>
                        <td class="text-start small">
                            <div v-if="item.attributes && Object.keys(item.attributes).length">
                                <div v-for="(v, k) in item.attributes" :key="k" class="text-nowrap">{{ k }}: {{ v }}</div>
                            </div>
                            <span v-else class="text-muted">-</span>
                        </td>
                        <td>{{ item.unit_name || '-' }}</td>
                        <td>
                            <input type="number" step="0.001" class="form-control form-control-sm text-center" v-model.number="item.qty" @input="item.qty = Math.max(0.001, item.qty || 0.001)" style="width: 70px;" />
                        </td>
                        <td>{{ item.product_unit_name || '-' }}</td>
                        <td>
                            <input type="number" step="0.001" class="form-control form-control-sm text-center" v-model.number="item.product_unit_qty" @input="item.product_unit_qty = Math.max(0.001, item.product_unit_qty || 0.001)" style="width: 70px;" />
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
                        <td class="fw-bold">{{ ((item.qty * item.price) + (Number(item.vat) || 0) - (Number(item.discount) || 0)).toFixed(2) }}</td>
                        <td>
                            <button type="button" class="btn btn-outline-danger btn-sm" @click="removeCartItem(index)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
                <tfoot v-if="cartItems.length > 0">
                    <tr class="table-secondary fw-bold">
                        <td colspan="10" class="text-end">Sub Total:</td>
                        <td class="text-center">{{ subTotal.toFixed(2) }}</td>
                        <td></td>
                    </tr>
                    <tr class="table-secondary">
                        <td colspan="10" class="text-end">Total VAT:</td>
                        <td class="text-center">{{ totalVat.toFixed(2) }}</td>
                        <td></td>
                    </tr>
                    <tr class="table-secondary">
                        <td colspan="10" class="text-end">Total Discount:</td>
                        <td class="text-center">{{ totalDiscount.toFixed(2) }}</td>
                        <td></td>
                    </tr>
                    <tr class="table-primary fw-bold">
                        <td colspan="10" class="text-end">Grand Total:</td>
                        <td class="text-center">{{ grandTotal.toFixed(2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6">
                <label for="remarks" class="form-label">Remarks</label>
                <textarea name="remarks" class="form-control" id="remarks" placeholder="Write remarks here..." v-model="field.remarks" rows="2"></textarea>
                <div class="form-text text-danger fw-bold" style="font-size: 10px;">{{ localErrors.remarks }}</div>
            </div>
            <div class="col-6 d-flex align-items-end justify-content-end gap-2">
                <Button label="Reset" type="button" icon="bi bi-x-circle" color="secondary" @click="onReset" />
                <Button label="Save" type="button" icon="bi bi-clock" color="warning" @click="saveAsPending(true)" :disabled="cartItems.length === 0" />
                <Button label="Payment" type="button" icon="bi bi-credit-card" color="info" @click="openPayment" :disabled="cartItems.length === 0" />
            </div>
        </div>
    </form>

    <PaymentModal
        v-if="showPaymentModal"
        :invoice-no="savedInvoiceNo || field.invoice_no"
        :amount="paymentAmount"
        :payment-methods="paymentMethods"
        payment-type="purchase"
        @close="showPaymentModal = false"
        @payment-completed="onPaymentCompleted"
    />
</template>

<script setup>
    import Button from '../../components/Button.vue';
    import PaymentModal from '../../components/PaymentModal.vue';
    import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
    import axios from 'axios';

    const props = defineProps({
        statuses: { type: Array, default: () => [] },
        branches: { type: Array, default: () => [] },
        products: { type: Array, default: () => [] },
        suppliers: { type: Array, default: () => [] },
        units: { type: Array, default: () => [] },
        paymentMethods: { type: Array, default: () => [] },
        errors: { type: Object, default: () => ({}) },
        editPurchase: { type: Object, default: null },
        pendingPurchases: { type: Array, default: () => [] },
        loadingPending: { type: Boolean, default: false },
    });

    const emit = defineEmits([
        "reset-form",
        "update-purchase",
        "refresh-pending",
    ]);

    function generateInvoiceNo() {
        const now = new Date();
        const y = now.getFullYear();
        const m = String(now.getMonth() + 1).padStart(2, '0');
        const d = String(now.getDate()).padStart(2, '0');
        const rand = Math.random().toString(36).substring(2, 6).toUpperCase();
        return `P-${y}${m}${d}-${rand}`;
    }

    const regenerateInvoiceNo = () => {
        field.invoice_no = generateInvoiceNo();
    };

    const loadInvoiceData = async () => {
        if (!field.invoice_no || isEditing.value) return;
        try {
            const res = await axios.get(`/api/purchases/by-invoice/${field.invoice_no}`);
            const purchases = res.data?.purchases;
            if (purchases && purchases.length) {
                setPendingData(field.invoice_no, purchases);
            }
        } catch {}
    };

    const field = reactive({
        invoice_no: '',
        status: '',
        supplier: '',
        branch: '',
        product: '',
        unit: '',
        qty: '',
        product_unit_id: '',
        product_unit_qty: '',
        price: '',
        vat: '',
        discount: '',
        remarks: '',
    });

    const cartItems = ref([]);
    const savedInvoiceNo = ref(null);
    const saving = ref(false);
    const isEditing = ref(false);
    const searchInput = ref(null);

    const selectedProduct = ref(null);
    const addQty = ref(1);
    const addPrice = ref(0);
    const addUnit = ref('');
    const addProdQty = ref(1);
    const addProdUnit = ref('');
    const productSearchTerm = ref('');
    const showDropdown = ref(false);
    let blurTimeout = null;

    const showPaymentModal = ref(false);
    const showPendingDropdown = ref(false);
    const paidAmounts = ref({});
    const paymentAmount = ref(0);

    const togglePendingDropdown = () => {
        showPendingDropdown.value = !showPendingDropdown.value;
    };

    const closePendingDropdown = () => {
        showPendingDropdown.value = false;
    };

    const loadInvoice = (invoiceNo, items) => {
        setPendingData(invoiceNo, items);
        closePendingDropdown();
    };

    const calculateTotal = (items) => {
        return items.reduce((sum, item) => {
            const q = item.qty || item.product_unit_qty;
            return sum + (q * item.price) + (q * (item.vat || 0)) - (q * (item.discount || 0));
        }, 0);
    };

    const filteredProducts = computed(() => {
        const term = productSearchTerm.value.toLowerCase();
        return props.products.filter(p =>
            p.name.toLowerCase().includes(term) ||
            p.barcode.toLowerCase().includes(term)
        ).slice(0, 10);
    });

    const subTotal = computed(() => {
        return cartItems.value.reduce((sum, item) => sum + (item.qty * item.price), 0);
    });

    const totalVat = computed(() => {
        return cartItems.value.reduce((sum, item) => sum + (Number(item.vat) || 0), 0);
    });

    const totalDiscount = computed(() => {
        return cartItems.value.reduce((sum, item) => sum + (Number(item.discount) || 0), 0);
    });

    const grandTotal = computed(() => {
        return subTotal.value + totalVat.value - totalDiscount.value;
    });

    const hideDropdown = () => {
        blurTimeout = setTimeout(() => { showDropdown.value = false; }, 200);
    };

    const selectProduct = (product) => {
        selectedProduct.value = product;
        field.product = product.id;
        productSearchTerm.value = '';
        showDropdown.value = false;
        addQty.value = 1;
        addProdQty.value = 1;
        addPrice.value = 0;
        addUnit.value = '';
        addProdUnit.value = '';
        for (const key in selectedAttributes) delete selectedAttributes[key];
        if (product.attributes && typeof product.attributes === 'object') {
            for (const key of Object.keys(product.attributes)) {
                selectedAttributes[key] = '';
            }
        }
        setTimeout(() => searchInput.value?.focus(), 100);
    };

    const selectedAttributes = reactive({});

    const buildAttributesPayload = () => {
        const obj = {};
        for (const key in selectedAttributes) {
            if (selectedAttributes[key]) {
                obj[key] = selectedAttributes[key];
            }
        }
        return Object.keys(obj).length ? JSON.stringify(obj) : null;
    };

    const clearSelection = () => {
        selectedProduct.value = null;
        productSearchTerm.value = '';
        addQty.value = 1;
        addProdQty.value = 1;
        addPrice.value = 0;
        addUnit.value = '';
        addProdUnit.value = '';
        field.product = '';
        for (const key in selectedAttributes) delete selectedAttributes[key];
        setTimeout(() => searchInput.value?.focus(), 100);
    };

    const addToCart = () => {
        if (!selectedProduct.value || !addQty.value || !addUnit.value) return;

        const attrs = buildAttributesPayload();

        const existing = cartItems.value.find(item =>
            item.product_id === selectedProduct.value.id &&
            item.unit_id === addUnit.value &&
            JSON.stringify(item.attributes || {}) === JSON.stringify(attrs ? JSON.parse(attrs) : {})
        );
        if (existing) {
            existing.qty = Number(existing.qty) + Number(addQty.value);
            if (addProdQty.value) existing.product_unit_qty = Number(existing.product_unit_qty) + Number(addProdQty.value);
        } else {
            const unit = props.units.find(u => u.id === addUnit.value);
            const prodUnit = props.units.find(u => u.id === addProdUnit.value);
            const item = {
                product_id: selectedProduct.value.id,
                product_name: selectedProduct.value.name,
                unit_id: addUnit.value,
                unit_name: unit?.name || '',
                qty: Number(addQty.value),
                product_unit_id: addProdUnit.value || '',
                product_unit_name: prodUnit?.name || '',
                product_unit_qty: Number(addProdQty.value || addQty.value),
                price: Number(addPrice.value) || 0,
                vat: 0,
                discount: 0,
            };
            if (attrs) item.attributes = JSON.parse(attrs);
            cartItems.value.push(item);
        }
        clearSelection();
    };

    const removeCartItem = (index) => {
        cartItems.value.splice(index, 1);
    };

    const localErrors = reactive({});

    const clearLocalErrors = () => {
        for (const k in localErrors) delete localErrors[k];
    };

    let loadedPurchaseIds = [];

    const fetchInvoiceTotalPaid = async (invoiceNo) => {
        try {
            const res = await axios.get(`/api/v1/payments/total/${invoiceNo}`);
            return Number(res.data?.total_paid || 0);
        } catch {
            return 0;
        }
    };

    const saveAsPending = async (resetAfterSave = false, forcePending = false) => {
        if (!field.branch) {
            localErrors.branch = 'Please select a branch';
            return false;
        }
        clearLocalErrors();
        saving.value = true;

        const selectedStatus = field.status
            ? props.statuses.find(s => Number(s.id) === Number(field.status))
            : null;
        const pendingStatus = props.statuses.find(s => s.name === 'pending');
        const targetStatusId = forcePending
            ? pendingStatus?.id || props.statuses[0]?.id
            : selectedStatus?.id || pendingStatus?.id || props.statuses[0]?.id;

        const savedPurchaseIds = [];
        let success = true;

        for (const item of cartItems.value) {
            const payload = new FormData();
            payload.append('invoice_no', field.invoice_no);
            payload.append('status', targetStatusId);
            payload.append('branch', field.branch);
            payload.append('supplier', field.supplier || '');
            payload.append('product', item.product_id);
            payload.append('unit', item.unit_id);
            payload.append('qty', item.qty);
            payload.append('product_unit_id', item.product_unit_id);
            payload.append('product_unit_qty', item.product_unit_qty);
            payload.append('price', item.price);
            payload.append('vat', item.vat || 0);
            payload.append('discount', item.discount || 0);
            payload.append('remarks', field.remarks || '');
            if (item.attributes && Object.keys(item.attributes).length) {
                payload.append('attributes', JSON.stringify(item.attributes));
            }

            try {
                if (item.purchase_id) {
                    await axios.post(`/api/purchases/update/${item.purchase_id}`, payload);
                    savedPurchaseIds.push(item.purchase_id);
                } else {
                    const res = await axios.post('/api/purchases', payload);
                    const newId = res.data?.purchase?.id || res.data?.data?.id;
                    if (newId) {
                        savedPurchaseIds.push(newId);
                        item.purchase_id = newId;
                    }
                }
            } catch (e) {
                success = false;
                const errData = e.response?.data?.errors;
                if (errData) {
                    for (const key in errData) {
                        localErrors[key] = Array.isArray(errData[key]) ? errData[key][0] : errData[key];
                    }
                } else {
                    const msg = e.response?.data?.message || e.message || 'Save failed';
                    localErrors.general = msg;
                }
                break;
            }
        }

        if (success) {
            for (const oldId of loadedPurchaseIds) {
                if (!savedPurchaseIds.includes(oldId)) {
                    try { await axios.post(`/api/purchases/delete/${oldId}`); } catch {}
                }
            }

            savedInvoiceNo.value = field.invoice_no;
            loadedPurchaseIds = savedPurchaseIds;

            saving.value = false;
            if (resetAfterSave) {
                resetForm();
                emit('reset-form');
            }
            return true;
        }
        saving.value = false;
        return false;
    };

    const openPayment = async () => {
        if (cartItems.value.length === 0) return;
        if (!field.branch) {
            localErrors.branch = 'Please select a branch';
            return;
        }
        if (!field.invoice_no) {
            localErrors.invoice_no = 'Invoice number required';
            return;
        }
        const saved = await saveAsPending(false, true);
        if (!saved) return;

        const invoiceNo = savedInvoiceNo.value || field.invoice_no;
        const backendPaid = await fetchInvoiceTotalPaid(invoiceNo);
        const sessionPaid = paidAmounts.value[invoiceNo] || 0;
        const totalPaid = Math.max(backendPaid, sessionPaid);
        const remaining = grandTotal.value - totalPaid;

        if (remaining <= 0) {
            localErrors.general = `Invoice ${invoiceNo} is already fully paid.`;
            return;
        }

        paymentAmount.value = remaining;
        showPaymentModal.value = true;
    };

    const onPaymentCompleted = (paidAmount) => {
        showPaymentModal.value = false;
        if (savedInvoiceNo.value && paidAmount) {
            const prev = paidAmounts.value[savedInvoiceNo.value] || 0;
            paidAmounts.value[savedInvoiceNo.value] = prev + paidAmount;
        }
    };

    const loadCartFromPurchases = (purchases) => {
        cartItems.value = purchases.map(p => ({
            product_id: p.product_id,
            product_name: p.product?.name || '',
            unit_id: p.unit_id || '',
            product_unit_id: p.product_unit_id || '',
            unit_name: p.old_unit?.name || p.unit?.name || '',
            product_unit_name: p.unit?.name || '',
            qty: Number(p.qty || 0),
            product_unit_qty: Number(p.product_unit_qty || 0),
            price: p.price,
            vat: p.vat,
            discount: p.discount || 0,
            attributes: p.attributes && typeof p.attributes === 'object' ? p.attributes : (p.attributes ? JSON.parse(p.attributes) : null),
            purchase_id: p.id,
        }));
        loadedPurchaseIds = purchases.map(p => p.id);
    };

    const setPendingData = (invoiceNo, purchases) => {
        isEditing.value = true;
        field.invoice_no = invoiceNo;
        savedInvoiceNo.value = invoiceNo;
        if (purchases.length) {
            const p = purchases[0];
            field.branch = p.branch_id || p.branch?.id || '';
            field.supplier = p.supplier_id || p.supplier?.id || '';
            field.status = p.status_id || '';
        }
        loadCartFromPurchases(purchases);
    };

    const resetForm = () => {
        cartItems.value = [];
        loadedPurchaseIds = [];
        selectedProduct.value = null;
        productSearchTerm.value = '';
        addQty.value = 1;
        addProdQty.value = 1;
        addPrice.value = 0;
        addUnit.value = '';
        addProdUnit.value = '';
        field.supplier = '';
        field.branch = '';
        field.status = '';
        field.remarks = '';
        savedInvoiceNo.value = null;
        isEditing.value = false;
        for (const key in selectedAttributes) delete selectedAttributes[key];
        regenerateInvoiceNo();
    };

    defineExpose({
        resetForm,
        field,
        setInvoiceNo: (no) => { field.invoice_no = no; },
        setPendingData,
        loadCartFromPurchases,
    });

    const handleClickOutside = (e) => {
        if (showPendingDropdown.value) {
            const dropdown = e.target.closest('.dropdown');
            if (!dropdown) {
                closePendingDropdown();
            }
        }
    };

    onMounted(() => {
        document.addEventListener('mousedown', handleClickOutside);
        if (!props.editPurchase) {
            field.invoice_no = generateInvoiceNo();
        }
    });

    onUnmounted(() => {
        document.removeEventListener('mousedown', handleClickOutside);
    });

    watch(() => props.editPurchase, (val) => {
        if (val) {
            isEditing.value = true;
            field.invoice_no = val.invoice_no || '';
            field.status = val.status_id || '';
            field.supplier = val.supplier_id || '';
            field.branch = val.branch_id || '';
            field.product = val.product_id || '';
            if (val.product) {
                selectedProduct.value = val.product;
                productSearchTerm.value = '';
            }
            field.unit = val.product_unit_id || val.unit_id || '';
            field.qty = val.product_unit_qty || val.qty || '';
            field.product_unit_id = val.product_unit_id || val.unit_id || '';
            field.product_unit_qty = val.product_unit_qty || val.qty || '';
            field.price = val.price || '';
            field.vat = val.vat || '';
            field.discount = val.discount || '';
            field.remarks = val.remarks || '';
        } else {
            isEditing.value = false;
        }
    }, { immediate: true });

    const onReset = () => {
        emit("reset-form");
    };
</script>

<style lang="scss" scoped>
    .product-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 1000;
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        max-height: 250px;
        overflow-y: auto;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .product-dropdown-item {
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
    }
    .product-dropdown-item:hover {
        background-color: #e9ecef;
    }
    input[type="number"]::-webkit-inner-spin-button,
    input[type="number"]::-webkit-outer-spin-button {
        opacity: 1;
    }
</style>
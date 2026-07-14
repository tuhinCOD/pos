<template>
    <form id="sale-form" @submit.prevent="saveAsPending">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center gap-2">
                <h4 class="m-0">{{ isEditing ? 'Edit Sale' : 'Add Sale' }}</h4>
            </div>
            <router-link to="/sales" class="btn btn-primary btn-sm">
                <i class="bi bi-eye"></i> View Sales
            </router-link>
        </div>

        <div v-if="Object.values(localErrors).some(v => v)" class="alert alert-danger py-2 mb-2" @click.stop>
            <i class="bi bi-exclamation-triangle me-1"></i>
            {{ Object.values(localErrors).filter(v => v).join(', ') }}
        </div>

        <div class="my-3">
            <div class="row g-2">
                <div class="col-3">
                    <label class="form-label">Invoice No <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input class="form-control" type="text" v-model="field.invoice_no" placeholder="Invoice No" :disabled="isEditing" />
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
                    <label for="client" class="form-label">Client (Phone Number)</label>
                    <input class="form-control" type="text" v-model="clientPhone" placeholder="Enter client phone number..." @input="handleClientPhoneInput" />
                    <div class="form-text text-danger fw-bold" style="font-size: 10px;">{{ localErrors.client || errors.client?.[0] }}</div>
                    <div v-if="clientFound" class="form-text text-success fw-bold" style="font-size: 10px;">Client: {{ clientFound.name }}</div>
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
            <div class="col-4 position-relative">
                <label class="form-label">Search Product / Scan Barcode</label>
                <input
                    class="form-control"
                    type="text"
                    v-model="productSearchTerm"
                    placeholder="Type product name or scan barcode..."
                    @input="showDropdown = true; handleScanInput()"
                    @focus="showDropdown = true"
                    @blur="hideDropdown"
                    ref="searchInput"
                />
                <ul v-if="showDropdown && searchResults.length" class="product-dropdown list-unstyled">
                    <template v-for="item in searchResults" :key="item.type === 'product' ? 'p-' + item.id : 'bc-' + item.id">
                        <li v-if="item.type === 'product'" class="product-dropdown-item px-3 py-2 fw-bold" @mousedown.prevent="selectProduct(item)">
                            {{ item.name }}
                        </li>
                        <li v-else class="product-dropdown-item px-3 py-2 ps-4 small" @mousedown.prevent="selectBarcodeFromSearch(item)">
                            <span class="text-muted">{{ item.barcode }}</span>
                        </li>
                    </template>
                </ul>
                <div v-if="showDropdown && productSearchTerm && !searchResults.length" class="product-dropdown list-unstyled">
                    <div class="px-3 py-2 text-muted">No products found.</div>
                </div>
            </div>
            <div class="col-4" v-if="selectedProduct">
                <label class="form-label">Qty</label>
                <input type="number" step="0.001" class="form-control" v-model.number="addQty" min="0.001" />
            </div>
            <div class="col-4 d-flex align-items-end gap-2" v-if="selectedProduct">
                <button type="button" class="btn btn-success" @click="addToCart" :disabled="!selectedBarcode && !selectedProduct">
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

        <div v-if="selectedProduct" class="row g-2 mb-3">
            <div class="col-12">
                <label class="form-label fw-bold">Custom Attributes <span class="text-muted fw-normal">(optional)</span></label>
                <div v-for="(attr, index) in customAttributes" :key="index" class="input-group mb-1" style="max-width: 500px;">
                    <input type="text" class="form-control form-control-sm" v-model="attr.key" placeholder="Key (e.g. brand)" />
                    <input type="text" class="form-control form-control-sm" v-model="attr.value" placeholder="Value (e.g. Nike)" />
                    <button type="button" class="btn btn-outline-danger btn-sm" @click="removeCustomAttribute(index)">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" @click="addCustomAttribute">
                    <i class="bi bi-plus"></i> Add Custom Attribute
                </button>
            </div>
        </div>

        <div class="table-responsive mb-3">
            <table class="table table-bordered table-sm align-middle">
                <thead class="table-light">
                    <tr class="text-center">
                        <th style="width: 5%;">#</th>
                        <th style="width: 22%;">Product</th>
                        <th style="width: 12%;">Attributes</th>
                        <th style="width: 7%;">Qty</th>
                        <th style="width: 10%;">Price</th>
                        <th style="width: 7%;">VAT</th>
                        <th style="width: 7%;">Discount</th>
                        <th style="width: 10%;">Subtotal</th>
                        <th style="width: 5%;"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="cartItems.length === 0">
                        <td colspan="9" class="text-center text-muted py-3">No items added. Search and add products above.</td>
                    </tr>
                    <tr v-for="(item, index) in cartItems" :key="index" class="text-center">
                        <td>{{ index + 1 }}</td>
                        <td class="text-start">{{ item.product_name }}</td>
                        <td class="text-start small" style="min-width: 120px;">
                            <div v-if="item.attributes && Object.keys(item.attributes).length" class="d-flex flex-wrap gap-1">
                                <span v-for="(v, k) in item.attributes" :key="k" class="badge bg-light text-dark d-inline-flex align-items-center gap-1">
                                    {{ k }}: {{ v }}
                                </span>
                            </div>
                            <span v-else class="text-muted">-</span>
                        </td>
                        <td>
                            <input type="number" step="0.001" class="form-control form-control-sm text-center" v-model.number="item.qty" @input="item.qty = Math.max(0.001, item.qty || 0.001)" style="width: 80px;" />
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
                        <td class="fw-bold">{{ ((item.qty * item.price) + (item.qty * item.vat) - (item.qty * item.discount)).toFixed(2) }}</td>
                        <td>
                            <button type="button" class="btn btn-outline-danger btn-sm" @click="removeCartItem(index)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
                <tfoot v-if="cartItems.length > 0">
                    <tr class="table-secondary fw-bold">
                        <td colspan="7" class="text-end">Sub Total:</td>
                        <td class="text-center">{{ subTotal.toFixed(2) }}</td>
                        <td></td>
                    </tr>
                    <tr class="table-secondary">
                        <td colspan="7" class="text-end">Total Point:</td>
                        <td class="text-center">{{ totalPoint.toFixed(2) }}</td>
                        <td></td>
                    </tr>
                    <tr class="table-secondary">
                        <td colspan="7" class="text-end">Total VAT:</td>
                        <td class="text-center">{{ totalVat.toFixed(2) }}</td>
                        <td></td>
                    </tr>
                    <tr class="table-secondary">
                        <td colspan="7" class="text-end">Total Discount:</td>
                        <td class="text-center">{{ totalDiscount.toFixed(2) }}</td>
                        <td></td>
                    </tr>
                    <tr class="table-primary fw-bold">
                        <td colspan="7" class="text-end">Grand Total:</td>
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
        @close="showPaymentModal = false"
        @payment-completed="onPaymentCompleted"
    />
    <ReceiptPrint
        v-if="showReceipt"
        :invoice-no="receiptData.invoiceNo"
        :date="receiptData.date"
        :branch-name="receiptData.branchName"
        :client-name="receiptData.clientName"
        :items="receiptData.items"
        :subtotal="receiptData.subtotal"
        :total-vat="receiptData.totalVat"
        :total-discount="receiptData.totalDiscount"
        :grand-total="receiptData.grandTotal"
        :paid-amount="receiptData.paidAmount"
        :due-amount="receiptData.dueAmount"
        @close="showReceipt = false"
    />
</template>

<script setup>
    import Input from '../../components/Input.vue';
    import Button from '../../components/Button.vue';
    import PaymentModal from '../../components/PaymentModal.vue';
    import ReceiptPrint from '../../components/ReceiptPrint.vue';
    import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
    import axios from 'axios';

    const props = defineProps({
        statuses: { type: Array, default: () => [] },
        branches: { type: Array, default: () => [] },
        products: { type: Array, default: () => [] },
        productPrices: { type: Array, default: () => [] },
        clients: { type: Array, default: () => [] },
        units: { type: Array, default: () => [] },
        barcodes: { type: Array, default: () => [] },
        paymentMethods: { type: Array, default: () => [] },
        errors: { type: Object, default: () => ({}) },
        editSale: { type: Object, default: null },
    });

    const emit = defineEmits([
        "reset-form",
    ]);

    function generateInvoiceNo() {
        const now = new Date();
        const y = now.getFullYear();
        const m = String(now.getMonth() + 1).padStart(2, '0');
        const d = String(now.getDate()).padStart(2, '0');
        const rand = Math.random().toString(36).substring(2, 6).toUpperCase();
        return `S-${y}${m}${d}-${rand}`;
    }

    const regenerateInvoiceNo = () => {
        field.invoice_no = generateInvoiceNo();
    };

    const field = reactive({
        invoice_no: '',
        status: '',
        branch: '',
        client: '',
        product: '',
        product_price: '',
        unit: '',
        qty: '',
        price: '',
        vat: '',
        discount: '',
        point: '',
        remarks: '',
    });

    const cartItems = ref([]);
    const savedInvoiceNo = ref(null);
    const saving = ref(false);
    const isEditing = ref(false);
    const searchInput = ref(null);

    const selectedProduct = ref(null);
    const selectedBarcode = ref(null);
    const addQty = ref(1);
    const productSearchTerm = ref('');
    const showDropdown = ref(false);
    let blurTimeout = null;

    const selectedAttributes = reactive({});
    const customAttributes = ref([]);

    const addCustomAttribute = () => {
        customAttributes.value.push({ key: '', value: '' });
    };

    const removeCustomAttribute = (index) => {
        customAttributes.value.splice(index, 1);
    };

    const buildAttributesPayload = () => {
        const obj = {};
        for (const key in selectedAttributes) {
            if (selectedAttributes[key]) {
                obj[key] = selectedAttributes[key];
            }
        }
        for (const attr of customAttributes.value) {
            if (attr.key && attr.value) {
                obj[attr.key] = attr.value;
            }
        }
        return Object.keys(obj).length ? JSON.stringify(obj) : null;
    };

    const showPaymentModal = ref(false);
    const paidAmounts = ref({});
    const lastSavedStatus = ref('');
    const paymentAmount = ref(0);
    const showReceipt = ref(false);
    const receiptData = reactive({
        invoiceNo: '',
        date: '',
        branchName: '',
        clientName: '',
        items: [],
        subtotal: 0,
        totalVat: 0,
        totalDiscount: 0,
        grandTotal: 0,
        paidAmount: 0,
        dueAmount: 0,
    });

    const clientPhone = ref('');
    const clientFound = ref(null);

    const filteredProducts = computed(() => {
        const term = productSearchTerm.value.toLowerCase();
        return props.products.filter(p =>
            p.name.toLowerCase().includes(term) ||
            p.barcode.toLowerCase().includes(term)
        ).slice(0, 10);
    });

    const searchResults = computed(() => {
        const term = productSearchTerm.value.toLowerCase();
        if (!term) return [];

        const matchedProducts = props.products.filter(p =>
            p.name.toLowerCase().includes(term)
        ).slice(0, 5);

        const barcodeMatchedIds = new Set();
        props.barcodes.filter(bc =>
            bc.status_id === 1 &&
            bc.barcode.toLowerCase().includes(term)
        ).forEach(bc => barcodeMatchedIds.add(bc.product_id));

        for (const pid of barcodeMatchedIds) {
            if (!matchedProducts.some(p => p.id === pid)) {
                const p = props.products.find(p => p.id === pid);
                if (p) matchedProducts.push(p);
            }
        }

        const results = [];
        for (const product of matchedProducts) {
            results.push({ type: 'product', ...product });
            const productBarcodes = props.barcodes.filter(bc =>
                bc.product_id === product.id && bc.status_id === 1
            );
            for (const bc of productBarcodes) {
                results.push({ type: 'barcode', ...bc, productName: product.name });
            }
        }
        return results;
    });

    const subTotal = computed(() => {
        return cartItems.value.reduce((sum, item) => sum + (item.qty * item.price), 0);
    });

    const totalPoint = computed(() => {
        return cartItems.value.reduce((sum, item) => sum + (item.qty * (item.point || 0)), 0);
    });

    const totalVat = computed(() => {
        return cartItems.value.reduce((sum, item) => sum + (item.qty * item.vat), 0);
    });

    const totalDiscount = computed(() => {
        return cartItems.value.reduce((sum, item) => sum + (item.qty * item.discount), 0);
    });

    const grandTotal = computed(() => {
        return subTotal.value + totalVat.value - totalDiscount.value;
    });

    const handleScanInput = () => {
        const term = productSearchTerm.value;
        const match = props.barcodes.find(bc =>
            bc.barcode.toLowerCase() === term.toLowerCase() &&
            bc.status_id === 1
        );
        if (match) {
            const product = props.products.find(p => p.id === match.product_id);
            if (product) {
                selectedProduct.value = product;
                field.product = product.id;
                selectedBarcode.value = match;
                addQty.value = Number(match.qty) || 1;
                productSearchTerm.value = '';
                showDropdown.value = false;
                addToCart();
            }
        }
    };

    const hideDropdown = () => {
        blurTimeout = setTimeout(() => { showDropdown.value = false; }, 200);
    };

    const selectProduct = (product) => {
        selectedProduct.value = product;
        field.product = product.id;
        productSearchTerm.value = '';
        showDropdown.value = false;
        selectedBarcode.value = null;
        addQty.value = 1;
        for (const key in selectedAttributes) delete selectedAttributes[key];
        customAttributes.value = [];
        if (product.attributes && typeof product.attributes === 'object') {
            for (const key of Object.keys(product.attributes)) {
                selectedAttributes[key] = '';
            }
        }
        setTimeout(() => searchInput.value?.focus(), 100);
    };

    const selectBarcodeFromSearch = (bc) => {
        const product = props.products.find(p => p.id === bc.product_id);
        if (!product) return;
        selectedProduct.value = product;
        field.product = product.id;
        selectedBarcode.value = bc;
        addQty.value = Number(bc.qty) || 1;
        productSearchTerm.value = '';
        showDropdown.value = false;
        addToCart();
    };

    const clearSelection = () => {
        selectedProduct.value = null;
        selectedBarcode.value = null;
        productSearchTerm.value = '';
        addQty.value = 1;
        field.product = '';
        for (const key in selectedAttributes) delete selectedAttributes[key];
        customAttributes.value = [];
        setTimeout(() => searchInput.value?.focus(), 100);
    };

    const resolveProductPrice = (barcode) => {
        return {
            price: Number(barcode?.price) || 0,
            vat: Number(barcode?.vat) || 0,
            discount: Number(barcode?.discount) || 0,
            point: Number(barcode?.point) || 0,
            unit_id: barcode?.unit_id || '',
        };
    };

    const normalizeAttrs = (attrs) => {
        if (!attrs) return {};
        if (Array.isArray(attrs)) {
            const obj = {};
            for (const a of attrs) {
                if (a.key && a.value) obj[a.key] = a.value;
            }
            return obj;
        }
        if (typeof attrs === 'object') return attrs;
        return {};
    };

    const addToCart = () => {
        if (!selectedProduct.value) return;
        const bc = selectedBarcode.value;
        const qty = Number(addQty.value) || 1;
        const resolved = resolveProductPrice(bc);

        let attrs = {};
        if (bc?.attributes) {
            attrs = normalizeAttrs(bc.attributes);
        } else {
            const payload = buildAttributesPayload();
            if (payload) attrs = JSON.parse(payload);
        }

        const existing = cartItems.value.find(item =>
            item.product_id === selectedProduct.value.id &&
            JSON.stringify(item.attributes || {}) === JSON.stringify(attrs)
        );
        if (existing) {
            existing.qty = Number(existing.qty) + qty;
        } else {
            cartItems.value.push({
                product_id: selectedProduct.value.id,
                product_name: selectedProduct.value.name,
                barcode_id: bc?.id || null,
                barcode: bc?.barcode || selectedProduct.value.barcode,
                qty: qty,
                price: resolved.price,
                vat: resolved.vat,
                discount: resolved.discount,
                point: resolved.point,
                unit_id: resolved.unit_id,
                product_price_id: bc?.product_price_id || '',
                attributes: Object.keys(attrs).length ? attrs : null,
            });
        }
        clearSelection();
    };

    const removeCartItem = (index) => {
        cartItems.value.splice(index, 1);
    };

    const handleClientPhoneInput = () => {
        const phone = clientPhone.value.trim();
        if (!phone) {
            field.client = '';
            clientFound.value = null;
            return;
        }
        const found = props.clients.find(c => c.contact === phone);
        if (found) {
            field.client = found.id;
            clientFound.value = found;
        } else {
            field.client = '';
            clientFound.value = null;
        }
    };

    const resolveProductPriceId = (productId, unitId, currentId) => {
        if (currentId) return currentId;
        const pp = props.productPrices.find(p =>
            Number(p.product_id) === Number(productId) && (!unitId || String(p.unit_id) === String(unitId))
        );
        return pp?.id || '';
    };

    const localErrors = reactive({});

    const clearLocalErrors = () => {
        for (const k in localErrors) delete localErrors[k];
    };

    let loadedSaleIds = [];

    const fetchInvoiceTotalPaid = async (invoiceNo) => {
        try {
            const res = await axios.get(`/api/v1/payments/total/${invoiceNo}`);
            return Number(res.data?.total_paid || 0);
        } catch {
            return 0;
        }
    };

    const saveAsPending = async (resetAfterSave = false) => {
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
        const partialCompletedStatus = props.statuses.find(s => s.name === 'partial completed');
        const completedStatus = props.statuses.find(s => s.name === 'completed');
        const targetStatusId = selectedStatus?.id || pendingStatus?.id || props.statuses[0]?.id;
        const isCompleted = selectedStatus?.name === 'completed';

        const savedSaleIds = [];

        let success = true;

        // Phase 1: Validate stock for ALL items before saving any
        try {
            const validationItems = cartItems.value.map(item => ({
                invoice_no: field.invoice_no,
                status: targetStatusId,
                branch: field.branch,
                product: item.product_id,
                qty: item.qty,
                attributes: item.attributes && Object.keys(item.attributes).length ? JSON.stringify(item.attributes) : null,
            }));
            await axios.post('/api/sales/validate-batch', { items: validationItems });
        } catch (e) {
            success = false;
            const errData = e.response?.data?.errors;
            if (errData && Array.isArray(errData)) {
                localErrors.general = errData.join(' | ');
            } else {
                const msg = e.response?.data?.message || e.message || 'Stock validation failed';
                localErrors.general = msg;
            }
            saving.value = false;
            return false;
        }

        // Phase 2: Check payment before saving
        const backendPaid = await fetchInvoiceTotalPaid(field.invoice_no);
        const sessionPaid = paidAmounts.value[field.invoice_no] || 0;
        const totalPaid = Math.max(backendPaid, sessionPaid);

        let saveStatusId;
        if (isCompleted) {
            if (totalPaid >= grandTotal.value) {
                saveStatusId = targetStatusId;
            } else {
                if (resetAfterSave) {
                    localErrors.general = 'Invoice is not fully paid. Complete the payment first.';
                    return false;
                }
                saveStatusId = pendingStatus?.id || targetStatusId;
            }
        } else if (selectedStatus?.name === 'partial completed' && totalPaid >= grandTotal.value) {
            saveStatusId = completedStatus?.id || targetStatusId;
        } else {
            saveStatusId = targetStatusId;
        }

        // Phase 3: All stock checks passed, now save each item
        for (const item of cartItems.value) {
            const productPriceId = resolveProductPriceId(item.product_id, item.unit_id, item.product_price_id);
            const unitId = item.unit_id || props.units[0]?.id || '';
            const payload = new FormData();
            payload.append('invoice_no', field.invoice_no);
            payload.append('status', saveStatusId);
            payload.append('branch', field.branch);
            payload.append('product', item.product_id);
            payload.append('product_price', productPriceId);
            payload.append('unit', unitId);
            payload.append('qty', item.qty);
            payload.append('price', item.price);
            payload.append('vat', item.vat);
            payload.append('discount', item.discount || 0);
            payload.append('point', item.point || 0);
            if (item.attributes && Object.keys(item.attributes).length) {
                payload.append('attributes', JSON.stringify(item.attributes));
            }
            payload.append('remarks', field.remarks || '');
            if (field.client) {
                payload.append('client', field.client);
            } else if (clientPhone.value.trim()) {
                payload.append('client_phone', clientPhone.value.trim());
            }

            try {
                if (item.sale_id) {
                    await axios.post(`/api/sales/update/${item.sale_id}`, payload);
                    savedSaleIds.push(item.sale_id);
                } else {
                    const res = await axios.post('/api/sales', payload);
                    const newId = res.data?.sale?.id || res.data?.data?.id;
                    if (newId) {
                        savedSaleIds.push(newId);
                        item.sale_id = newId;
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
            for (const oldId of loadedSaleIds) {
                if (!savedSaleIds.includes(oldId)) {
                    try { await axios.post(`/api/sales/delete/${oldId}`); } catch {}
                }
            }

            savedInvoiceNo.value = field.invoice_no;
            loadedSaleIds = savedSaleIds;

            const savedStatusObj = props.statuses.find(s => Number(s.id) === Number(saveStatusId));
            lastSavedStatus.value = savedStatusObj?.name || selectedStatus?.name || 'pending';

            saving.value = false;
            if (lastSavedStatus.value === 'completed') {
                triggerPrint();
            }
            if (resetAfterSave) {
                resetForm();
                emit('reset-form');
            }
            return lastSavedStatus.value;
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
        const saved = await saveAsPending();
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

    const onPaymentCompleted = async (paidAmount) => {
        showPaymentModal.value = false;
        const invoiceNo = savedInvoiceNo.value;
        if (invoiceNo && paidAmount) {
            const prev = paidAmounts.value[invoiceNo] || 0;
            paidAmounts.value[invoiceNo] = prev + paidAmount;
        }

        if (lastSavedStatus.value === 'partial completed' && invoiceNo) {
            const backendPaid = await fetchInvoiceTotalPaid(invoiceNo);
            const sessionPaid = paidAmounts.value[invoiceNo] || 0;
            const totalPaid = Math.max(backendPaid, sessionPaid);
            if (totalPaid >= grandTotal.value) {
                const completedStatus = props.statuses.find(s => s.name === 'completed');
                if (completedStatus) {
                    field.status = completedStatus.id;
                    await saveAsPending();
                }
            }
        }

        if (lastSavedStatus.value !== 'pending') {
            resetForm();
            emit('reset-form');
        }
    };

    const loadCartFromSales = (sales) => {
        isEditing.value = true;
        cartItems.value = sales.map(s => ({
            product_id: s.product_id,
            product_name: s.product?.name || '',
            barcode_id: null,
            barcode: s.product?.barcode || '',
            qty: Number(s.qty),
            price: s.price,
            vat: s.vat,
            discount: s.discount || 0,
            point: s.point || 0,
            unit_id: s.unit_id,
            product_price_id: s.product_price_id,
            sale_id: s.id,
            attributes: normalizeAttrs(s.attributes),
        }));
        loadedSaleIds = sales.map(s => s.id);
        if (sales.length) {
            const s = sales[0];
            field.invoice_no = s.invoice_no || '';
            field.branch = s.branch_id || s.branch?.id || '';
            field.status = s.status_id || '';
            if (s.client_id || s.client?.id) {
                field.client = s.client_id || s.client?.id;
                clientPhone.value = s.client?.contact || '';
                clientFound.value = s.client || null;
            }
        }
    };

    const getBranchName = (id) => {
        const found = props.branches.find(b => Number(b.id) === Number(id));
        return found?.name || '';
    };

    const triggerPrint = (paidAmt = 0) => {
        const totalPaid = Math.max(paidAmt, paidAmounts.value[field.invoice_no] || 0);
        const due = grandTotal.value - totalPaid;
        receiptData.invoiceNo = field.invoice_no;
        receiptData.date = new Date().toLocaleString();
        receiptData.branchName = getBranchName(field.branch);
        receiptData.clientName = clientFound.value?.name || '';
        receiptData.items = cartItems.value.map(item => ({
            product_name: item.product_name,
            qty: item.qty,
            price: item.price,
            vat: item.vat || 0,
            discount: item.discount || 0,
            attributes: item.attributes || null,
        }));
        receiptData.subtotal = subTotal.value;
        receiptData.totalVat = totalVat.value;
        receiptData.totalDiscount = totalDiscount.value;
        receiptData.grandTotal = grandTotal.value;
        receiptData.paidAmount = totalPaid > 0 ? totalPaid : 0;
        receiptData.dueAmount = due > 0 ? due : 0;
        showReceipt.value = true;
    };

    const resetForm = () => {
        clearLocalErrors();
        cartItems.value = [];
        loadedSaleIds = [];
        selectedProduct.value = null;
        selectedBarcode.value = null;
        productSearchTerm.value = '';
        addQty.value = 1;
        clientPhone.value = '';
        clientFound.value = null;
        field.status = '';
        field.branch = '';
        field.client = '';
        field.remarks = '';
        savedInvoiceNo.value = null;
        isEditing.value = false;
        for (const key in selectedAttributes) delete selectedAttributes[key];
        customAttributes.value = [];
        regenerateInvoiceNo();
    };

    defineExpose({
        resetForm,
        field,
        setInvoiceNo: (no) => { field.invoice_no = no; },
        loadCartFromSales,
    });

    onMounted(() => {
        if (!props.editSale) {
            field.invoice_no = generateInvoiceNo();
        }
    });

    watch(() => field.product_price, (newVal) => {
        if (newVal) {
            const pp = props.productPrices.find(p => p.id === Number(newVal));
            if (pp) {
                field.unit = pp.unit_id || '';
                field.price = pp.price || '';
                field.vat = pp.vat || '';
                field.point = pp.point || '';
            }
        }
    });

    watch(() => props.editSale, (val) => {
        if (val) {
            isEditing.value = true;
            field.invoice_no = val.invoice_no || '';
            field.status = val.status_id || '';
            field.branch = val.branch_id || '';
            field.client = val.client_id || '';
            if (val.client) {
                clientPhone.value = val.client.contact || '';
                clientFound.value = val.client;
            }
        } else {
            isEditing.value = false;
        }
    }, { immediate: true });

    const onReset = () => {
        emit("reset-form");
    };
</script>

<style lang="scss" scoped>
    .selected-product {
        background-color: #f8f9fa;
    }
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

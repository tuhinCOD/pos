<template>
    <form id="purchase-return-form" @submit.prevent="updateForm">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="m-0">{{ isEditing ? 'Edit Purchase Return' : 'Add Purchase Return' }}</h4>
            <router-link to="/purchase-returns" class="btn btn-primary btn-sm">
                <i class="bi bi-eye"></i> View Purchase Returns
            </router-link>
        </div>
        <div class="my-3">
            <div class="row g-2">
                <div class="col-3 position-relative">
                    <label class="form-label">Purchase Invoice <span class="text-danger">*</span></label>
                    <input
                        v-if="!selectedInvoiceNo"
                        class="form-control"
                        type="text"
                        v-model="purchaseSearchTerm"
                        placeholder="Type invoice no or product name..."
                        @input="showPurchaseDropdown = true"
                        @focus="showPurchaseDropdown = true"
                        @blur="hidePurchaseDropdown"
                    />
                    <div v-else class="selected-purchase d-flex align-items-center gap-2 border rounded p-2">
                        <span class="fw-bold">{{ selectedInvoiceNo }}</span>
                        <span v-if="selectedProduct" class="text-muted small ms-2">({{ selectedProduct.name }})</span>
                        <button type="button" class="btn btn-sm btn-outline-danger ms-auto" @click="clearPurchase">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                    <ul v-if="showPurchaseDropdown && filteredPurchases.length" class="purchase-dropdown list-unstyled">
                        <li
                            v-for="inv in filteredPurchases"
                            :key="inv.invoice_no"
                            class="purchase-dropdown-item px-3 py-2"
                            @mousedown.prevent="selectPurchase(inv)"
                        >
                            <span class="fw-bold">{{ inv.invoice_no }}</span>
                        </li>
                    </ul>
                    <div v-if="showPurchaseDropdown && purchaseSearchTerm && !filteredPurchases.length" class="purchase-dropdown list-unstyled">
                        <div class="px-3 py-2 text-muted">No purchases found.</div>
                    </div>
                    <div id="purchaseHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.purchase?.[0] }}</div>
                </div>
                <div class="col-3 position-relative">
                    <label class="form-label">Product <span class="text-danger">*</span></label>
                    <input
                        v-if="!selectedProduct"
                        class="form-control"
                        type="text"
                        v-model="productSearchTerm"
                        placeholder="Type product name or barcode..."
                        @input="showProductDropdown = true"
                        @focus="showProductDropdown = true"
                        @blur="hideProductDropdown"
                    />
                    <div v-else class="selected-product d-flex align-items-center gap-2 border rounded p-2">
                        <span class="fw-bold">{{ selectedProduct.name }}</span>
                        <span class="text-muted small">({{ selectedProduct.barcode }})</span>
                        <button type="button" class="btn btn-sm btn-outline-danger ms-auto" @click="clearProduct">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                    <ul v-if="showProductDropdown && filteredProducts.length" class="product-dropdown list-unstyled">
                        <li
                            v-for="p in filteredProducts"
                            :key="p.id"
                            class="product-dropdown-item px-3 py-2"
                            @mousedown.prevent="selectProduct(p)"
                        >
                            <span class="fw-bold">{{ p.name }}</span>
                            <span class="text-muted small ms-2">({{ p.barcode }})</span>
                        </li>
                    </ul>
                    <div v-if="showProductDropdown && productSearchTerm && !filteredProducts.length" class="product-dropdown list-unstyled">
                        <div class="px-3 py-2 text-muted">No products found.</div>
                    </div>
                    <div id="productHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.product?.[0] }}</div>
                </div>
                <div class="col-3">
                    <label for="branch" class="form-label">Branch <span class="text-danger">*</span></label>
                    <select class="form-select" v-model="field.branch" id="branch" name="branch">
                        <option value="">Select Branch</option>
                        <option v-for="b in availableBranches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                    <div id="branchHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.branch?.[0] }}</div>
                </div>
                <div class="col-3">
                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                    <select class="form-select" v-model="field.status" id="status" name="status">
                        <option value="">Select Status</option>
                        <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <div id="statusHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.status?.[0] }}</div>
                </div>
                <div class="col-3" style="margin-top: 14px;">
                    <label for="unit" class="form-label">Unit <span class="text-danger">*</span></label>
                    <select class="form-select" v-model="field.unit" id="unit" name="unit">
                        <option value="">Select Unit</option>
                        <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                    <div id="unitHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.unit?.[0] }}</div>
                </div>
                <div class="col-3">
                    <Input label="Qty" id="qty" type="number" step="0.001" errid="qtyHelp" name="qty" v-model="field.qty" placeholder="0" :err="errors.qty?.[0]" must/>
                </div>
                <div class="col-3" style="margin-top: 14px;">
                    <label for="prod_unit" class="form-label">Product Unit</label>
                    <select class="form-select" v-model="field.product_unit_id" id="prod_unit" name="prod_unit">
                        <option value="">Select Product Unit</option>
                        <option v-for="u in availableUnits" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                    <div id="prodUnitHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.product_unit_id?.[0] }}</div>
                </div>
                <div class="col-3">
                    <Input label="Product Qty" id="prod_qty" type="number" step="0.001" errid="prodQtyHelp" name="prod_qty" v-model="field.product_unit_qty" placeholder="0" :err="errors.product_unit_qty?.[0]"/>
                </div>
                <div v-if="purchaseAttrs && Object.keys(purchaseAttrs).length" class="col-12">
                    <label class="form-label fw-semibold">Attributes</label>
                    <div class="d-flex flex-wrap gap-3">
                        <div v-for="(values, key) in purchaseAttrs" :key="key">
                            <label class="fw-semibold text-capitalize small me-2">{{ key }}:</label>
                            <div class="d-flex flex-wrap gap-1">
                                <label v-for="val in (Array.isArray(values) ? values : [values])" :key="val"
                                    class="btn btn-outline-secondary btn-sm"
                                    :class="{ 'active border-primary text-light': selectedAttributes[key] === val }">
                                    <input type="radio" :name="'ret_attr_' + key" :value="val"
                                        v-model="selectedAttributes[key]" class="d-none">
                                    {{ val }}
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <label for="remarks" class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control" id="remarks" placeholder="Write remarks here..." v-model="field.remarks"></textarea>
                    <div id="remarksHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.remarks?.[0] }}</div>
                </div>
            </div>
        </div>
        <div class="row g-2 my-3">
            <div class="col-6">
                <Button :label="editReturn ? 'Update' : 'Submit'" id="submitBtn" type="submit" icon="bi bi-save-fill"/>
            </div>
            <div class="col-6">
                <Button label="Reset" type="button" id="resetBtn" color="secondary" icon="bi bi-x-square-fill" @click="onReset"/>
            </div>
        </div>
    </form>
</template>

<script setup>
    import Input from '../../components/Input.vue';
    import Button from '../../components/Button.vue';
    import { computed, onMounted, reactive, ref, watch } from 'vue';

    const props = defineProps({
        statuses: { type: Array, default: () => [] },
        products: { type: Array, default: () => [] },
        purchases: { type: Array, default: () => [] },
        units: { type: Array, default: () => [] },
        branches: { type: Array, default: () => [] },
        errors: { type: Object, default: () => ({}) },
        editReturn: { type: Object, default: null },
    });

    const emit = defineEmits([
        "reset-form",
        "update-return",
    ]);

    const field = reactive({
        purchase: '',
        product: '',
        status: '',
        qty: '',
        unit: '',
        product_unit_qty: '',
        product_unit_id: '',
        branch: '',
        remarks: '',
    });

    const isEditing = ref(false);

    const selectedInvoiceNo = ref(null);
    const selectedInvoicePurchases = ref([]);
    const purchaseSearchTerm = ref('');
    const showPurchaseDropdown = ref(false);
    let purchaseBlurTimeout = null;

    const selectedProduct = ref(null);
    const productSearchTerm = ref('');
    const showProductDropdown = ref(false);
    let productBlurTimeout = null;

    const purchaseUnits = computed(() => {
        if (!selectedInvoiceNo.value || !selectedProduct.value) return [];
        const seen = new Set();
        return selectedInvoicePurchases.value
            .filter(p => p.product_id === selectedProduct.value.id)
            .reduce((acc, p) => {
                const uid = p.product_unit_id || p.unit_id;
                if (uid && !seen.has(uid)) {
                    seen.add(uid);
                    const u = props.units.find(u => u.id === uid);
                    if (u) acc.push(u);
                }
                return acc;
            }, []);
    });

    const availableUnits = computed(() => {
        if (purchaseUnits.value.length) return purchaseUnits.value;
        return props.units;
    });

    const availableBranches = computed(() => {
        if (selectedInvoiceNo.value && selectedInvoicePurchases.value.length) {
            const branchId = selectedInvoicePurchases.value[0].branch_id;
            const b = props.branches.find(b => b.id === branchId);
            return b ? [b] : props.branches;
        }
        return props.branches;
    });

    const selectedAttributes = reactive({});
    const purchaseAttrs = computed(() => {
        if (!selectedProduct.value || !selectedInvoiceNo.value) return null;
        const purchases = selectedInvoicePurchases.value.filter(
            p => p.product_id === selectedProduct.value.id
        );
        if (!purchases.length) return null;
        const merged = {};
        purchases.forEach(p => {
            if (!p.attributes) return;
            const attrs = typeof p.attributes === 'string'
                ? JSON.parse(p.attributes)
                : p.attributes;
            for (const [key, val] of Object.entries(attrs)) {
                if (!merged[key]) merged[key] = [];
                if (!merged[key].includes(val)) {
                    merged[key].push(val);
                }
            }
        });
        return Object.keys(merged).length ? merged : null;
    });

    watch(selectedAttributes, () => {
        if (!selectedProduct.value || !selectedInvoiceNo.value) return;
        const attrKeys = Object.keys(selectedAttributes);
        const purchase = selectedInvoicePurchases.value.find(p => {
            if (p.product_id !== selectedProduct.value.id) return false;
            if (!p.attributes) return attrKeys.length === 0;
            const attrs = typeof p.attributes === 'string'
                ? JSON.parse(p.attributes)
                : p.attributes;
            return attrKeys.length > 0 && attrKeys.every(k => attrs[k] === selectedAttributes[k]);
        });
        if (purchase) {
            field.purchase = purchase.id;
            if (purchase.product_unit_id || purchase.unit_id) {
                if (!isEditing.value) {
                    field.unit = purchase.product_unit_id || purchase.unit_id;
                    field.product_unit_id = purchase.product_unit_id || purchase.unit_id;
                }
            }
        }
    }, { deep: true });

    const filteredPurchases = computed(() => {
        const term = purchaseSearchTerm.value.toLowerCase();
        const invoiceMap = new Map();
        props.purchases.forEach(p => {
            if (field.product && p.product_id !== Number(field.product)) return;
            if (term && !p.invoice_no.toLowerCase().includes(term) &&
                !(p.product?.name || '').toLowerCase().includes(term)) return;
            if (!invoiceMap.has(p.invoice_no)) {
                invoiceMap.set(p.invoice_no, { invoice_no: p.invoice_no, products: [] });
            }
            const group = invoiceMap.get(p.invoice_no);
            if (p.product && !group.products.some(pr => pr.id === p.product.id)) {
                group.products.push(p.product);
            }
        });
        return Array.from(invoiceMap.values()).slice(0, 10);
    });

    const filteredProducts = computed(() => {
        const term = productSearchTerm.value.toLowerCase();
        let list = props.products;
        if (selectedInvoiceNo.value) {
            const seen = new Set();
            list = selectedInvoicePurchases.value
                .map(p => p.product)
                .filter(Boolean)
                .filter(p => {
                    if (seen.has(p.id)) return false;
                    seen.add(p.id);
                    return true;
                });
        }
        return list.filter(p =>
            p.name.toLowerCase().includes(term) ||
            p.barcode.toLowerCase().includes(term)
        ).slice(0, 10);
    });

    const hidePurchaseDropdown = () => {
        purchaseBlurTimeout = setTimeout(() => {
            showPurchaseDropdown.value = false;
        }, 200);
    };

    const selectPurchase = (invoiceGroup) => {
        selectedInvoiceNo.value = invoiceGroup.invoice_no;
        selectedInvoicePurchases.value = props.purchases.filter(
            p => p.invoice_no === invoiceGroup.invoice_no
        );
        purchaseSearchTerm.value = '';
        showPurchaseDropdown.value = false;
        const first = selectedInvoicePurchases.value[0];
        if (first?.branch_id) field.branch = first.branch_id;
        const matchingProduct = field.product
            ? selectedInvoicePurchases.value.find(p => p.product_id === Number(field.product))
            : null;
        if (matchingProduct) {
            field.purchase = matchingProduct.id;
            if (matchingProduct.product_unit_id || matchingProduct.unit_id) {
                if (!isEditing.value) {
                    field.unit = matchingProduct.product_unit_id || matchingProduct.unit_id;
                    field.product_unit_id = matchingProduct.product_unit_id || matchingProduct.unit_id;
                }
            }
        } else if (invoiceGroup.products.length === 1) {
            const purchase = first;
            selectProduct(purchase.product);
            field.purchase = purchase.id;
            if (purchase.product_unit_id || purchase.unit_id) {
                if (!isEditing.value) {
                    field.unit = purchase.product_unit_id || purchase.unit_id;
                    field.product_unit_id = purchase.product_unit_id || purchase.unit_id;
                }    
            }
        } else {
            clearProduct();
            field.purchase = '';
            field.unit = '';
            field.product_unit_id = '';
        }
    };

    const clearPurchase = () => {
        selectedInvoiceNo.value = null;
        selectedInvoicePurchases.value = [];
        field.purchase = '';
        purchaseSearchTerm.value = '';
        field.unit = '';
        field.product_unit_id = '';
        field.branch = '';
    };

    const hideProductDropdown = () => {
        productBlurTimeout = setTimeout(() => {
            showProductDropdown.value = false;
        }, 200);
    };

    const selectProduct = (product) => {
        selectedProduct.value = product;
        field.product = product.id;
        productSearchTerm.value = '';
        showProductDropdown.value = false;
        for (const key in selectedAttributes) {
            delete selectedAttributes[key];
        }
        if (selectedInvoiceNo.value) {
            const purchases = selectedInvoicePurchases.value.filter(
                p => p.product_id === product.id
            );
            if (purchases.length) {
                if (purchases.length === 1 && !purchases[0].attributes) {
                    field.purchase = purchases[0].id;
                    if (purchases[0].product_unit_id || purchases[0].unit_id) {
                        field.unit = purchases[0].product_unit_id || purchases[0].unit_id;
                        field.product_unit_id = purchases[0].product_unit_id || purchases[0].unit_id;
                    }
                }
                const first = purchases.find(p => p.attributes);
                if (first?.attributes) {
                    const attrs = typeof first.attributes === 'string'
                        ? JSON.parse(first.attributes)
                        : first.attributes;
                    for (const key of Object.keys(attrs)) {
                        const values = attrs[key];
                        selectedAttributes[key] = Array.isArray(values) ? values[0] : values;
                    }
                }
            }
        }
    };

    const clearProduct = () => {
        selectedProduct.value = null;
        field.product = '';
        field.qty = '';
        field.unit = '';
        field.product_unit_qty = '';
        field.product_unit_id = '';
        productSearchTerm.value = '';
        for (const key in selectedAttributes) {
            delete selectedAttributes[key];
        }
    };

    const updateForm = () => {
        const formData = new FormData();
        for (const key in field) {
            formData.append(key, field[key]);
        }
        if (Object.keys(selectedAttributes).length) {
            formData.append('attributes', JSON.stringify(selectedAttributes));
        }
        emit('update-return', formData);
    };

    watch(() => props.editReturn, (val) => {
        if (val) {
            isEditing.value = true;
            field.status = val.status_id || '';
            field.branch = val.branch_id || '';
            field.remarks = val.remarks || '';

            if (val.product) {
                selectedProduct.value = val.product;
                field.product = val.product_id || val.product.id;
                productSearchTerm.value = '';
            } else if (val.product_id && !selectedProduct.value) {
                const prod = props.products.find(p => p.id === val.product_id);
                if (prod) {
                    selectedProduct.value = prod;
                    field.product = prod.id;
                    productSearchTerm.value = '';
                }
            }

            const purchaseObj = val.purchase || (val.purchase_id
                ? props.purchases.find(p => p.id === val.purchase_id)
                : null);
            if (purchaseObj) {
                const sameInvoices = props.purchases.filter(p => p.invoice_no === purchaseObj.invoice_no);
                const seen = new Set();
                const products = [];
                sameInvoices.forEach(p => {
                    if (p.product && !seen.has(p.product.id)) {
                        seen.add(p.product.id);
                        products.push(p.product);
                    }
                });
                selectPurchase({ invoice_no: purchaseObj.invoice_no, products });
            }

            field.qty = val.qty || '';
            field.product_unit_qty = val.product_unit_qty || '';
            field.unit = val.unit_id || '';
            field.product_unit_id = val.product_unit_id || '';

            if (val.attributes) {
                const attrs = typeof val.attributes === 'string'
                    ? JSON.parse(val.attributes)
                    : val.attributes;
                for (const key in selectedAttributes) {
                    delete selectedAttributes[key];
                }
                for (const key of Object.keys(attrs)) {
                    selectedAttributes[key] = attrs[key];
                }
            }
        } else {
            isEditing.value = false;
        }
    }, { immediate: true });

    const resetForm = () => {
        field.purchase = '';
        field.product = '';
        field.status = '';
        field.qty = '';
        field.unit = '';
        field.product_unit_qty = '';
        field.product_unit_id = '';
        field.branch = '';
        field.remarks = '';
        isEditing.value = false;
        selectedInvoiceNo.value = null;
        selectedInvoicePurchases.value = [];
        purchaseSearchTerm.value = '';
        selectedProduct.value = null;
        productSearchTerm.value = '';
        for (const key in selectedAttributes) {
            delete selectedAttributes[key];
        }
    };

    const onReset = () => {
        resetForm();
        emit("reset-form");
    };

    defineExpose({
        resetForm,
        field,
    });
</script>

<style lang="scss" scoped>
    .selected-product, .selected-purchase {
        background-color: #f8f9fa;
    }
    .product-dropdown, .purchase-dropdown {
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
    .product-dropdown-item, .purchase-dropdown-item {
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
    }
    .product-dropdown-item:hover, .purchase-dropdown-item:hover {
        background-color: #e9ecef;
    }
</style>

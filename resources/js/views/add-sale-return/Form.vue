<template>
    <form id="client-return-form" @submit.prevent="updateForm">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="m-0">{{ isEditing ? 'Edit Client Return' : 'Add Client Return' }}</h4>
            <router-link to="/client-returns" class="btn btn-primary btn-sm">
                <i class="bi bi-eye"></i> View Client Returns
            </router-link>
        </div>
        <div class="my-3">
            <div class="row g-2">
                <div class="col-3 position-relative">
                    <label class="form-label">Sale Invoice <span class="text-danger">*</span></label>
                    <input
                        v-if="!selectedInvoiceNo"
                        class="form-control"
                        type="text"
                        v-model="saleSearchTerm"
                        placeholder="Type invoice no or product name..."
                        @input="showSaleDropdown = true"
                        @focus="showSaleDropdown = true"
                        @blur="hideSaleDropdown"
                    />
                    <div v-else class="selected-sale d-flex align-items-center gap-2 border rounded p-2">
                        <span class="fw-bold">{{ selectedInvoiceNo }}</span>
                        <span v-if="selectedProduct" class="text-muted small ms-2">({{ selectedProduct.name }})</span>
                        <button type="button" class="btn btn-sm btn-outline-danger ms-auto" @click="clearSale">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                    <ul v-if="showSaleDropdown && filteredSales.length" class="sale-dropdown list-unstyled">
                        <li
                            v-for="inv in filteredSales"
                            :key="inv.invoice_no"
                            class="sale-dropdown-item px-3 py-2"
                            @mousedown.prevent="selectSale(inv)"
                        >
                            <span class="fw-bold">{{ inv.invoice_no }}</span>
                        </li>
                    </ul>
                    <div v-if="showSaleDropdown && saleSearchTerm && !filteredSales.length" class="sale-dropdown list-unstyled">
                        <div class="px-3 py-2 text-muted">No sales found.</div>
                    </div>
                    <div id="saleHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.sale?.[0] }}</div>
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
                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                    <select class="form-select" v-model="field.status" id="status" name="status">
                        <option value="">Select Status</option>
                        <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <div id="statusHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.status?.[0] }}</div>
                </div>
                <div class="col-3">
                    <label for="branch" class="form-label">Branch <span class="text-danger">*</span></label>
                    <select class="form-select" v-model="field.branch" id="branch" name="branch">
                        <option value="">Select Branch</option>
                        <option v-for="b in availableBranches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                    <div id="branchHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.branch?.[0] }}</div>
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
                    <label for="product_unit_id" class="form-label">Product Unit <span class="text-danger">*</span></label>
                    <select class="form-select" v-model="field.product_unit_id" id="product_unit_id" name="product_unit_id">
                        <option value="">Select Prod Unit</option>
                        <option v-for="u in availableProductUnits" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                    <div class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.product_unit_id?.[0] }}</div>
                </div>
                <div class="col-3" style="margin-top: 14px;">
                    <label for="product_unit" class="form-label">Product Qty <span class="text-danger">*</span></label>
                    <input type="number" step="0.001" class="form-control" v-model="field.product_unit_qty" id="product_unit" placeholder="0" />
                    <div class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.product_unit_qty?.[0] }}</div>
                </div>
                <div v-if="saleAttrs && Object.keys(saleAttrs).length" class="col-12">
                    <label class="form-label fw-semibold">Attributes</label>
                    <div class="d-flex flex-wrap gap-3">
                        <div v-for="(values, key) in saleAttrs" :key="key">
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
        branches: { type: Array, default: () => [] },
        units: { type: Array, default: () => [] },
        sales: { type: Array, default: () => [] },
        errors: { type: Object, default: () => ({}) },
        editReturn: { type: Object, default: null },
    });

    const emit = defineEmits([
        "reset-form",
        "update-return",
    ]);

    const field = reactive({
        sale: '',
        product: '',
        status: '',
        unit: '',
        product_unit_id: '',
        branch: '',
        qty: '',
        product_unit_qty: '',
        remarks: '',
    });

    const isEditing = ref(false);

    const selectedInvoiceNo = ref(null);
    const selectedInvoiceSales = ref([]);
    const saleSearchTerm = ref('');
    const showSaleDropdown = ref(false);
    let saleBlurTimeout = null;

    const selectedProduct = ref(null);
    const productSearchTerm = ref('');
    const showProductDropdown = ref(false);
    let productBlurTimeout = null;

    const selectedAttributes = reactive({});
    const saleAttrs = computed(() => {
        if (!selectedProduct.value || !selectedInvoiceNo.value) return null;
        const sales = selectedInvoiceSales.value.filter(
            s => s.product_id === selectedProduct.value.id
        );
        if (!sales.length) return null;
        const merged = {};
        sales.forEach(s => {
            if (!s.attributes) return;
            const attrs = typeof s.attributes === 'string'
                ? JSON.parse(s.attributes)
                : s.attributes;
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
        const sale = selectedInvoiceSales.value.find(s => {
            if (s.product_id !== selectedProduct.value.id) return false;
            if (!s.attributes) return attrKeys.length === 0;
            const attrs = typeof s.attributes === 'string'
                ? JSON.parse(s.attributes)
                : s.attributes;
            return attrKeys.length > 0 && attrKeys.every(k => attrs[k] === selectedAttributes[k]);
        });
        if (sale) {
            field.sale = sale.id;
            if (sale.unit_id) {
                if (!isEditing.value) {
                    field.unit = sale.unit_id;
                    field.product_unit_id = sale.unit_id;
                }    
            }
            if (sale.branch_id) field.branch = sale.branch_id;
        }
    }, { deep: true });

    const filteredSales = computed(() => {
        const term = saleSearchTerm.value.toLowerCase();
        const invoiceMap = new Map();
        props.sales.forEach(s => {
            if (field.product && s.product_id !== Number(field.product)) return;
            if (term && !s.invoice_no.toLowerCase().includes(term) &&
                !(s.product?.name || '').toLowerCase().includes(term)) return;
            if (!invoiceMap.has(s.invoice_no)) {
                invoiceMap.set(s.invoice_no, { invoice_no: s.invoice_no, products: [] });
            }
            const group = invoiceMap.get(s.invoice_no);
            if (s.product && !group.products.some(pr => pr.id === s.product.id)) {
                group.products.push(s.product);
            }
        });
        return Array.from(invoiceMap.values()).slice(0, 10);
    });

    const availableProductUnits = computed(() => {
        if (selectedInvoiceNo.value) {
            const saleUnitIds = new Set(selectedInvoiceSales.value.map(s => s.unit_id).filter(Boolean));
            if (saleUnitIds.size) {
                return props.units.filter(u => saleUnitIds.has(u.id));
            }
        }
        return props.units;
    });

    const availableBranches = computed(() => {
        if (selectedInvoiceNo.value) {
            const saleBranchIds = new Set(selectedInvoiceSales.value.map(s => s.branch_id).filter(Boolean));
            if (saleBranchIds.size) {
                return props.branches.filter(b => saleBranchIds.has(b.id));
            }
        }
        return props.branches;
    });

    const filteredProducts = computed(() => {
        const term = productSearchTerm.value.toLowerCase();
        let list = props.products;
        if (selectedInvoiceNo.value) {
            const seen = new Set();
            list = selectedInvoiceSales.value
                .map(s => s.product)
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

    const hideSaleDropdown = () => {
        saleBlurTimeout = setTimeout(() => {
            showSaleDropdown.value = false;
        }, 200);
    };

    const selectSale = (invoiceGroup) => {
        selectedInvoiceNo.value = invoiceGroup.invoice_no;
        selectedInvoiceSales.value = props.sales.filter(
            s => s.invoice_no === invoiceGroup.invoice_no
        );
        saleSearchTerm.value = '';
        showSaleDropdown.value = false;
        const matchingProduct = field.product
            ? selectedInvoiceSales.value.find(s => s.product_id === Number(field.product))
            : null;
        if (matchingProduct) {
            if (!isEditing.value) {
                field.sale = matchingProduct.id;
                field.unit = matchingProduct.unit_id || '';
                field.product_unit_id = matchingProduct.unit_id || '';
                field.branch = matchingProduct.branch_id || '';
            }    
        } else if (invoiceGroup.products.length === 1) {
            if (!isEditing.value) {
                const sale = selectedInvoiceSales.value[0];
                selectProduct(sale.product);
                field.sale = sale.id;
                field.unit = sale.unit_id || '';
                field.product_unit_id = sale.unit_id || '';
                field.branch = sale.branch_id || '';
            }    
        } else {
            clearProduct();
            field.sale = '';
        }
    };

    const clearSale = () => {
        selectedInvoiceNo.value = null;
        selectedInvoiceSales.value = [];
        field.sale = '';
        field.branch = '';
        saleSearchTerm.value = '';
        clearProduct();
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
            const sales = selectedInvoiceSales.value.filter(
                s => s.product_id === product.id
            );
            if (sales.length) {
                if (sales.length === 1 && !sales[0].attributes) {
                    field.sale = sales[0].id;
                    field.unit = sales[0].unit_id || '';
                    field.branch = sales[0].branch_id || '';
                }
                const first = sales.find(s => s.attributes);
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
        field.product_unit_id = '';
        productSearchTerm.value = '';
        for (const key in selectedAttributes) {
            delete selectedAttributes[key];
        }
    };

    const updateForm = () => {
        const formData = new FormData();
        for (const key in field) {
            if (field[key] !== '' && field[key] !== null) {
                formData.append(key, field[key]);
            }
        }
        if (Object.keys(selectedAttributes).length) {
            formData.append('attributes', JSON.stringify(selectedAttributes));
        }
        emit('update-return', formData);
    };

    onMounted(() => {
    });

    watch(() => props.editReturn, (val) => {
        if (val) {
            isEditing.value = true;
            field.status = val.status_id || '';
            field.branch = val.branch_id || '';
            field.remarks = val.remarks || '';
            const saleObj = val.sale || (val.sale_id
                ? props.sales.find(s => s.id === val.sale_id)
                : null);
            if (saleObj) {
                const sameInvoices = props.sales.filter(s => s.invoice_no === saleObj.invoice_no);
                const seen = new Set();
                const products = [];
                sameInvoices.forEach(s => {
                    if (s.product && !seen.has(s.product.id)) {
                        seen.add(s.product.id);
                        products.push(s.product);
                    }
                });
                selectSale({ invoice_no: saleObj.invoice_no, products });
            }
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
            field.unit = val.unit_id || '';
            field.product_unit_id = val.product_unit_id || '';
            field.qty = val.qty || '';
            field.product_unit_qty = val.product_unit_qty || '';

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
        field.sale = '';
        field.product = '';
        field.status = '';
        field.unit = '';
        field.product_unit_id = '';
        field.branch = '';
        field.qty = '';
        field.product_unit_qty = '';
        field.remarks = '';
        isEditing.value = false;
        selectedInvoiceNo.value = null;
        selectedInvoiceSales.value = [];
        saleSearchTerm.value = '';
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
    .selected-product, .selected-sale {
        background-color: #f8f9fa;
    }
    .product-dropdown, .sale-dropdown {
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
    .product-dropdown-item, .sale-dropdown-item {
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
    }
    .product-dropdown-item:hover, .sale-dropdown-item:hover {
        background-color: #e9ecef;
    }
</style>

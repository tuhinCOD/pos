<template>
    <div class="container-fluid">
        <div class="my-3">
            <div class="card card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="m-0">Generate Single Barcode</h4>
                    <router-link to="/barcodes" class="btn btn-primary btn-sm">
                        <i class="bi bi-eye"></i> View Barcodes
                    </router-link>
                </div>

                <div v-if="message" class="alert" :class="messageType" role="alert">
                    {{ message }}
                </div>

                <form @submit.prevent="handleGenerate">
                    <div class="row g-3">
                        <div class="col-4 position-relative">
                            <label class="form-label">Product <span class="text-danger">*</span></label>
                            <input
                                v-if="!selectedProduct"
                                class="form-control"
                                :class="fieldErrors.product_id ? 'is-invalid' : ''"
                                type="text"
                                v-model="productSearchTerm"
                                placeholder="Type product name or barcode..."
                                @input="showProductDropdown = true; fieldErrors.product_id = ''"
                                @focus="showProductDropdown = true"
                                @blur="hideProductDropdown"
                            />
                            <div v-else class="selected-item d-flex align-items-center gap-2 border rounded p-2">
                                <span class="fw-bold">{{ selectedProduct.name }}</span>
                                <span class="text-muted small">({{ selectedProduct.barcode }})</span>
                                <button type="button" class="btn btn-sm btn-outline-danger ms-auto" @click="clearProduct">
                                    <i class="bi bi-x"></i>
                                </button>
                            </div>
                            <ul v-if="showProductDropdown && filteredProducts.length" class="dropdown-menu-show list-unstyled">
                                <li
                                    v-for="p in filteredProducts"
                                    :key="p.id"
                                    class="dropdown-item-show px-3 py-2"
                                    @mousedown.prevent="selectProduct(p)"
                                >
                                    <span class="fw-bold">{{ p.name }}</span>
                                    <span class="text-muted small ms-2">({{ p.barcode }})</span>
                                </li>
                            </ul>
                            <div v-if="fieldErrors.product_id" class="invalid-feedback d-block">{{ fieldErrors.product_id }}</div>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Branch <span class="text-danger">*</span></label>
                            <select class="form-select" :class="fieldErrors.branch_id ? 'is-invalid' : ''" v-model="form.branch_id" @change="fieldErrors.branch_id = ''">
                                <option value="">Select Branch</option>
                                <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                            <div v-if="fieldErrors.branch_id" class="invalid-feedback d-block">{{ fieldErrors.branch_id }}</div>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Unit <span class="text-danger">*</span></label>
                            <select class="form-select" :class="fieldErrors.unit_id ? 'is-invalid' : ''" v-model="form.unit_id" @change="fieldErrors.unit_id = ''">
                                <option value="">Select Unit</option>
                                <option v-if="selectedProduct?.unit" :value="selectedProduct.unit.id">{{ selectedProduct.unit.name }}</option>
                            </select>
                            <div v-if="fieldErrors.unit_id" class="invalid-feedback d-block">{{ fieldErrors.unit_id }}</div>
                        </div>
                        <div class="col-3">
                            <label class="form-label">Qty <span class="text-danger">*</span></label>
                            <input type="number" step="0.001" class="form-control" :class="fieldErrors.qty ? 'is-invalid' : ''" v-model.number="form.qty" @input="fieldErrors.qty = ''" />
                            <div v-if="fieldErrors.qty" class="invalid-feedback d-block">{{ fieldErrors.qty }}</div>
                        </div>
                        <div class="col-3">
                            <label class="form-label">Price <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" :class="fieldErrors.price ? 'is-invalid' : ''" v-model.number="form.price" @input="fieldErrors.price = ''" />
                            <div v-if="fieldErrors.price" class="invalid-feedback d-block">{{ fieldErrors.price }}</div>
                        </div>
                        <div class="col-3">
                            <label class="form-label">VAT</label>
                            <input type="number" step="0.01" class="form-control" :class="fieldErrors.vat ? 'is-invalid' : ''" v-model.number="form.vat" @input="fieldErrors.vat = ''" />
                            <div v-if="fieldErrors.vat" class="invalid-feedback d-block">{{ fieldErrors.vat }}</div>
                        </div>
                        <div class="col-3">
                            <label class="form-label">Discount</label>
                            <input type="number" step="0.01" class="form-control" :class="fieldErrors.discount ? 'is-invalid' : ''" v-model.number="form.discount" @input="fieldErrors.discount = ''" />
                            <div v-if="fieldErrors.discount" class="invalid-feedback d-block">{{ fieldErrors.discount }}</div>
                        </div>
                        <div class="col-3">
                            <label class="form-label">Point</label>
                            <input type="number" step="0.01" class="form-control" :class="fieldErrors.point ? 'is-invalid' : ''" v-model.number="form.point" @input="fieldErrors.point = ''" />
                            <div v-if="fieldErrors.point" class="invalid-feedback d-block">{{ fieldErrors.point }}</div>
                        </div>
                    </div>

                    <div v-if="productAttributes && Object.keys(productAttributes).length" class="mt-3">
                        <label class="form-label fw-bold">Product Attributes</label>
                        <div class="row g-2">
                            <div v-for="(values, key) in productAttributes" :key="key" class="col-auto">
                                <label class="form-label text-capitalize">{{ key }}</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <div v-for="val in (Array.isArray(values) ? values : [values])" :key="val" class="form-check">
                                        <input class="form-check-input" type="radio" :value="val" v-model="selectedAttrs[key]" :name="key" />
                                        <label class="form-check-label">{{ val }}</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 mt-4">
                        <div class="col-6">
                            <Button :label="generating ? 'Generating...' : 'Generate Barcode'" type="submit" icon="bi bi-upc-scan" :disabled="generating" />
                        </div>
                        <div class="col-6">
                            <Button label="Reset" type="button" color="secondary" icon="bi bi-arrow-counterclockwise" @click="resetForm" />
                        </div>
                    </div>
                </form>

                <div v-if="generatedBarcode" class="mt-4 p-4 border rounded-3 text-center" style="background:#f8fafc;">
                    <h5 class="text-success mb-3"><i class="bi bi-check-circle-fill"></i> Barcode Generated!</h5>
                    <div class="mt-3 d-flex justify-content-center gap-2">
                        <button class="btn btn-primary" v-print="'#sticker-layout'">
                            <i class="bi bi-printer"></i> Print Sticker
                        </button>
                        <button class="btn btn-outline-primary" @click="resetForm">
                            <i class="bi bi-plus-circle"></i> Generate Another
                        </button>
                    </div>
                </div>

                <div id="sticker-layout" class="sticker-layout">
                    <div v-if="generatedBarcode" class="sticker">
                        <svg ref="barcodeSvg"></svg>
                        <div class="sticker-barcode-text">{{ generatedBarcode.barcode }}</div>
                        <div class="sticker-product">{{ generatedBarcode.product?.name }}</div>
                        <div class="sticker-price">{{ generatedBarcode.price }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
    import Button from '../../components/Button.vue';
    import { computed, nextTick, onMounted, reactive, ref } from 'vue';
    import axios from 'axios';
    import JsBarcode from 'jsbarcode';

    const products = ref([]);
    const branches = ref([]);
    const generating = ref(false);
    const message = ref('');
    const messageType = ref('alert-success');
    const generatedBarcode = ref(null);

    const productSearchTerm = ref('');
    const showProductDropdown = ref(false);
    const selectedProduct = ref(null);

    const selectedAttrs = reactive({});
    const barcodeSvg = ref(null);

    const fieldErrors = reactive({
        product_id: '',
        branch_id: '',
        unit_id: '',
        qty: '',
        price: '',
        vat: '',
        discount: '',
        point: '',
    });

    const clearFieldErrors = () => {
        for (const key in fieldErrors) fieldErrors[key] = '';
    };

    const form = reactive({
        product_id: '',
        branch_id: '',
        unit_id: '',
        qty: null,
        price: null,
        vat: 0,
        discount: 0,
        point: 0,
    });

    const productAttributes = computed(() => {
        if (!selectedProduct.value?.attributes) return null;
        const attrs = selectedProduct.value.attributes;
        if (typeof attrs === 'string') {
            try { return JSON.parse(attrs); } catch { return null; }
        }
        return attrs;
    });

    const filteredProducts = computed(() => {
        const term = productSearchTerm.value.toLowerCase();
        return products.value.filter(p =>
            p.name?.toLowerCase().includes(term) ||
            p.barcode?.toLowerCase().includes(term)
        ).slice(0, 10);
    });

    let blurTimeout = null;

    const hideProductDropdown = () => {
        blurTimeout = setTimeout(() => {
            showProductDropdown.value = false;
        }, 200);
    };

    const selectProduct = async (product) => {
        selectedProduct.value = product;
        form.product_id = product.id;
        form.unit_id = product.unit_id;
        productSearchTerm.value = '';
        showProductDropdown.value = false;
        message.value = '';
        for (const key of Object.keys(selectedAttrs)) {
            delete selectedAttrs[key];
        }
        const attrs = product.attributes;
        if (attrs) {
            const parsed = typeof attrs === 'string' ? JSON.parse(attrs) : attrs;
            for (const key of Object.keys(parsed)) {
                selectedAttrs[key] = '';
            }
        }
        try {
            const res = await axios(`/api/product-prices/latest/${product.id}`);
            const price = res.data?.price;
            if (price) {
                form.price = price.price ?? null;
                form.vat = price.vat ?? 0;
                form.discount = price.discount ?? 0;
                form.point = price.point ?? 0;
            }
        } catch {}
    };

    const clearProduct = () => {
        selectedProduct.value = null;
        form.product_id = '';
        form.unit_id = '';
        productSearchTerm.value = '';
        form.price = null;
        form.vat = 0;
        form.discount = 0;
        form.point = 0;
        for (const key of Object.keys(selectedAttrs)) {
            delete selectedAttrs[key];
        }
    };

    const handleGenerate = async () => {
        generating.value = true;
        message.value = '';
        generatedBarcode.value = null;
        clearFieldErrors();

        const attrObj = {};
        for (const key of Object.keys(selectedAttrs)) {
            if (selectedAttrs[key]) attrObj[key] = selectedAttrs[key];
        }

        const payload = {
            product_id: form.product_id,
            branch_id: form.branch_id,
            unit_id: form.unit_id,
            qty: form.qty,
            price: form.price,
            vat: form.vat || 0,
            discount: form.discount || 0,
            point: form.point || 0,
        };
        if (Object.keys(attrObj).length) {
            payload.attributes = JSON.stringify(attrObj);
        }

        try {
            const res = await axios.post('/api/barcodes/generate-single', payload);
            generatedBarcode.value = res.data.barcode;
            message.value = res.data.message;
            messageType.value = 'alert-success';
            await nextTick();
            if (barcodeSvg.value && generatedBarcode.value?.barcode) {
                JsBarcode(barcodeSvg.value, generatedBarcode.value.barcode, {
                    format: 'CODE128',
                    width: 1.5,
                    height: 40,
                    displayValue: false,
                    margin: 0,
                });
            }
        } catch (err) {
            const data = err.response?.data;
            message.value = data?.message || 'Failed to generate barcode';
            messageType.value = 'alert-danger';
            if (data?.errors) {
                for (const key in data.errors) {
                    if (key in fieldErrors) {
                        fieldErrors[key] = data.errors[key][0];
                    }
                }
            }
        }

        generating.value = false;
    };

    const resetForm = () => {
        generatedBarcode.value = null;
        barcodeSvg.value = null;
        message.value = '';
        clearFieldErrors();
        selectedProduct.value = null;
        form.product_id = '';
        form.branch_id = '';
        form.unit_id = '';
        form.qty = null;
        form.price = null;
        form.vat = 0;
        form.discount = 0;
        form.point = 0;
        for (const key of Object.keys(selectedAttrs)) {
            delete selectedAttrs[key];
        }
    };

    onMounted(async () => {
        try {
            const [prodRes, branchRes] = await Promise.all([
                axios.get('/api/products', { params: { perPage: 1000 } }),
                axios.get('/api/branches', { params: { perPage: 1000 } }),
            ]);
            products.value = prodRes.data?.product?.data || prodRes.data?.products?.data || prodRes.data?.products || [];
            branches.value = branchRes.data?.branch?.data || branchRes.data?.branches?.data || branchRes.data?.branches || [];
        } catch {}
    });
</script>

<style lang="scss" scoped>
    .selected-item {
        background-color: #f8f9fa;
    }
    .dropdown-menu-show {
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
    .dropdown-item-show {
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
    }
    .dropdown-item-show:hover {
        background-color: #e9ecef;
    }
    .sticker-layout {
        display: none;
    }
    @media print {
        body * {
            visibility: hidden;
        }
        #sticker-layout, #sticker-layout * {
            visibility: visible;
        }
        #sticker-layout {
            display: block !important;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            padding: 10px;
        }
        .sticker {
            width: 1.5in;
            padding: 4px 6px;
            text-align: center;
            font-family: Arial, Helvetica, sans-serif;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .sticker svg {
            max-width: 100%;
            height: auto;
        }
        .sticker-barcode-text {
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 1px;
            margin-top: 1px;
        }
        .sticker-product {
            font-size: 7px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 1px;
        }
        .sticker-price {
            font-size: 9px;
            font-weight: bold;
            margin-top: 1px;
        }
    }
</style>

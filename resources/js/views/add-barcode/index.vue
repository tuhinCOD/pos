<template>
    <div class="container-fluid">
        <div class="my-3">
            <div class="card card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="m-0">{{ isEditing ? 'Edit Barcode' : 'Generate Barcodes' }}</h4>
                    <router-link to="/barcodes" class="btn btn-primary btn-sm">
                        <i class="bi bi-eye"></i> View Barcodes
                    </router-link>
                </div>

                <div v-if="message" class="alert" :class="messageType" role="alert">
                    {{ message }}
                </div>

                <!-- Edit mode: show barcode details -->
                <template v-if="isEditing && barcode">
                    <div class="row g-3 mb-3">
                        <div class="col-3"><strong>Barcode:</strong> <code>{{ barcode.barcode }}</code></div>
                        <div class="col-3"><strong>Product:</strong> {{ barcode.product?.name }}</div>
                        <div class="col-3"><strong>Purchase:</strong> {{ barcode.purchase?.invoice_no }}</div>
                        <div class="col-3"><strong>Status:</strong> {{ barcode.status?.name }}</div>
                    </div>
                    <form @submit.prevent="handleUpdate">
                        <div class="row g-3">
                            <div class="col-3">
                                <label class="form-label">Qty</label>
                                <input type="number" step="0.001" class="form-control" :class="fieldErrors.qty ? 'is-invalid' : ''" v-model.number="form.qty" @input="fieldErrors.qty = ''" />
                                <div v-if="fieldErrors.qty" class="invalid-feedback d-block">{{ fieldErrors.qty }}</div>
                            </div>
                            <div class="col-3">
                                <label class="form-label">Price</label>
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
                            <div class="col-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" :class="fieldErrors.status_id ? 'is-invalid' : ''" v-model.number="form.status_id" @change="fieldErrors.status_id = ''">
                                    <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                                <div v-if="fieldErrors.status_id" class="invalid-feedback d-block">{{ fieldErrors.status_id }}</div>
                            </div>
                        </div>

                        <div v-if="editHasAttributes" class="row g-2 mb-3 mt-2">
                            <div class="col-12">
                                <label class="form-label fw-bold">Purchase Attributes</label>
                                <div class="row g-2">
                                    <div v-for="(values, key) in editPurchaseAttrs" :key="key" class="col-auto">
                                        <label class="form-label text-capitalize">{{ key }}</label>
                                        <div class="d-flex flex-wrap gap-2">
                                            <div v-for="val in (Array.isArray(values) ? values : [values])" :key="val" class="form-check">
                                                <input class="form-check-input" type="radio" :value="val" v-model="selectedAttributes[key]" :name="'edit_' + key" />
                                                <label class="form-check-label">{{ val }}</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-2 my-4">
                            <div class="col-6">
                                <Button label="Update Barcode" type="submit" icon="bi bi-save-fill" :disabled="updating" />
                            </div>
                        </div>
                    </form>
                </template>

                <!-- Generate mode: search purchase and configure -->
                <template v-if="!isEditing">
                    <div class="row g-3">
                        <div class="col-6 position-relative">
                            <label class="form-label">Search Purchase <span class="text-danger">*</span></label>
                            <input
                                v-if="!selectedPurchase"
                                class="form-control"
                                type="text"
                                v-model="purchaseSearchTerm"
                                placeholder="Type invoice no or product name..."
                                @input="showDropdown = true"
                                @focus="showDropdown = true"
                                @blur="hideDropdown"
                            />
                            <div v-else class="selected-item d-flex align-items-center gap-2 border rounded p-2">
                                <span class="fw-bold">{{ selectedPurchase.invoice_no }}</span>
                                <span class="text-muted small">({{ selectedPurchase.product?.name }})</span>
                                <button type="button" class="btn btn-sm btn-outline-danger ms-auto" @click="clearPurchase">
                                    <i class="bi bi-x"></i>
                                </button>
                            </div>
                            <ul v-if="showDropdown && filteredPurchases.length" class="purchase-dropdown list-unstyled">
                                <li
                                    v-for="p in filteredPurchases"
                                    :key="p.id"
                                    class="purchase-dropdown-item px-3 py-2"
                                    @mousedown.prevent="selectPurchase(p)"
                                >
                                    <span class="fw-bold">{{ p.invoice_no }}</span>
                                    <span class="text-muted small ms-2">{{ p.product?.name }} ({{ p.qty }} {{ p.unit?.name }})</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <hr v-if="selectedPurchase">

                    <div v-if="selectedPurchase" class="mt-3">
                        <h5>Purchase Details</h5>
                        <div class="row g-2">
                            <div class="col-3"><strong>Invoice:</strong> {{ selectedPurchase.invoice_no }}</div>
                            <div class="col-3"><strong>Product:</strong> {{ selectedPurchase.product?.name }}</div>
                            <div class="col-3"><strong>Qty:</strong> {{ selectedPurchase.qty }} {{ selectedPurchase.unit?.name }}</div>
                            <div class="col-3"><strong>Barcode Type:</strong> {{ selectedPurchase.product?.barcode_type || 'single' }}</div>
                        </div>

                        <div v-if="hasAttributes" class="row g-2 mb-3 mt-2">
                            <div class="col-12">
                                <label class="form-label fw-bold">Purchase Attributes</label>
                                <div class="row g-2">
                                    <div v-for="(values, key) in purchaseAttrs" :key="key" class="col-auto">
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

                        <div class="mt-3">
                            <h6>Barcode Configuration</h6>
                            <form @submit.prevent="handleGenerate">
                                <div class="row g-3">
                                    <div class="col-3">
                                        <label class="form-label">Qty</label>
                                        <input type="number" step="0.001" class="form-control" :class="fieldErrors.qty ? 'is-invalid' : ''" v-model.number="form.qty" @input="fieldErrors.qty = ''" :disabled="selectedPurchase?.product?.barcode_type === 'weight'" />
                                        <div v-if="fieldErrors.qty" class="invalid-feedback d-block">{{ fieldErrors.qty }}</div>
                                    </div>
                                    <div class="col-3">
                                        <label class="form-label">Price</label>
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

                                <div class="row g-2 mt-3">
                                    <div class="col-6">
                                        <Button :label="generating ? 'Generating...' : 'Generate Barcodes'" type="submit" icon="bi bi-upc-scan" :disabled="generating"/>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div v-if="existingBarcodes !== null" class="mt-2">
                            <div class="alert" :class="existingBarcodesCount > 0 ? 'alert-warning' : 'alert-success'">
                                <strong>{{ existingBarcodesCount }}</strong> barcodes already exist for this purchase.
                                <button v-if="existingBarcodesCount > 0" class="btn btn-sm btn-outline-primary ms-2" @click="viewBarcodes">
                                    <i class="bi bi-eye"></i> View
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

<script setup>
    import Button from '../../components/Button.vue';
    import { computed, onMounted, reactive, ref } from 'vue';
    import { useRoute, useRouter } from 'vue-router';
    import { useBarcode } from '../../stores/barcode';
    import { usePurchase } from '../../stores/purchase';

    const router = useRouter();
    const route = useRoute();
    const barcodeStore = useBarcode();
    const purchaseStore = usePurchase();

    const isEditing = computed(() => route.name === 'barcode-edit');

    const selectedPurchase = ref(null);
    const purchaseSearchTerm = ref('');
    const showDropdown = ref(false);
    const generating = ref(false);
    const updating = ref(false);
    const message = ref('');
    const messageType = ref('alert-success');
    const existingBarcodes = ref(null);
    const existingBarcodesCount = ref(0);
    const barcode = ref(null);
    const productPrice = ref(null);
    const statuses = ref([]);
    const selectedAttributes = reactive({});

    const purchaseAttrs = computed(() => parseAttrs(selectedPurchase.value?.attributes));

    const hasAttributes = computed(() => Object.keys(purchaseAttrs.value).length > 0);

    const parseAttrs = (attrs) => {
        if (!attrs) return {};
        const result = {};
        if (Array.isArray(attrs)) {
            attrs.forEach(a => { if (a.key && a.key !== 'variants') result[a.key] = a.value; });
        } else if (typeof attrs === 'object') {
            for (const key in attrs) {
                if (key === 'variants') continue;
                result[key] = attrs[key];
            }
        }
        return result;
    };

    const editPurchaseAttrs = computed(() => parseAttrs(barcode.value?.purchase?.attributes));
    const editHasAttributes = computed(() => Object.keys(editPurchaseAttrs.value).length > 0);

    const clearAttributes = () => {
        for (const key in selectedAttributes) delete selectedAttributes[key];
    };

    const populateAttributesFromPurchase = () => {
        clearAttributes();
        const attrs = purchaseAttrs.value;
        for (const key of Object.keys(attrs)) {
            selectedAttributes[key] = '';
        }
    };

    const buildAttributesPayload = () => {
        const obj = {};
        for (const key in selectedAttributes) {
            if (selectedAttributes[key] !== '' && selectedAttributes[key] !== null) {
                obj[key] = selectedAttributes[key];
            }
        }
        return Object.keys(obj).length ? JSON.stringify(obj) : null;
    };

    const form = reactive({
        qty: null,
        price: null,
        vat: null,
        discount: null,
        point: null,
        status_id: null,
    });

    const fieldErrors = reactive({
        qty: '',
        price: '',
        vat: '',
        discount: '',
        point: '',
        status_id: '',
        purchase_id: '',
    });

    const clearFieldErrors = () => {
        for (const key in fieldErrors) {
            fieldErrors[key] = '';
        }
    };

    let blurTimeout = null;

    const purchases = computed(() => purchaseStore.allPurchases);
    const filteredPurchases = computed(() => {
        const term = purchaseSearchTerm.value.toLowerCase();
        return purchases.value.filter(p =>
            p.invoice_no?.toLowerCase().includes(term) ||
            p.product?.name?.toLowerCase().includes(term)
        ).slice(0, 10);
    });

    const hideDropdown = () => {
        blurTimeout = setTimeout(() => {
            showDropdown.value = false;
        }, 200);
    };

    const selectPurchase = async (purchase) => {
        selectedPurchase.value = purchase;
        purchaseSearchTerm.value = '';
        showDropdown.value = false;
        message.value = '';
        existingBarcodes.value = null;

        const price = await fetchProductPrice(purchase.product_id);
        productPrice.value = price;

        if (purchase.product?.barcode_type === 'weight') {
            form.qty = 1;
        } else if (purchase.product?.barcode_type === 'piece') {
            form.qty = purchase.qty;
        } else {
            form.qty = null;
        }
        form.price = price?.price ?? null;
        form.vat = price?.vat ?? null;
        form.discount = price?.discount ?? null;
        form.point = price?.point ?? null;

        populateAttributesFromPurchase();

        const result = await barcodeStore.getBarcodesByPurchase(purchase.id);
        if (result.status === 'success') {
            existingBarcodes.value = result.data.barcodes;
            existingBarcodesCount.value = result.data.barcodes.length;
        }
    };

    const fetchProductPrice = async (productId) => {
        try {
            const { default: axios } = await import('axios');
            const res = await axios(`/api/product-prices/latest/${productId}`);
            return res.data?.price || null;
        } catch {
            return null;
        }
    };

    const clearPurchase = () => {
        selectedPurchase.value = null;
        purchaseSearchTerm.value = '';
        existingBarcodes.value = null;
        existingBarcodesCount.value = 0;
        productPrice.value = null;
        clearAttributes();
    };

    const viewBarcodes = () => {
        router.push({ name: 'barcodes', query: { purchase_id: selectedPurchase.value.id } });
    };

    const handleGenerate = async () => {
        if (!selectedPurchase.value) return;

        generating.value = true;
        message.value = '';
        clearFieldErrors();

        const payload = { purchase_id: selectedPurchase.value.id };
        if (form.qty !== null) payload.qty = form.qty;
        if (form.price !== null) payload.price = form.price;
        if (form.vat !== null) payload.vat = form.vat;
        if (form.discount !== null) payload.discount = form.discount;
        if (form.point !== null) payload.point = form.point;

        const attrs = buildAttributesPayload();
        if (attrs) payload.attributes = attrs;

        const result = await barcodeStore.generateBarcodes(payload);

        if (result.status === 'success') {
            message.value = result.data.message;
            messageType.value = 'alert-success';
            existingBarcodesCount.value = result.data.total;
            form.qty = null;
            form.price = null;
            form.vat = null;
            form.discount = null;
            form.point = null;
            clearAttributes();
        } else if (result.errors && Object.keys(result.errors).length) {
            for (const key in result.errors) {
                if (key in fieldErrors) {
                    fieldErrors[key] = result.errors[key][0];
                }
            }
            message.value = 'Please fix the errors below';
            messageType.value = 'alert-danger';
        } else {
            message.value = result.message || 'Failed to generate barcodes';
            messageType.value = 'alert-danger';
        }

        generating.value = false;
    };

    const handleUpdate = async () => {
        if (!barcode.value) return;

        updating.value = true;
        message.value = '';
        clearFieldErrors();

        const payload = {};
        if (form.qty !== null) payload.qty = form.qty;
        if (form.price !== null) payload.price = form.price;
        if (form.vat !== null) payload.vat = form.vat;
        if (form.discount !== null) payload.discount = form.discount;
        if (form.point !== null) payload.point = form.point;
        if (form.status_id) payload.status_id = form.status_id;

        const attrs = buildAttributesPayload();
        if (attrs) payload.attributes = attrs;

        const result = await barcodeStore.updateBarcode(barcode.value.id, payload);

        if (result.status === 'success') {
            message.value = 'Barcode updated successfully';
            messageType.value = 'alert-success';
            barcode.value = result.data.barcode;
        } else if (result.errors) {
            for (const key in result.errors) {
                if (key in fieldErrors) {
                    fieldErrors[key] = result.errors[key][0];
                }
            }
            message.value = 'Please fix the errors below';
            messageType.value = 'alert-danger';
        } else {
            message.value = result.message || 'Failed to update barcode';
            messageType.value = 'alert-danger';
        }

        updating.value = false;
    };

    onMounted(async () => {
        if (!purchaseStore.allPurchases.length) {
            await purchaseStore.loadPurchases({}, 1, 100);
        }
    });

    onMounted(async () => {
        if (isEditing.value && route.params.id) {
            const result = await barcodeStore.getBarcode(route.params.id);
            if (result.status === 'success') {
                barcode.value = result.data.barcode;
                productPrice.value = result.data.product_price;
                form.qty = barcode.value.qty;
                form.price = barcode.value.price;
                form.vat = barcode.value.vat;
                form.discount = barcode.value.discount;
                form.point = barcode.value.point;
                form.status_id = barcode.value.status_id;

                clearAttributes();
                const editAttrs = editPurchaseAttrs.value;
                for (const key of Object.keys(editAttrs)) {
                    selectedAttributes[key] = '';
                }
                if (barcode.value.attributes && typeof barcode.value.attributes === 'object') {
                    for (const key in barcode.value.attributes) {
                        if (key in selectedAttributes) {
                            selectedAttributes[key] = barcode.value.attributes[key];
                        }
                    }
                }
            }
        }
    });

    onMounted(async () => {
        try {
            const { default: axios } = await import('axios');
            const res = await axios('/api/v1/statuses');
            statuses.value = res.data?.statuses || res.data?.data || [];
        } catch {}
    });
</script>

<style lang="scss" scoped>
    .selected-item {
        background-color: #f8f9fa;
    }
    .purchase-dropdown {
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
    .purchase-dropdown-item {
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
    }
    .purchase-dropdown-item:hover {
        background-color: #e9ecef;
    }
</style>

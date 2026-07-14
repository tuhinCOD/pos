<template>
    <form id="damage-form" @submit.prevent="updateForm">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="m-0">{{ isEditing ? 'Edit Damage' : 'Add Damage' }}</h4>
            <router-link to="/damages" class="btn btn-primary btn-sm">
                <i class="bi bi-eye"></i> View Damages
            </router-link>
        </div>
        <div class="my-3">
            <div class="row g-2">
                <div class="col-lg-4 col-md-6 position-relative" style="margin-top: 15px;">
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
                <div class="col-lg-4 col-md-6" style="margin-top: 15px;">
                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                    <select class="form-select" v-model="field.status" id="status" name="status">
                        <option value="">Select Status</option>
                        <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <div id="statusHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.status?.[0] }}</div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <Input label="Qty" id="qty" type="number" step="0.001" errid="qtyHelp" name="qty" v-model="field.qty" placeholder="0" :err="errors.qty?.[0]" :disabled="isEditing" must/>
                </div>
                <div class="col-lg-4 col-md-6" style="margin-top: 15px;">
                    <label for="unit" class="form-label">Unit <span class="text-danger">*</span></label>
                    <select class="form-select" v-model="field.unit" id="unit" name="unit">
                        <option value="">Select Unit</option>
                        <option v-for="u in productUnits" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                    <div id="unitHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.unit?.[0] }}</div>
                </div>
                <div class="col-lg-4 col-md-6" style="margin-top: 15px;">
                    <label for="branch" class="form-label">Branch <span class="text-danger">*</span></label>
                    <select class="form-select" v-model="field.branch" id="branch" name="branch">
                        <option value="">Select Branch</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                    <div id="branchHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.branch?.[0] }}</div>
                </div>
                <div class="col-lg-4 col-md-6" style="margin-top: 15px;">
                    <label for="remarks" class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control" id="remarks" placeholder="Write remarks here..." v-model="field.remarks"></textarea>
                    <div id="remarksHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.remarks?.[0] }}</div>
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
                                <input class="form-check-input" type="radio" :value="val" v-model="selectedAttributes[key]" :name="key" :disabled="isEditing" />
                                {{ val }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        </div>

        <div class="row g-2 my-3">
            <div class="col-sm-6">
                <Button :label="editDamage ? 'Update' : 'Submit'" id="submitBtn" type="submit" icon="bi bi-save-fill"/>
            </div>
            <div class="col-sm-6">
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
        errors: { type: Object, default: () => ({}) },
        editDamage: { type: Object, default: null },
    });

    const emit = defineEmits([
        "reset-form",
        "update-damage",
    ]);

    const field = reactive({
        product: '',
        status: '',
        unit: '',
        branch: '',
        qty: '',
        remarks: '',
    });

    const isEditing = ref(false);

    const selectedProduct = ref(null);
    const productSearchTerm = ref('');
    const showProductDropdown = ref(false);
    let productBlurTimeout = null;

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

    const clearAttributes = () => {
        for (const key in selectedAttributes) delete selectedAttributes[key];
    };

    const populateAttributesFromProduct = (product) => {
        clearAttributes();
        if (product.attributes && typeof product.attributes === 'object') {
            for (const key of Object.keys(product.attributes)) {
                selectedAttributes[key] = '';
            }
        }
    };

    const restoreAttributes = (attrs) => {
        if (!attrs) return;
        const parsed = typeof attrs === 'string' ? JSON.parse(attrs) : attrs;
        for (const key in parsed) {
            selectedAttributes[key] = parsed[key];
        }
    };

    const filteredProducts = computed(() => {
        const term = productSearchTerm.value.toLowerCase();
        return props.products.filter(p =>
            p.name.toLowerCase().includes(term) ||
            p.barcode.toLowerCase().includes(term)
        ).slice(0, 10);
    });

    const productUnits = computed(() => {
        if (!selectedProduct.value) return props.units;
        return props.units.filter(u => u.id === selectedProduct.value.unit_id);
    });

    const hideProductDropdown = () => {
        productBlurTimeout = setTimeout(() => {
            showProductDropdown.value = false;
        }, 200);
    };

    const selectProduct = (product) => {
        selectedProduct.value = product;
        field.product = product.id;
        field.unit = product.unit_id || '';
        productSearchTerm.value = '';
        showProductDropdown.value = false;
        populateAttributesFromProduct(product);
    };

    const clearProduct = () => {
        selectedProduct.value = null;
        field.product = '';
        productSearchTerm.value = '';
        clearAttributes();
    };

    const updateForm = () => {
        const formData = new FormData();
        for (const key in field) {
            formData.append(key, field[key]);
        }
        const attrs = buildAttributesPayload();
        if (attrs) {
            formData.append('attributes', attrs);
        }
        emit('update-damage', formData);
    };

    watch(() => props.editDamage, (val) => {
        if (val) {
            isEditing.value = true;
            field.status = val.status_id || '';
            field.unit = val.unit_id || '';
            field.branch = val.branch_id || '';
            field.qty = val.qty || '';
            field.remarks = val.remarks || '';
            if (val.product) {
                selectedProduct.value = val.product;
                field.product = val.product_id || val.product.id;
                productSearchTerm.value = '';
                populateAttributesFromProduct(val.product);
            } else if (val.product_id && !selectedProduct.value) {
                const prod = props.products.find(p => p.id === val.product_id);
                if (prod) {
                    selectedProduct.value = prod;
                    field.product = prod.id;
                    productSearchTerm.value = '';
                    populateAttributesFromProduct(prod);
                }
            }
            if (val.attributes) {
                restoreAttributes(val.attributes);
            }
        } else {
            isEditing.value = false;
        }
    }, { immediate: true });

    const resetForm = () => {
        field.product = '';
        field.status = '';
        field.unit = '';
        field.branch = '';
        field.qty = '';
        field.remarks = '';
        isEditing.value = false;
        selectedProduct.value = null;
        productSearchTerm.value = '';
        clearAttributes();
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
</style>

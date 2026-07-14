<template>
    <form id="product-price-form" @submit.prevent="submitForm">
        <div class="d-flex justify-content-between align-items-center">
            <h3>Product Price Form</h3>
            <button
            class="btn btn-primary btn-sm"
            @click="goToViewProduct"
            type="button"
            title="View product"
            >
                <i class="bi bi-eye"></i>
                View Products
            </button>
        </div>
        <div class="my-3">
            <div class="row g-2">
                <div class="col-12 position-relative">
                    <label class="form-label">Search Product</label>
                    <input
                        v-if="!selectedProduct"
                        class="form-control"
                        type="text"
                        v-model="productSearchTerm"
                        placeholder="Type product name or barcode..."
                        @input="onSearchInput"
                        @focus="showDropdown = true"
                        @blur="hideDropdown"
                    />
                    <div v-else class="selected-product d-flex align-items-center gap-2 border rounded p-2">
                        <span class="fw-bold">{{ selectedProduct.name }}</span>
                        <span class="text-muted small">({{ selectedProduct.barcode }})</span>
                        <button type="button" class="btn btn-sm btn-outline-danger ms-auto" @click="clearProduct">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                    <ul v-if="showDropdown && filteredProducts.length" class="product-dropdown list-unstyled">
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
                    <div v-if="showDropdown && productSearchTerm && !filteredProducts.length" class="product-dropdown list-unstyled">
                        <div class="px-3 py-2 text-muted">No products found.</div>
                    </div>
                </div>
                <div class="col-6">
                    <Input label="Price" id="price" type="number" step="0.001" errid="priceHelp" name="price" v-model="field.price" placeholder="0.00" :err="errors.price?.[0]" must/>
                </div>
                <div class="col-6">
                    <Input label="VAT" id="vat" type="number" step="0.001" errid="vatHelp" name="vat" v-model="field.vat" placeholder="0.00" :err="errors.vat?.[0]" must/>
                </div>
                <div class="col-6">
                    <Input label="Point" id="point" type="number" step="0.00" errid="pointHelp" name="point" v-model="field.point" placeholder="0.00" :err="errors.point?.[0]" />
                </div>
                <div class="col-12">
                    <label for="remarks" class="form-label">Remarks</label>
                    <textarea class="form-control" id="remarks" name="remarks" v-model="field.remarks" placeholder="Remarks..." rows="2"></textarea>
                    <div class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.remarks?.[0] }}</div>
                </div>
            </div>
        </div>
        <div class="row g-2 my-3">
            <div class="col-6">
                <Button :label="isEditing ? 'Update' : 'Submit'" id="submitBtn" type="submit" icon="bi bi-save-fill"/>
            </div>
            <div class="col-6">
                <Button label="Reset" type="button" id="resetBtn" color="secondary" icon="bi bi-x-square-fill" @click="handleReset"/>
            </div>
        </div>
    </form>
</template>

<script setup>
    import Input from '../../components/Input.vue';
    import Button from '../../components/Button.vue';
    import { computed, reactive, ref, watch } from 'vue';
    import { useRouter } from 'vue-router';

    const router = useRouter();

    const goToViewProduct = () => {
        router.push({ name: 'products' });
    };

    const props = defineProps({
        products: { type: Array, default: () => [] },
        units: { type: Array, default: () => [] },
        productPrices: { type: Array, default: () => [] },
        errors: { type: Object, default: () => ({}) },
        initialProductId: { type: Number, default: null },
        editPrice: { type: Object, default: null },
    });

    const emit = defineEmits([
        "reset-form",
        "update-product-price",
    ]);

    const field = reactive({
        product: null,
        price: '',
        vat: '',
        point: '',
        remarks: '',
    });

    const isEditing = ref(false);
    const editPriceId = ref(null);
    const selectedProduct = ref(null);
    const productSearchTerm = ref('');
    const showDropdown = ref(false);
    let blurTimeout = null;

    const filteredProducts = computed(() => {
        // if (!productSearchTerm.value) return [];
        const term = productSearchTerm.value.toLowerCase();
        return props.products.filter(p =>
            p.name.toLowerCase().includes(term) ||
            p.barcode.toLowerCase().includes(term)
        ).slice(0, 10);
    });

    const onSearchInput = () => {
        showDropdown.value = true;
    };

    const hideDropdown = () => {
        blurTimeout = setTimeout(() => {
            showDropdown.value = false;
        }, 200);
    };

    const selectProduct = (product) => {
        selectedProduct.value = product;
        field.product = product.id;
        productSearchTerm.value = '';
        showDropdown.value = false;

        const existing = props.productPrices.find(pp => pp.product_id === product.id);
        if (existing) {
            isEditing.value = true;
            editPriceId.value = existing.id;
            field.price = existing.price;
            field.vat = existing.vat;
            field.point = existing.point || '';
            field.remarks = existing.remarks || '';
        } else {
            isEditing.value = false;
            editPriceId.value = null;
            field.price = '';
            field.vat = '';
            field.point = '';
            field.remarks = '';
        }
    };

    const clearProduct = () => {
        selectedProduct.value = null;
        field.product = null;
        isEditing.value = false;
        editPriceId.value = null;
        field.price = '';
        field.vat = '';
        field.point = '';
        field.remarks = '';
        productSearchTerm.value = '';
    };

    const resetForm = () => {
        clearProduct();
    };

    const handleReset = () => {
        resetForm();
        emit("reset-form");
    };

    const submitForm = () => {
        const formData = new FormData();
        formData.append('product', field.product);
        formData.append('price', field.price);
        formData.append('vat', field.vat);
        formData.append('point', field.point || 0);
        formData.append('remarks', field.remarks || '');

        emit('update-product-price', formData, editPriceId.value);
    };

    watch(() => props.editPrice, (val) => {
        if (val) {
            isEditing.value = true;
            editPriceId.value = val.id;
            if (val.product) {
                selectedProduct.value = val.product;
                field.product = val.product.id;
                productSearchTerm.value = '';
            }
            field.price = val.price;
            field.vat = val.vat;
            field.point = val.point || '';
            field.remarks = val.remarks || '';
        }
    }, { immediate: true });

    watch(() => props.initialProductId, (val) => {
        if (val && props.products.length) {
            const found = props.products.find(p => p.id === val);
            if (found) {
                selectProduct(found);
            }
        }
    }, { immediate: true });

    watch(() => props.products, (products) => {
        if (props.initialProductId && products.length && !selectedProduct.value) {
            const found = products.find(p => p.id === props.initialProductId);
            if (found) {
                selectProduct(found);
            }
        }
    });

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

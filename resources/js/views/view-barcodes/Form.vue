<template>
    <div class="row g-3 align-items-end">
        <div class="col-3">
            <label class="form-label" style="font-size: 13px;">Search</label>
            <input class="form-control form-control-sm" type="text" v-model="filters.search" placeholder="Barcode, product name..." @input="emitUpdate"/>
        </div>
        <div class="col-3 position-relative">
            <label class="form-label" style="font-size: 13px;">Product</label>
            <input
                v-if="!selectedProduct"
                class="form-control form-control-sm"
                type="text"
                v-model="productSearchTerm"
                placeholder="Search product..."
                @input="showProductDropdown = true"
                @focus="showProductDropdown = true"
                @blur="hideProductDropdown"
            />
            <div v-else class="d-flex align-items-center gap-2 border rounded p-1" style="background:#f8f9fa;">
                <span class="small fw-bold px-1">{{ selectedProduct.name }}</span>
                <button type="button" class="btn btn-sm btn-outline-danger ms-auto p-0 px-1" @click="clearProduct">
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
                    <span class="fw-bold small">{{ p.name }}</span>
                </li>
            </ul>
        </div>
        <div class="col-3">
            <label class="form-label" style="font-size: 13px;">Branch</label>
            <select class="form-select form-select-sm" v-model="filters.branch_id" @change="emitUpdate">
                <option value="">All Branches</option>
                <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
        </div>
        <div class="col-3 position-relative">
            <label class="form-label" style="font-size: 13px;">Purchase Invoice</label>
            <input
                v-if="!selectedPurchase"
                class="form-control form-control-sm"
                type="text"
                v-model="purchaseSearchTerm"
                placeholder="Search invoice..."
                @input="showPurchaseDropdown = true"
                @focus="showPurchaseDropdown = true"
                @blur="hidePurchaseDropdown"
            />
            <div v-else class="d-flex align-items-center gap-2 border rounded p-1" style="background:#f8f9fa;">
                <span class="small fw-bold px-1">{{ selectedPurchase.invoice_no }}</span>
                <button type="button" class="btn btn-sm btn-outline-danger ms-auto p-0 px-1" @click="clearPurchase">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <ul v-if="showPurchaseDropdown && filteredPurchases.length" class="dropdown-menu-show list-unstyled">
                <li
                    v-for="p in filteredPurchases"
                    :key="p.id"
                    class="dropdown-item-show px-3 py-2"
                    @mousedown.prevent="selectPurchase(p)"
                >
                    <span class="fw-bold small">{{ p.invoice_no }}</span>
                    <span class="text-muted small ms-2">{{ p.product?.name }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
    import { computed, reactive, ref } from 'vue';

    const props = defineProps({
        products: { type: Array, default: () => [] },
        branches: { type: Array, default: () => [] },
        purchases: { type: Array, default: () => [] },
    });

    const emit = defineEmits(['update:filters']);

    const filters = reactive({
        search: '',
        product_id: '',
        branch_id: '',
        purchase_id: '',
    });

    const selectedProduct = ref(null);
    const productSearchTerm = ref('');
    const showProductDropdown = ref(false);
    let productBlurTimeout = null;

    const selectedPurchase = ref(null);
    const purchaseSearchTerm = ref('');
    const showPurchaseDropdown = ref(false);
    let purchaseBlurTimeout = null;

    const filteredProducts = computed(() => {
        const term = productSearchTerm.value.toLowerCase();
        return props.products.filter(p =>
            p.name?.toLowerCase().includes(term)
        ).slice(0, 10);
    });

    const filteredPurchases = computed(() => {
        const term = purchaseSearchTerm.value.toLowerCase();
        return props.purchases.filter(p =>
            p.invoice_no?.toLowerCase().includes(term) ||
            p.product?.name?.toLowerCase().includes(term)
        ).slice(0, 10);
    });

    const hideProductDropdown = () => {
        productBlurTimeout = setTimeout(() => {
            showProductDropdown.value = false;
        }, 200);
    };

    const hidePurchaseDropdown = () => {
        purchaseBlurTimeout = setTimeout(() => {
            showPurchaseDropdown.value = false;
        }, 200);
    };

    const selectProduct = (product) => {
        selectedProduct.value = product;
        filters.product_id = product.id;
        productSearchTerm.value = '';
        showProductDropdown.value = false;
        emitUpdate();
    };

    const clearProduct = () => {
        selectedProduct.value = null;
        filters.product_id = '';
        productSearchTerm.value = '';
        emitUpdate();
    };

    const selectPurchase = (purchase) => {
        selectedPurchase.value = purchase;
        filters.purchase_id = purchase.id;
        purchaseSearchTerm.value = '';
        showPurchaseDropdown.value = false;
        emitUpdate();
    };

    const clearPurchase = () => {
        selectedPurchase.value = null;
        filters.purchase_id = '';
        purchaseSearchTerm.value = '';
        emitUpdate();
    };

    let timeout = null;
    const emitUpdate = () => {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            emit('update:filters', { ...filters });
        }, 500);
    };
</script>

<style lang="scss" scoped>
    .dropdown-menu-show {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 1000;
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        max-height: 200px;
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
</style>

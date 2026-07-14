<template>
    <div class="d-flex justify-content-between align-items-center">
        <h3>View Stock</h3>
    </div>
    <div class="my-3">
        <div class="row g-2">
            <div class="col-4">
                <Input label="Search" id="search" type="text" name="search" v-model="filters.search" placeholder="Search product, barcode, branch..."/>
            </div>
            <div class="col-4" style="margin-top: 14px;">
                <div class="dropdown w-100">
                    <label for="product-dropdown-btn" class="form-label">Product</label>
                    <button class="btn border-secondary-subtle w-100 d-flex justify-content-between align-items-center" id="product-dropdown-btn"
                            type="button"
                            data-bs-toggle="dropdown">
                        <span class="text-truncate">{{ selectedProductLabel }}</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <ul class="dropdown-menu p-0 w-100 dropdown-menu-scroll" @click.stop>
                        <li v-for="product in props.products" :key="product.id">
                            <label class="dropdown-item d-flex align-items-center gap-2 mb-0">
                                <input class="form-check-input m-0" type="checkbox" :value="product.id" v-model="filters.products">
                                {{ product.name }}
                            </label>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="col-4" style="margin-top: 14px;">
                <label for="branch" class="form-label">Branch</label>
                <select id="branch" class="form-select" name="branch" v-model="filters.branch">
                    <option value="">Select Branch</option>
                    <option v-for="branch in props.branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
                </select>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, reactive, watch } from 'vue';
import Input from '../../components/Input.vue';

const emit = defineEmits(['update:filters']);

const props = defineProps({
    products: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
    filterValues: { type: Object, default: () => ({}) },
});

let updatingFromProp = false;

const filters = reactive({
    search: props.filterValues?.search || '',
    products: props.filterValues?.products ? [...props.filterValues.products] : [],
    branch: props.filterValues?.branch || '',
});

const selectedProductLabel = computed(() => {
    const count = filters.products.length;
    if (count === 0) return 'Choose Product';
    if (count === 1) {
        const product = props.products.find(p => p.id === filters.products[0]);
        return product ? product.name : 'Choose Product';
    }
    const names = props.products
        .filter(p => filters.products.includes(p.id))
        .map(p => p.name);
    return names.join(', ');
});

watch(() => props.filterValues, (val) => {
    if (!val) return;
    updatingFromProp = true;
    filters.search = val.search || '';
    filters.products = val.products ? [...val.products] : [];
    filters.branch = val.branch || '';
    nextTick(() => { updatingFromProp = false; });
}, { deep: true });

watch(filters, () => {
    if (updatingFromProp) return;
    emit('update:filters', { ...filters });
}, { deep: true, immediate: true });
</script>

<style lang="scss" scoped>
.dropdown-menu-scroll {
    max-height: 300px;
    overflow-y: auto;
}
</style>

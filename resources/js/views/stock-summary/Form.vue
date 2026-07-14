<template>
    <div class="d-flex justify-content-between align-items-center">
        <h3>Stock Summary</h3>
    </div>
    <div class="my-3">
        <div class="row g-2">
            <div class="col-4">
                <Input label="Search" id="search" type="text" name="search" v-model="filters.search" placeholder="Search product, barcode, branch..."/>
            </div>
            <div class="col-4" style="margin-top: 14px;">
                <label for="product" class="form-label">Product</label>
                <select id="product" class="form-select" name="product" v-model="filters.product_id">
                    <option value="">All Products</option>
                    <option v-for="product in props.products" :key="product.id" :value="product.id">{{ product.name }}</option>
                </select>
            </div>
        </div>
    </div>
</template>

<script setup>
import { nextTick, reactive, watch } from 'vue';
import Input from '../../components/Input.vue';

const emit = defineEmits(['update:filters']);

const props = defineProps({
    products: { type: Array, default: () => [] },
    filterValues: { type: Object, default: () => ({}) },
});

let updatingFromProp = false;

const filters = reactive({
    search: props.filterValues?.search || '',
    product_id: props.filterValues?.product_id || '',
});

watch(() => props.filterValues, (val) => {
    if (!val) return;
    updatingFromProp = true;
    filters.search = val.search || '';
    filters.product_id = val.product_id || '';
    nextTick(() => { updatingFromProp = false; });
}, { deep: true });

watch(filters, () => {
    if (updatingFromProp) return;
    emit('update:filters', { ...filters });
}, { deep: true, immediate: true });
</script>

<style lang="scss" scoped>
</style>

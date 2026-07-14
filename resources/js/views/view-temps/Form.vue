<template>
    <div class="d-flex justify-content-between align-items-center">
        <h3>View Temps</h3>
        <button
        v-if="auth.user?.role?.name !== 'super admin'"
        class="btn btn-primary btn-sm"
        @click="goToAddTemp"
        title="Add temp"
        >
            <i class="bi bi-plus-circle"></i>
            Add Temp
        </button>
    </div>
    <div class="my-3">
        <div class="row g-2">
            <div class="col-3">
                <Input label="Search" id="search" type="text" name="search" v-model="filters.search" placeholder="Search..."/>
            </div>
            <div class="col-3" style="margin-top: 14px;">
                <div class="dropdown w-100">
                    <label for="dropdown-btn" class="form-label">Products</label>
                    <button class="btn border-secondary-subtle w-100 d-flex justify-content-between align-items-center" id="dropdown-btn"
                            type="button"
                            data-bs-toggle="dropdown">
                        <span class="text-truncate category-text">{{ selectedProductsLabel }}</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <ul class="dropdown-menu p-0 w-100 dropdown-products" @click.stop>
                        <li v-for="p in props.products" :key="p.id">
                            <label class="dropdown-item d-flex align-items-center gap-2 mb-0">
                                <input class="form-check-input m-0" type="checkbox" :value="p.id" v-model="selectedProducts">
                                {{ p.name }}
                            </label>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="col-3" style="margin-top: 14px;">
                <label for="status" class="form-label">Status</label>
                <select id="status" class="form-select" aria-label="Default select example" name="status" v-model="filters.status">
                    <option value="">Select Status</option>
                    <option v-for="s in props.statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>
            <div class="col-3" style="margin-top: 14px;">
                <label for="branch" class="form-label">Branch</label>
                <select id="branch" class="form-select" aria-label="Default select example" name="branch" v-model="filters.branch">
                    <option value="">Select Branch</option>
                    <option v-for="b in props.branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue';
import Input from '../../components/Input.vue';
import { useRouter } from 'vue-router';
import { useAuth } from '../../stores/auth';

const auth = useAuth();

const router = useRouter();

const goToAddTemp = () => {
    router.push({ name: 'temp-create' });
};

const emit = defineEmits(['update:filters']);

const props = defineProps({
    statuses: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    filterValues: { type: Object, default: () => ({}) },
});

let updatingFromProp = false;

const selectedProducts = ref([]);

const filters = reactive({
    search: props.filterValues?.search || '',
    products: props.filterValues?.products || '',
    status: props.filterValues?.status || '',
    branch: props.filterValues?.branch || '',
});

const selectedProductsLabel = computed(() => {
    const count = selectedProducts.value.length;
    if (count === 0) return 'Choose Products';
    if (count === 1) {
        const prod = props.products.find(p => p.id === selectedProducts.value[0]);
        return prod ? prod.name : 'Choose Products';
    }
    const names = props.products
        .filter(p => selectedProducts.value.includes(p.id))
        .map(p => p.name);
    return names.join(', ');
});

watch(() => props.filterValues, (val) => {
    if (!val) return;
    updatingFromProp = true;
    filters.search = val.search || '';
    filters.products = val.products || '';
    filters.status = val.status || '';
    filters.branch = val.branch || '';
    if (val.products) {
        selectedProducts.value = String(val.products).split(',').map(Number);
    } else {
        selectedProducts.value = [];
    }
    nextTick(() => { updatingFromProp = false; });
}, { deep: true });

watch(filters, () => {
    if (updatingFromProp) return;
    emit('update:filters', { ...filters });
}, { deep: true, immediate: true });

watch(selectedProducts, (val) => {
    if (updatingFromProp) return;
    filters.products = val.join(',');
}, { deep: true });
</script>

<style lang="scss" scoped>
.dropdown-products {
    max-height: 300px;
    overflow-y: auto;
}
</style>

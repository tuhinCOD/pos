<template>
    <div class="d-flex justify-content-between align-items-center">
        <h3>View Product</h3>
        <button
        v-if="auth.user?.role?.name !== 'super admin' && auth.user?.role?.name !== 'cashier' && auth.user?.role?.name !== 'warehouse staff'"
        class="btn btn-primary btn-sm"
        @click="goToAddProduct"
        title="Add product"
        >
            <i class="bi bi-plus-circle"></i>
            Add Product
        </button>
    </div>
    <div class="my-3">
        <div class="row g-2">
            <div class="col-3">
                <Input label="Search" id="search" type="text" name="search" v-model="filters.search" placeholder="Search..."/>
            </div>
            <div class="col-3" style="margin-top: 14px;">
                <div class="dropdown w-100">
                    <label for="dropdown-btn" class="form-label">Category</label>
                    <button class="btn border-secondary-subtle w-100 d-flex justify-content-between align-items-center" id="dropdown-btn"
                            type="button"
                            data-bs-toggle="dropdown">
                        <span class="text-truncate category-text">{{ selectedCategoryLabel }}</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <ul class="dropdown-menu p-0 w-100 dropdown-category" @click.stop>
                        <li v-for="cat in props.categories" :key="cat.id">
                            <label class="dropdown-item d-flex align-items-center gap-2 mb-0">
                                <input class="form-check-input m-0" type="checkbox" :value="cat.id" v-model="filters.categories">
                                {{ cat.name }}
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
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, reactive, watch } from 'vue';
import Input from '../../components/Input.vue';
import { useRouter } from 'vue-router';
import { useAuth } from '../../stores/auth';
    
const auth = useAuth();

const router = useRouter();

const goToAddProduct = () => {
    router.push({ name: 'add-product' });
};

const emit = defineEmits(['update:filters']);

const props = defineProps({
    categories: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    filterValues: { type: Object, default: () => ({}) },
});

let updatingFromProp = false;

const filters = reactive({
    search: props.filterValues?.search || '',
    categories: props.filterValues?.categories ? [...props.filterValues.categories] : [],
    status: props.filterValues?.status || '',
});

const selectedCategoryLabel = computed(() => {
    const count = filters.categories.length;
    if (count === 0) return 'Choose Category';
    if (count === 1) {
        const cat = props.categories.find(c => c.id === filters.categories[0]);
        return cat ? cat.name : 'Choose Category';
    }
    const names = props.categories
        .filter(c => filters.categories.includes(c.id))
        .map(c => c.name);

    return names.join(', ');
});

watch(() => props.filterValues, (val) => {
    if (!val) return;
    updatingFromProp = true;
    filters.search = val.search || '';
    filters.categories = val.categories ? [...val.categories] : [];
    filters.status = val.status || '';
    nextTick(() => { updatingFromProp = false; });
}, { deep: true });

watch(filters, () => {
    if (updatingFromProp) return;
    emit('update:filters', { ...filters });
}, { deep: true, immediate: true });
</script>

<style lang="scss" scoped>
.dropdown-category {
    max-height: 300px;
    overflow-y: auto;
}
</style>

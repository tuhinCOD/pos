<template>
    <div class="container-fluid">
        <div class="my-3">
            <div class="card card-body">
                <Form 
                :categories="categories"
                :statuses="statuses"
                :filterValues="filters"
                @update:filters="filters = $event"/>
                <hr style="margin-left:-1rem; margin-right:-1rem;">
                <Table
                :products="products"
                :pagination="pagination"
                :perPage="perPage"
                :page="page"
                :exportEndpoint="'products'"
                :exportFilters="buildApiFilters()"
                @export-started="handleExportStarted"
                @edit-product="handleEditProduct"
                @edit-product-price="handleEditProductPrice"
                @delete-product="handleDeleteProduct"
                @view-product="handleViewProduct"
                @page-change="handlePageChange"
                @per-page-change="handlePerPageChange"
                @reload="load"
                />
            </div>
        </div>
        <div v-if="viewProduct" class="modal d-block" tabindex="-1" @click.self="viewProduct = null">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ viewProduct.name }} - Images</h5>
                        <button type="button" class="btn-close" @click="viewProduct = null"></button>
                    </div>
                    <div class="modal-body">
                        <div v-if="viewProduct.images?.length" class="row g-3">
                            <div v-for="image in viewProduct.images" :key="image.id" class="col-md-4 col-sm-6">
                                <div class="card position-relative">
                                    <div v-if="auth.user?.role?.name !== 'super admin'" class="dropdown position-absolute top-0 end-0 m-1">
                                        <button class="btn btn-sm btn-dark rounded-circle p-1" style="width: 28px; height: 28px; opacity: 0.8;" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots-vertical text-white" style="font-size: 14px;"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end" style="min-width: 100px;">
                                            <li><button class="dropdown-item text-danger" @click="deleteImage(image.id)"><i class="bi bi-trash me-2"></i>Delete</button></li>
                                        </ul>
                                    </div>
                                    <img :src="`/storage/${image.image}`" class="card-img-top" :alt="image.title || 'Product image'" style="height: 200px; object-fit: cover;">
                                    <div v-if="image.title" class="card-body p-2">
                                        <p class="card-text text-center small mb-0">{{ image.title }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center text-muted py-4">
                            No images available for this product.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="viewProduct = null">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="viewProduct" class="modal-backdrop fade show"></div>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from "vue";
import debounce from "lodash/debounce";
import { useRoute, useRouter } from "vue-router";
import axios from "axios";
import { useProduct } from "../../stores/product";
import { useAuth } from '../../stores/auth';
import { useExport } from '../../composables/useExport';
import Table from './Table.vue';
import Form from './Form.vue';

const auth = useAuth();
const dash = useProduct();
const router = useRouter();
const route = useRoute();

let fromUrl = false;
let syncingUrl = false;

function parseQuery(q) {
    return {
        search: q.search || '',
        categories: q.categories
            ? String(q.categories).split(',').map(Number).filter(n => !isNaN(n))
            : [],
        status: q.status || '',
    };
}

const filters = ref(parseQuery(route.query));
const page = ref(Number(route.query.page) || 1);
const perPage = ref(Number(route.query.perPage) || 15);

const categories = computed(() => dash.allCategories);
const statuses = computed(() => dash.allStatuses);
const products = computed(() => dash.allProducts);
const pagination = computed(() => dash.allPaginations);

function buildApiFilters() {
    const f = filters.value;
    const apiFilters = { search: f.search, status: f.status };

    if (f.categories.length) {
        const ids = new Set(f.categories);
        f.categories.forEach(catId => {
            categories.value.forEach(c => {
                if (c.parent_id === catId) ids.add(c.id);
            })
        })
        apiFilters.categories = [...ids];
    }

    return apiFilters;
};

function syncRoute() {
    syncingUrl = true;
    const query = {};
    if (page.value > 1) query.page = page.value;
    if (perPage.value !== 15) query.perPage = perPage.value;
    if (filters.value.search) query.search = filters.value.search;
    if (filters.value.status) query.status = filters.value.status;
    if (filters.value.categories.length) query.categories = filters.value.categories.join(',');
    router.replace({ query });
    nextTick(() => { syncingUrl = false; });
};

function load() {
    dash.loadProducts(buildApiFilters(), page.value, perPage.value);
}

const { handleExportStarted } = useExport('products');

onMounted(() => {
    if (window.Echo) {
        window.Echo.channel('products')
            .listen('.product-updated', () => {
                load();
            });
    }
});

watch(() => route.query, (q) => {
    if (syncingUrl) return;
    fromUrl = true;
    filters.value = parseQuery(q);
    page.value = Number(q.page) || 1;
    perPage.value = Number(q.perPage) || 15;
    syncRoute();
    load();
    nextTick(() => { fromUrl = false; });
}, { immediate: true });

const debouncedLoad = debounce(() => {
    page.value = 1;
    syncRoute();
    load();
}, 500); 

watch(filters, () => {
    if (fromUrl) return;
    if (filters.value.search) {
        debouncedLoad();
    } else {
        page.value = 1;
        syncRoute();
        load();
    }
}, { deep: true });

const handlePageChange = (newPage) => {
    page.value = newPage;
    syncRoute();
    load();
};

const handlePerPageChange = (newPerPage) => {
    perPage.value = newPerPage;
    page.value = 1;
    syncRoute();
    load();
};

const handleEditProduct = (product) => {
    router.push({ name: 'edit-product', params: { id: product.id } });
};

const handleEditProductPrice = (product) => {
    router.push({ name: 'product-price', query: { product_id: product.id } });
};

const viewProduct = ref(null);

const handleViewProduct = (product) => {
    viewProduct.value = product;
};

const deleteImage = async (imageId) => {
    if (!confirm('Delete this image?')) return;
    try {
        const res = await axios.post(`/api/products/image/delete/${imageId}`);
        if (res.data.status === 'success') {
            viewProduct.value.images = viewProduct.value.images.filter(img => img.id !== imageId);
        }
    } catch {
        alert('Failed to delete image');
    }
};

const handleDeleteProduct = async (product) => {
    if (!confirm(`Are you sure you want to delete product "${product.name}"?`)) return;
    const result = await dash.deleteProduct(product.id);
    if (result.status === 'success') {
        load();
    }
};
</script>

<style lang="scss" scoped>
.dropdown-item:active,
.dropdown-item:focus {
    background-color: #6c757d;
}
</style>

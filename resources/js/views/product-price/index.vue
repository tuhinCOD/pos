<template>
    <div class="container-fluid">
        <div class="row my-3">
            <div v-if="auth.user?.role?.name !== 'super admin'" class="col-6">
                <div class="card card-body">
                    <Form
                        ref="formRef"
                        :products="products"
                        :units="units"
                        :product-prices="productPrices"
                        :errors="formErrors"
                        :initial-product-id="initialProductId"
                        :edit-price="editPrice"
                        @reset-form="handleReset"
                        @update-product-price="handleUpdateProductPrice"
                    />
                </div>
            </div>
            <div :class="auth.user?.role?.name === 'super admin' ? 'col-12' : 'col-6'">
                <div class="card card-body">
                    <Table
                        :product-prices="productPrices"
                        :pagination="pagination"
                        :per-page="perPage"
                        :page="page"
                        :search="searchTerm"
                        :exportEndpoint="'product_prices'"
                        :exportFilters="{ search: searchTerm }"
                        @search="handleSearchInput"
                        @search-enter="handleSearchEnter"
                        @search-reset="handleSearchReset"
                        @edit-price="handleEditPrice"
                        @delete-price="handleDeletePrice"
                        @page-change="handlePageChange"
                        @per-page-change="handlePerPageChange"
                        @reload="load"
                        @export-started="handleExportStarted"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
    import { computed, nextTick, onMounted, reactive, ref, watch } from "vue";
    import { useRoute, useRouter } from "vue-router";
    import { useAuth } from '../../stores/auth';
    import { useProductPrice } from "../../stores/productPrice";
    import { useExport } from '../../composables/useExport';
    import Form from "./Form.vue";
    import Table from "./Table.vue";

    const { handleExportStarted } = useExport('product_prices');

    const auth = useAuth();

    const dash = useProductPrice();
    const router = useRouter();
    const route = useRoute();

    const productPrices = computed(() => dash.allProductPrices);
    const products = computed(() => dash.allProducts);
    const units = computed(() => dash.allUnits);
    const pagination = computed(() => dash.allPaginations);

    const formRef = ref(null);
    const editPrice = ref(null);
    const searchTerm = ref('');
    const page = ref(1);
    const perPage = ref(15);
    const initialProductId = ref(null);

    const formErrors = reactive({
        product: '',
        price: '',
        vat: '',
        point: '',
        remarks: '',
    });

    let fromUrl = false;
    let syncingUrl = false;
    let searchTimeout = null;

    function syncRoute() {
        syncingUrl = true;
        const query = {};
        if (page.value > 1) query.page = page.value;
        if (perPage.value !== 15) query.perPage = perPage.value;
        if (searchTerm.value) query.search = searchTerm.value;
        if (initialProductId.value) query.product_id = initialProductId.value;
        router.replace({ query }).then(() => {
            syncingUrl = false;
        });
    }

    function load() {
        dash.loadProductPrices(searchTerm.value ? { search: searchTerm.value } : {}, page.value, perPage.value);
    }

    onMounted(() => {
        if (window.Echo) {
            window.Echo.channel('productPrices')
                .listen('.product-price-updated', () => {
                    load();
                });
        }
    });

    watch(() => route.query, (q) => {
        if (syncingUrl) return;
        fromUrl = true;
        searchTerm.value = q.search || '';
        page.value = Number(q.page) || 1;
        perPage.value = Number(q.perPage) || 15;
        initialProductId.value = q.product_id ? Number(q.product_id) : null;

        load();

        nextTick(() => { fromUrl = false; });
    }, { immediate: true });

    watch(searchTerm, () => {
        if (fromUrl) return;
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            page.value = 1;
            load();
            syncRoute();
        }, 350);
    });

    const handleSearchInput = (val) => {
        searchTerm.value = val;
    };

    const handleUpdateProductPrice = async (formData, priceId) => {
        let result;
        if (priceId) {
            result = await dash.updateProductPrice(priceId, formData);
        } else {
            result = await dash.createProductPrice(formData);
        }

        Object.keys(formErrors).forEach(key => formErrors[key] = '');

        if (result.status === 'error' && result.errors) {
            for (const key in result.errors) {
                if (formErrors[key] !== undefined) {
                    formErrors[key] = result.errors[key];
                }
            }
        }

        if (result.status === 'success') {
            handleReset();
        }
        return result;
    };

    const handleEditPrice = (pp) => {
        editPrice.value = pp;
        initialProductId.value = pp.product_id;
        syncRoute();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const handleDeletePrice = async (id) => {
        if (!confirm('Are you sure you want to delete this price?')) return;
        const result = await dash.deleteProductPrice(id);
        if (result.status === 'error') {
            alert('Failed to delete price');
        }
    };

    const handleReset = () => {
        if (formRef.value && typeof formRef.value.resetForm === 'function') {
            formRef.value.resetForm();
        }
        Object.keys(formErrors).forEach(key => formErrors[key] = '');
        editPrice.value = null;
        initialProductId.value = null;
        syncingUrl = true;
        const query = { ...route.query };
        delete query.edit;
        delete query.product_id;
        router.replace({ query }).then(() => {
            syncingUrl = false;
        });
    };

    const handlePageChange = (newPage) => {
        page.value = newPage;
        load();
        syncRoute();
    };

    const handlePerPageChange = (newPerPage) => {
        perPage.value = newPerPage;
        page.value = 1;
        load();
        syncRoute();
    };

    const handleSearchEnter = () => {
        clearTimeout(searchTimeout);
        page.value = 1;
        load();
        syncRoute();
    };

    const handleSearchReset = () => {
        clearTimeout(searchTimeout);
        searchTerm.value = '';
        page.value = 1;
        load();
        syncRoute();
    };
</script>

<style lang="scss" scoped>
</style>

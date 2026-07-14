<template>
    <div class="container-fluid">
        <div class="my-3">
            <div class="card card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="m-0">All Barcodes</h4>
                    <router-link to="/barcodes/generate" class="btn btn-primary btn-sm" v-if="auth.user?.role?.name !== 'super admin' && auth.user?.role?.name !== 'cashier'">
                        <i class="bi bi-plus-lg"></i> Generate Barcodes
                    </router-link>
                </div>
                <Form
                    :products="products"
                    :branches="branches"
                    :purchases="purchases"
                    @update:filters="filters = $event"/>
                <hr style="margin-left:-1rem; margin-right:-1rem;">
                <Table
                    :barcodes="barcodes"
                    :pagination="pagination"
                    :perPage="perPage"
                    :page="page"
                    :exportEndpoint="'barcodes'"
                    :exportFilters="buildApiFilters()"
                    @edit-barcode="handleEditBarcode"
                    @delete-barcode="handleDeleteBarcode"
                    @page-change="handlePageChange"
                    @per-page-change="handlePerPageChange"
                @reload="load"
                    @export-started="handleExportStarted"
            />
            </div>
        </div>
    </div>
</template>

<script setup>
    import { computed, nextTick, onMounted, ref, watch } from "vue";
    import debounce from "lodash/debounce";
    import { useRoute, useRouter } from "vue-router";
    import { useExport } from '../../composables/useExport';
    import { useBarcode } from "../../stores/barcode";
    import { useAuth } from '../../stores/auth';
    import Table from './Table.vue';
    import Form from './Form.vue';

    const auth = useAuth();

    const dash = useBarcode();
    const router = useRouter();
    const route = useRoute();
    const { handleExportStarted } = useExport('barcodes');

    let fromUrl = false;
    let syncingUrl = false;

    function parseQuery(q) {
        return {
            search: q.search || '',
            product_id: q.product_id || '',
            branch_id: q.branch_id || '',
            purchase_id: q.purchase_id || '',
        };
    }

    const filters = ref(parseQuery(route.query));
    const page = ref(Number(route.query.page) || 1);
    const perPage = ref(Number(route.query.perPage) || 15);

    const barcodes = computed(() => dash.allBarcodes);
    const pagination = computed(() => dash.allPaginations);
    const products = computed(() => dash.allProducts);
    const branches = computed(() => dash.allBranches);
    const purchases = computed(() => dash.allPurchases);

    function buildApiFilters() {
        return { ...filters.value };
    }

    function syncRoute() {
        syncingUrl = true;
        const query = {};
        if (page.value > 1) query.page = page.value;
        if (perPage.value !== 15) query.perPage = perPage.value;
        if (filters.value.search) query.search = filters.value.search;
        if (filters.value.product_id) query.product_id = filters.value.product_id;
        if (filters.value.branch_id) query.branch_id = filters.value.branch_id;
        if (filters.value.purchase_id) query.purchase_id = filters.value.purchase_id;
        router.replace({ query });
        nextTick(() => { syncingUrl = false; });
    }

    function load() {
        dash.loadBarcodes(buildApiFilters(), page.value, perPage.value);
    }

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

    onMounted(() => {
        if (window.Echo) {
            window.Echo.channel('barcodes')
                .listen('.barcode-updated', () => {
                    load();
                });
        }
    });

    const handleEditBarcode = (barcode) => {
        router.push({ name: 'barcode-edit', params: { id: barcode.id } });
    };

    const handleDeleteBarcode = async (barcode) => {
        if (!confirm(`Delete barcode "${barcode.barcode}"?`)) return;
        const result = await dash.deleteBarcode(barcode.id);
        if (result.status === 'success') {
            load();
        }
    };
</script>

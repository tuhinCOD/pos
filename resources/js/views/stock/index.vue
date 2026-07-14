<template>
    <div class="container-fluid">
        <div class="my-3">
            <div class="card card-body">
                <Form
                :products="products"
                :branches="branches"
                :filterValues="filters"
                @update:filters="filters = $event"/>
                <hr style="margin-left:-1rem; margin-right:-1rem;">
                <Table
                :stocks="stocks"
                :pagination="pagination"
                :perPage="perPage"
                :page="page"
                :exportEndpoint="'stock'"
                :exportFilters="buildApiFilters()"
                @page-change="handlePageChange"
                @per-page-change="handlePerPageChange"
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
import { useStock } from "../../stores/stock";
import { useExport } from '../../composables/useExport';
import Table from './Table.vue';
import Form from './Form.vue';

const { handleExportStarted } = useExport('stock');

const dash = useStock();
const router = useRouter();
const route = useRoute();

let fromUrl = false;
let syncingUrl = false;

function parseQuery(q) {
    return {
        search: q.search || '',
        products: q.products
            ? String(q.products).split(',').map(Number).filter(n => !isNaN(n))
            : [],
        branch: q.branch || '',
    };
}

const filters = ref(parseQuery(route.query));
const page = ref(Number(route.query.page) || 1);
const perPage = ref(Number(route.query.perPage) || 20);

const stocks = computed(() => dash.allStocks);
const products = computed(() => dash.allProducts);
const branches = computed(() => dash.allBranches);
const pagination = computed(() => dash.allPaginations);

function buildApiFilters() {
    const f = filters.value;
    const apiFilters = { search: f.search };

    if (f.products.length) apiFilters.products = f.products;
    if (f.branch) apiFilters.branches = [f.branch];

    return apiFilters;
};

function syncRoute() {
    syncingUrl = true;
    const query = {};
    if (page.value > 1) query.page = page.value;
    if (perPage.value !== 15) query.perPage = perPage.value;
    if (filters.value.search) query.search = filters.value.search;
    if (filters.value.products.length) query.products = filters.value.products.join(',');
    if (filters.value.branch) query.branch = filters.value.branch;
    router.replace({ query });
    nextTick(() => { syncingUrl = false; });
};

function load() {
    dash.loadStocks(buildApiFilters(), page.value, perPage.value);
}

onMounted(() => {
    if (window.Echo) {
        window.Echo.channel('stock')
            .listen('.stock-updated', () => {
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
</script>

<style lang="scss" scoped>

</style>

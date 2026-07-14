<template>
    <div class="container-fluid">
        <div class="my-3">
            <div class="card card-body">
                <Form
                :statuses="statuses"
                :products="products"
                :branches="branches"
                :filterValues="filters"
                @update:filters="filters = $event"/>
                <hr style="margin-left:-1rem; margin-right:-1rem;">
                <Table
                :returns="returns"
                :pagination="pagination"
                :perPage="perPage"
                :page="page"
                :exportEndpoint="'client_returns'"
                :exportFilters="buildApiFilters()"
                @export-started="handleExportStarted"
                @edit-return="handleEditReturn"
                @delete-return="handleDeleteReturn"
                @page-change="handlePageChange"
                @per-page-change="handlePerPageChange"
                @reload="load"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from "vue";
import debounce from "lodash/debounce";
import { useRoute, useRouter } from "vue-router";
import { useClientReturn } from "../../stores/clientReturn";
import { useExport } from '../../composables/useExport';
import Table from './Table.vue';
import Form from './Form.vue';

const { handleExportStarted } = useExport('client_returns');

const dash = useClientReturn();
const router = useRouter();
const route = useRoute();

let fromUrl = false;
let syncingUrl = false;

function parseQuery(q) {
    return {
        search: q.search || '',
        products: q.products || '',
        status: q.status || '',
        branch: q.branch || '',
    };
}

const filters = ref(parseQuery(route.query));
const page = ref(Number(route.query.page) || 1);
const perPage = ref(Number(route.query.perPage) || 15);

const statuses = computed(() => dash.allStatuses);
const products = computed(() => dash.allProducts);
const branches = computed(() => dash.allBranches);
const returns = computed(() => dash.allClientReturns);
const pagination = computed(() => dash.allPaginations);

function buildApiFilters() {
    return { ...filters.value };
};

function syncRoute() {
    syncingUrl = true;
    const query = {};
    if (page.value > 1) query.page = page.value;
    if (perPage.value !== 15) query.perPage = perPage.value;
    if (filters.value.search) query.search = filters.value.search;
    if (filters.value.products) query.products = filters.value.products;
    if (filters.value.status) query.status = filters.value.status;
    if (filters.value.branch) query.branch = filters.value.branch;
    router.replace({ query });
    nextTick(() => { syncingUrl = false; });
};

function load() {
    dash.loadClientReturns(buildApiFilters(), page.value, perPage.value);
}

onMounted(() => {
    if (window.Echo) {
        window.Echo.channel('clientReturns')
            .listen('.client-return-updated', () => {
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
}, 1000);

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

const handleEditReturn = (item) => {
    router.push({ name: 'client-return-edit', params: { id: item.id } });
};

const handleDeleteReturn = async (item) => {
    if (!confirm(`Are you sure you want to delete this client return?`)) return;
    const result = await dash.deleteClientReturn(item.id, buildApiFilters(), page.value, perPage.value);
    if (result.status === 'success') {
        load();
    }
};
</script>

<style lang="scss" scoped>

</style>

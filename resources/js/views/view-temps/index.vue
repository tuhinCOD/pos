<template>
    <div class="container-fluid">
        <div class="my-3">
            <div class="card card-body">
                <Form
                :statuses="statuses"
                :branches="branches"
                :products="products"
                :filterValues="filters"
                @update:filters="filters = $event"/>
                <hr style="margin-left:-1rem; margin-right:-1rem;">
                <Table
                :groupedTemps="groupedTemps"
                :pagination="pagination"
                :perPage="perPage"
                :page="page"
                :exportEndpoint="'temps'"
                :exportFilters="buildApiFilters()"
                @edit-temp="handleEditTemp"
                @delete-temp="handleDeleteTemp"
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
import { useTemp } from "../../stores/temp";
import Table from './Table.vue';
import Form from './Form.vue';

const dash = useTemp();
const router = useRouter();
const route = useRoute();
const { handleExportStarted } = useExport('temps');

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
const branches = computed(() => dash.allBranches);
const products = computed(() => dash.allProducts);
const temps = computed(() => dash.allTemps);
const pagination = computed(() => dash.allPaginations);

const groupedTemps = computed(() => {
    const groups = {};
    temps.value.forEach(temp => {
        if (!groups[temp.invoice_no]) {
            groups[temp.invoice_no] = { invoice_no: temp.invoice_no, items: [], remarks: temp.remarks || '' };
        }
        groups[temp.invoice_no].items.push(temp);
    });
    return Object.values(groups);
});

function buildApiFilters() {
    return { ...filters.value };
};

function syncRoute() {
    syncingUrl = true;
    const query = {};
    if (page.value > 1) query.page = page.value;
    if (perPage.value !== 15) query.perPage = perPage.value;
    if (filters.value.search) query.search = filters.value.search;
    if (filters.value.status) query.status = filters.value.status;
    if (filters.value.branch) query.branch = filters.value.branch;
    if (filters.value.products) query.products = filters.value.products;
    router.replace({ query });
    nextTick(() => { syncingUrl = false; });
};

function load() {
    dash.loadTemps(buildApiFilters(), page.value, perPage.value);
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

onMounted(() => {
    if (window.Echo) {
        window.Echo.channel('temps')
            .listen('.temp-updated', () => {
                load();
            });
    }
});

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

const handleEditTemp = (temp) => {
    router.push({ name: 'temp-edit', params: { id: temp.invoice_no } });
};

const handleDeleteTemp = async (group) => {
    if (!confirm(`Are you sure you want to delete invoice "${group.invoice_no}" (${group.items.length} products)?`)) return;
    for (const temp of group.items) {
        await dash.deleteTemp(temp.id);
    }
    load();
};
</script>

<style lang="scss" scoped>

</style>

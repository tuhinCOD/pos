<template>
    <div class="container-fluid">
        <div class="my-3">
            <div class="card card-body">
                <Form
                :statuses="statuses"
                :products="products"
                :filterValues="filters"
                @update:filters="filters = $event"/>
                <hr style="margin-left:-1rem; margin-right:-1rem;">
                <Table
                :groupedOrders="groupedOrders"
                :pagination="pagination"
                :perPage="perPage"
                :page="page"
                :exportEndpoint="'orders'"
                :exportFilters="buildApiFilters()"
                @delete-order="handleDeleteOrder"
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
import { useOrder } from "../../stores/order";
import { useExport } from '../../composables/useExport';
import Table from './Table.vue';
import Form from './Form.vue';

const { handleExportStarted } = useExport('orders');

const dash = useOrder();
const router = useRouter();
const route = useRoute();

let fromUrl = false;
let syncingUrl = false;

function parseQuery(q) {
    return {
        search: q.search || '',
        products: q.products || '',
        status: q.status || '',
    };
}

const filters = ref(parseQuery(route.query));
const page = ref(Number(route.query.page) || 1);
const perPage = ref(Number(route.query.perPage) || 20);

const statuses = computed(() => dash.allStatuses);
const products = computed(() => dash.allProducts);
const orders = computed(() => dash.allOrders);
const pagination = computed(() => dash.allPaginations);

const groupedOrders = computed(() => {
    const groups = {};
    orders.value.forEach(order => {
        if (!groups[order.invoice_no]) {
            groups[order.invoice_no] = { invoice_no: order.invoice_no, items: [] };
        }
        groups[order.invoice_no].items.push(order);
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
    if (perPage.value !== 20) query.perPage = perPage.value;
    if (filters.value.search) query.search = filters.value.search;
    if (filters.value.status) query.status = filters.value.status;
    if (filters.value.products) query.products = filters.value.products;
    router.replace({ query });
    nextTick(() => { syncingUrl = false; });
};

function load() {
    dash.loadOrders(buildApiFilters(), page.value, perPage.value);
}

onMounted(() => {
    if (window.Echo) {
        window.Echo.channel('orders')
            .listen('.order-updated', () => {
                load();
            });
    }
});

watch(() => route.query, (q) => {
    if (syncingUrl) return;
    fromUrl = true;
    filters.value = parseQuery(q);
    page.value = Number(q.page) || 1;
    perPage.value = Number(q.perPage) || 20;
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

const handleDeleteOrder = async (group) => {
    if (!confirm(`Are you sure you want to delete invoice "${group.invoice_no}" (${group.items.length} products)?`)) return;

    let allDeleted = true;
    for (const order of group.items) {
        const result = await dash.deleteOrder(order.id, buildApiFilters(), page.value, perPage.value);
        if (result.status !== 'success') {
            allDeleted = false;
        }
    }
    if (allDeleted) {
        load();
    }
};
</script>

<style lang="scss" scoped>
</style>

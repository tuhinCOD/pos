<template>
    <div class="container-fluid">
        <div class="my-3">
            <div class="card card-body">
                <Form
                :filterValues="filters"
                @update:filters="filters = $event"/>
                <hr style="margin-left:-1rem; margin-right:-1rem;">
                <Table
                :credits="credits"
                :pagination="pagination"
                :perPage="perPage"
                :page="page"
                :exportEndpoint="'credits'"
                :exportFilters="buildApiFilters()"
                @export-started="handleExportStarted"
                @make-payment="handleMakePayment"
                @delete-credit="handleDeleteCredit"
                @page-change="handlePageChange"
                @per-page-change="handlePerPageChange"
                @reload="load"
                />
            </div>
        </div>
    </div>

    <PaymentModal
        v-if="showPaymentModal"
        :invoice-no="selectedCredit?.invoice_no"
        :amount="Number(selectedCredit?.due_amount) || 0"
        :payment-methods="paymentMethods"
        payment-type="purchase"
        @close="showPaymentModal = false"
        @payment-completed="handlePaymentCompleted"
    />
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from "vue";
import debounce from "lodash/debounce";
import { useRoute, useRouter } from "vue-router";
import { usePurchaseCredit } from "../../stores/purchaseCredit";
import { useExport } from '../../composables/useExport';
import Table from './Table.vue';
import Form from './Form.vue';
import PaymentModal from '../../components/PaymentModal.vue';
import axios from 'axios';

const { handleExportStarted } = useExport('credits');

const dash = usePurchaseCredit();
const router = useRouter();
const route = useRoute();

const showPaymentModal = ref(false);
const selectedCredit = ref(null);
const paymentMethods = ref([]);

let fromUrl = false;
let syncingUrl = false;

function parseQuery(q) {
    return {
        search: q.search || '',
    };
}

const filters = ref(parseQuery(route.query));
const page = ref(Number(route.query.page) || 1);
const perPage = ref(Number(route.query.perPage) || 15);

const credits = computed(() => dash.allCredits);
const pagination = computed(() => dash.allPaginations);

function buildApiFilters() {
    return { ...filters.value, credit_type: 'purchase' };
};

function syncRoute() {
    syncingUrl = true;
    const query = {};
    if (page.value > 1) query.page = page.value;
    if (perPage.value !== 15) query.perPage = perPage.value;
    if (filters.value.search) query.search = filters.value.search;
    router.replace({ query });
    nextTick(() => { syncingUrl = false; });
};

function load() {
    dash.loadCredits(buildApiFilters(), page.value, perPage.value);
}

const fetchPaymentMethods = async () => {
    try {
        const res = await axios('/api/v1/paymentmethods');
        paymentMethods.value = res.data?.paymentmethods || res.data?.data || [];
    } catch {}
};

onMounted(() => {
    fetchPaymentMethods();
    if (window.Echo) {
        window.Echo.channel('credits')
            .listen('.credit-updated', () => {
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

const handleMakePayment = (credit) => {
    selectedCredit.value = credit;
    showPaymentModal.value = true;
};

const handlePaymentCompleted = () => {
    showPaymentModal.value = false;
    selectedCredit.value = null;
    load();
};

const handleDeleteCredit = async (credit) => {
    if (!confirm(`Are you sure you want to delete this credit?`)) return;
    const result = await dash.deleteCredit(credit.id, buildApiFilters(), page.value, perPage.value);
    if (result.status === 'success') {
        load();
    }
};
</script>

<style lang="scss" scoped>

</style>
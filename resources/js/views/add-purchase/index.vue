<template>
    <div class="container-fluid">
        <div class="row my-3">
            <div class="col-12">
                <div class="card card-body">
                    <div v-if="alertMsg" class="alert alert-danger alert-dismissible fade show py-2" role="alert">
                        {{ alertMsg }}
                        <button type="button" class="btn-close py-2" @click="alertMsg = ''"></button>
                    </div>
                    <Form
                        ref="formRef"
                        :statuses="statuses"
                        :branches="branches"
                        :products="products"
                        :suppliers="suppliers"
                        :units="units"
                        :payment-methods="paymentMethods"
                        :errors="formErrors"
                        :pending-purchases="pendingPurchases"
                        :loading-pending="loadingPending"
                        @reset-form="handleReset"
                        @refresh-pending="loadPendingPurchases"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
    import { computed, nextTick, onMounted, reactive, ref } from "vue";
    import { useRoute, useRouter } from "vue-router";
    import { usePurchase } from "../../stores/purchase";
    import Form from "./Form.vue";
    import axios from "axios";

    const dash = usePurchase();
    const router = useRouter();
    const route = useRoute();

    const statuses = computed(() => dash.allStatuses);
    const branches = computed(() => dash.allBranches);
    const products = computed(() => dash.allProducts);
    const suppliers = computed(() => dash.allSuppliers);
    const units = computed(() => dash.allUnits);

    const formRef = ref(null);
    const alertMsg = ref('');
    const pendingPurchases = ref([]);
    const loadingPending = ref(false);
    const paymentMethods = ref([]);

    const formErrors = reactive({
        invoice_no: '',
        status: '',
        supplier: '',
        branch: '',
        product: '',
        unit: '',
        qty: '',
        product_unit_id: '',
        product_unit_qty: '',
        price: '',
        vat: '',
        discount: '',
        remarks: '',
        attributes: '',
    });

    async function load() {
        await dash.loadPurchases({}, 1, 15);
        if (route.params.id) {
            const invoiceNo = route.params.id;
            const result = await dash.getPurchasesByInvoice(invoiceNo);
            if (result.status === 'success' && result.data.length) {
                await nextTick();
                if (formRef.value) {
                    formRef.value.setPendingData(invoiceNo, result.data);
                }
            }
        }
    }

    const loadPendingPurchases = async () => {
        loadingPending.value = true;
        try {
            const pendingStatus = statuses.value.find(s => s.name === 'pending');
            if (pendingStatus) {
                const res = await axios(`/api/purchases?status=${pendingStatus.id}&perPage=100`);
                const purchases = res.data?.purchases?.data || [];
                const grouped = {};
                purchases.forEach(p => {
                    if (!grouped[p.invoice_no]) {
                        grouped[p.invoice_no] = { invoice_no: p.invoice_no, items: [], supplier: p.supplier, branch: p.branch, created_at: p.created_at };
                    }
                    grouped[p.invoice_no].items.push(p);
                });
                pendingPurchases.value = Object.values(grouped).sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
            }
        } catch (e) {
            console.error(e);
        }
        loadingPending.value = false;
    };

    onMounted(async () => {
        await load();
        await fetchPaymentMethods();
        await loadPendingPurchases();
        if (window.Echo) {
            window.Echo.channel('purchases')
                .listen('.purchase-updated', () => {
                    load();
                    dash.loadPurchases();
                    loadPendingPurchases();
                });
        }
    });

    const fetchPaymentMethods = async () => {
        try {
            const res = await axios('/api/v1/paymentmethods');
            paymentMethods.value = res.data?.paymentmethods || res.data?.data || [];
        } catch {}
    };

    const handleReset = () => {
        if (formRef.value && typeof formRef.value.resetForm === 'function') {
            formRef.value.resetForm();
        }
        Object.keys(formErrors).forEach(key => formErrors[key] = '');
        if (route.params.id) {
            router.push({ name: 'purchase-create' });
        }
    };
</script>

<style lang="scss" scoped>
</style>
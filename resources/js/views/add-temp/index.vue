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
                        :product-prices="productPrices"
                        :clients="clients"
                        :units="units"
                        :barcodes="barcodes"
                        :payment-methods="paymentMethods"
                        :errors="formErrors"
                        :edit-temp="editTemp"
                        :pending-temps="pendingTemps"
                        :loading-pending="loadingPending"
                        @reset-form="handleReset"
                        @update-temp="handleUpdateTemp"
                        @refresh-pending="loadPendingTemps"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
    import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from "vue";
    import { useRoute, useRouter } from "vue-router";
    import { useTemp } from "../../stores/temp";
    import Form from "./Form.vue";
    import axios from "axios";

    const dash = useTemp();
    const router = useRouter();
    const route = useRoute();

    const statuses = computed(() => dash.allStatuses);
    const branches = computed(() => dash.allBranches);
    const products = computed(() => dash.allProducts);
    const productPrices = computed(() => dash.allProductPrices);
    const clients = computed(() => dash.allClients);
    const units = computed(() => dash.allUnits);
    const barcodes = computed(() => dash.allBarcodes);

    const formRef = ref(null);
    const editTempData = ref(null);
    const alertMsg = ref('');
    const pendingTemps = ref([]);
    const loadingPending = ref(false);
    const paymentMethods = ref([]);

    const editTemp = computed(() => editTempData.value || dash.allEditTemp);

    const formErrors = reactive({
        invoice_no: '',
        status: '',
        branch: '',
        client: '',
        product: '',
        product_price: '',
        unit: '',
        qty: '',
        price: '',
        vat: '',
        discount: '',
        point: '',
        remarks: '',
    });

    async function load() {
        await dash.loadTemps({}, 1, 15);
        if (route.params.id) {
            const result = await dash.getTempsByInvoice(route.params.id);
            if (result.status === 'success' && result.data.length) {
                await nextTick();
                if (formRef.value) {
                    formRef.value.setPendingData(route.params.id, result.data);
                }
            }
        }
    }

    const loadPendingTemps = async () => {
        loadingPending.value = true;
        try {
            const pendingStatus = statuses.value.find(s => s.name === 'pending');
            if (pendingStatus) {
                const res = await axios(`/api/temps/grouped?status=${pendingStatus.id}&perPage=100`);
                pendingTemps.value = res.data?.temps || [];
            }
        } catch (e) {
            console.error(e);
        }
        loadingPending.value = false;
    };

    let tempChannel = null;

    onMounted(async () => {
        await load();
        await fetchPaymentMethods();
        await loadPendingTemps();
        if (window.Echo) {
            tempChannel = window.Echo.channel('temps')
                .listen('.temp-updated', () => {
                    load();
                    dash.loadTemps();
                    loadPendingTemps();
                });
        }
    });

    onBeforeUnmount(() => {
        if (tempChannel) {
            tempChannel.stopListening('.temp-updated');
            window.Echo.leave('temps');
            tempChannel = null;
        }
    });

    const fetchPaymentMethods = async () => {
        try {
            const res = await axios('/api/v1/paymentmethods');
            paymentMethods.value = res.data?.paymentmethods || res.data?.data || [];
        } catch {}
    };

    const handleUpdateTemp = async (formData) => {
        Object.keys(formErrors).forEach(key => formErrors[key] = '');
        alertMsg.value = '';
        let result;
        if (editTemp.value) {
            result = await dash.updateTemp(editTemp.value.id, formData);
        } else {
            result = await dash.createTemp(formData);
        }

        if (result.status === 'error' && result.errors) {
            let msgs = [];
            for (const key in result.errors) {
                if (formErrors[key] !== undefined) {
                    formErrors[key] = result.errors[key];
                    const m = Array.isArray(result.errors[key]) ? result.errors[key][0] : result.errors[key];
                    if (m && !m.toLowerCase().includes('required') && !m.toLowerCase().includes('numeric') && !m.toLowerCase().includes('greater than')) msgs.push(m);
                }
            }
            alertMsg.value = msgs.join(', ');
        }

        if (result.status === 'success') {
            handleReset();
            await dash.loadTodayTemps();
            await loadPendingTemps();
        }
        return result;
    };

    const handleReset = () => {
        if (formRef.value && typeof formRef.value.resetForm === 'function') {
            formRef.value.resetForm();
        }
        editTempData.value = null;
        Object.keys(formErrors).forEach(key => formErrors[key] = '');
        if (route.params.id) {
            router.push({ name: 'temp-create' });
        }
    };
</script>

<style lang="scss" scoped>
</style>

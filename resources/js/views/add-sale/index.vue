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
                        :edit-sale="editSale"
                        @reset-form="handleReset"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
    import { computed, nextTick, onMounted, reactive, ref } from "vue";
    import { useRoute, useRouter } from "vue-router";
    import { useSale } from "../../stores/sale";
    import Form from "./Form.vue";
    import axios from "axios";

    const dash = useSale();
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
    const editSaleData = ref(null);
    const alertMsg = ref('');
    const paymentMethods = ref([]);

    const editSale = computed(() => editSaleData.value || dash.allEditSale);

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
        await dash.loadSales({}, 1, 15);
        if (route.params.id) {
            const id = route.params.id;
            if (isNaN(Number(id))) {
                const result = await dash.getSalesByInvoice(id);
                if (result.status === 'success' && result.data.length) {
                    await nextTick();
                    if (formRef.value) {
                        formRef.value.loadCartFromSales(result.data);
                    }
                }
            } else {
                const result = await dash.getSale(id);
                if (result.status === 'success') {
                    editSaleData.value = result.data.sale;
                    await nextTick();
                    if (formRef.value && result.data.sale) {
                        formRef.value.loadCartFromSales([result.data.sale]);
                    }
                }
            }
        }
    }

    onMounted(async () => {
        await load();
        await fetchPaymentMethods();
        if (window.Echo) {
            window.Echo.channel('sales')
                .listen('.sale-updated', () => {
                    load();
                    dash.loadSales();
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
        editSaleData.value = null;
        Object.keys(formErrors).forEach(key => formErrors[key] = '');
        if (route.params.id) {
            router.push({ name: 'sales' });
        }
    };
</script>

<style lang="scss" scoped>
</style>

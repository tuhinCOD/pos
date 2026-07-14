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
                        :products="products"
                        :product-prices="productPrices"
                        :clients="clients"
                        :units="units"
                        :errors="formErrors"
                        :edit-order="editOrder"
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
    import { useOrder } from "../../stores/order";
    import Form from "./Form.vue";
    import axios from "axios";

    const dash = useOrder();
    const router = useRouter();
    const route = useRoute();

    const statuses = computed(() => dash.allStatuses);
    const products = computed(() => dash.allProducts);
    const productPrices = computed(() => dash.allProductPrices);
    const clients = computed(() => []); // clients not loaded by default in orders
    const units = computed(() => dash.allUnits);

    const formRef = ref(null);
    const editOrderData = ref(null);
    const alertMsg = ref('');

    const editOrder = computed(() => editOrderData.value);

    const formErrors = reactive({
        invoice_no: '',
        status: '',
        product: '',
        product_price: '',
        unit: '',
        qty: '',
        price: '',
        vat: '',
        discount: '',
        shipping_fee: '',
    });

    async function load() {
        await dash.loadOrders({}, 1, 20);
        if (route.params.id) {
            const id = route.params.id;
            if (isNaN(Number(id))) {
                try {
                    const res = await axios.get(`/api/orders/by-invoice/${id}`);
                    if (res.data?.orders?.length) {
                        await nextTick();
                        if (formRef.value) {
                            formRef.value.loadCartFromOrders(res.data.orders);
                        }
                    }
                } catch (e) {
                    alertMsg.value = 'Failed to load orders';
                }
            }
        }
    }

    onMounted(async () => {
        await load();
        if (window.Echo) {
            window.Echo.channel('orders')
                .listen('.order-updated', () => {
                    load();
                    dash.loadOrders();
                });
        }
    });

    const handleReset = () => {
        if (formRef.value && typeof formRef.value.resetForm === 'function') {
            formRef.value.resetForm();
        }
        editOrderData.value = null;
        Object.keys(formErrors).forEach(key => formErrors[key] = '');
        if (route.params.id) {
            router.push({ name: 'e-commerce-orders' });
        }
    };
</script>

<style lang="scss" scoped>
</style>

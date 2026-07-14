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
                    :branches="branches"
                    :units="units"
                    :sales="sales"
                    :errors="formErrors"
                    :edit-return="editReturn"
                    @reset-form="handleReset"
                    @update-return="handleUpdateReturn"
                />
            </div>
            </div>
        </div>
    </div>
</template>

<script setup>
    import { computed, onMounted, reactive, ref } from "vue";
    import { useRoute, useRouter } from "vue-router";
    import { useClientReturn } from "../../stores/clientReturn";
    import Form from "./Form.vue";

    const dash = useClientReturn();
    const router = useRouter();
    const route = useRoute();

    const statuses = computed(() => dash.allStatuses);
    const products = computed(() => dash.allProducts);
    const branches = computed(() => dash.allBranches);
    const units = computed(() => dash.allUnits);
    const sales = computed(() => dash.allSales);

    const formRef = ref(null);
    const editReturnData = ref(null);
    const alertMsg = ref('');

    const editReturn = computed(() => editReturnData.value || dash.allEditClientReturn);

    const formErrors = reactive({
        product: '',
        sale: '',
        status: '',
        unit: '',
        product_unit_id: '',
        branch: '',
        qty: '',
        product_unit_qty: '',
        remarks: '',
    });

    async function load() {
        await dash.loadClientReturns({}, 1, 20);
        if (route.params.id) {
            const result = await dash.getClientReturn(route.params.id);
            if (result.status === 'success') {
                editReturnData.value = result.data.clientReturn;
            }
        }
    }

    onMounted(() => {
        load();
    });

    onMounted(() => {
        if (window.Echo) {
            window.Echo.channel('clientReturns')
                .listen('.client-return-updated', () => {
                    load();
                });
        }
    });

    const handleUpdateReturn = async (formData) => {
        Object.keys(formErrors).forEach(key => formErrors[key] = '');
        let result;
        if (editReturn.value) {
            result = await dash.updateClientReturn(editReturn.value.id, formData);
        } else {
            result = await dash.createClientReturn(formData);
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
        }
        return result;
    };

    const handleReset = () => {
        if (formRef.value && typeof formRef.value.resetForm === 'function') {
            formRef.value.resetForm();
        }
        Object.keys(formErrors).forEach(key => formErrors[key] = '');
        alertMsg.value = '';
        editReturnData.value = null;
        if (route.params.id) {
            router.push({ name: 'client-return-create' });
        }
    };
</script>

<style lang="scss" scoped>
</style>

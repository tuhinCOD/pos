<template>
    <div class="container-fluid">
        <div class="row my-3">
            <div class="col-12">
                <div class="card card-body">
                    <Form
                        ref="formRef"
                        :errors="formErrors"
                        :edit-credit="editCredit"
                        @reset-form="handleReset"
                        @update-credit="handleUpdateCredit"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
    import { computed, onMounted, reactive, ref } from "vue";
    import { useRoute, useRouter } from "vue-router";
    import { useClientCredit } from "../../stores/clientCredit";
    import Form from "./Form.vue";

    const dash = useClientCredit();
    const router = useRouter();
    const route = useRoute();

    const formRef = ref(null);
    const editCreditData = ref(null);

    const editCredit = computed(() => editCreditData.value);

    const formErrors = reactive({
        paid_amount: '',
    });

    async function load() {
        if (route.params.id) {
            const result = await dash.getCredit(route.params.id);
            if (result.status === 'success') {
                const credit = result.data.credit;
                if (result.data.sale) {
                    credit.sale = result.data.sale;
                }
                editCreditData.value = credit;
            }
        }
    }

    onMounted(() => {
        load();
    });

    onMounted(() => {
        if (window.Echo) {
            window.Echo.channel('credits')
                .listen('.credit-updated', () => {
                    load();
                });
        }
    });

    const handleUpdateCredit = async (formData) => {
        Object.keys(formErrors).forEach(key => formErrors[key] = '');
        const result = await dash.updateCredit(route.params.id, formData);

        if (result.status === 'error' && result.errors) {
            for (const key in result.errors) {
                if (formErrors[key] !== undefined) {
                    formErrors[key] = result.errors[key];
                }
            }
        }

        if (result.status === 'success') {
            editCreditData.value = result.data.credit || editCreditData.value;
            alert('Credit updated successfully');
        }
        return result;
    };

    const handleReset = () => {
        if (formRef.value && typeof formRef.value.resetForm === 'function') {
            formRef.value.resetForm();
        }
        Object.keys(formErrors).forEach(key => formErrors[key] = '');
    };
</script>

<style lang="scss" scoped>
</style>

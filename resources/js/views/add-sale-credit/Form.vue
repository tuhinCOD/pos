<template>
    <form id="credit-form" @submit.prevent="updateForm">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="m-0">Edit Sale Credit</h4>
            <router-link to="/client-credits" class="btn btn-primary btn-sm">
                <i class="bi bi-eye"></i> View Credits
            </router-link>
        </div>
        <div class="my-3">
            <div class="row g-2">
                <div class="col-4">
                    <Input label="Invoice No" id="invoice_no" type="text" name="invoice_no" v-model="field.invoice_no" placeholder="Invoice No" disabled/>
                </div>
                <div class="col-4">
                    <Input label="Product" v-model="field.product_name" class="form-control" :value="field.product_name" disabled/>
                </div>
                <div class="col-4">
                    <Input label="Branch" v-model="field.branch_name" class="form-control" :value="field.branch_name" disabled/>
                </div>
                <div class="col-4">
                    <Input v-model="field.client_name" label="Client" class="form-control" :value="field.client_name" disabled/>
                </div>
                <div class="col-4">
                    <Input label="Total Amount" id="total_amount" type="number" step="0.01" name="total_amount" v-model="field.total_amount" placeholder="0.00" disabled/>
                </div>
                <div class="col-4">
                    <Input label="Paid Amount" id="paid_amount" type="number" step="0.01" errid="paid_amountHelp" name="paid_amount" v-model="field.paid_amount" placeholder="0.00" :err="errors.paid_amount?.[0]" must/>
                </div>
                <div class="col-4">
                    <Input label="Due Amount" id="due_amount" class="form-control" type="number" step="0.01" :value="computedDue" disabled/>
                </div>
            </div>
        </div>
        <div class="row g-2 my-3">
            <div class="col-6">
                <Button label="Update" id="submitBtn" type="submit" icon="bi bi-save-fill"/>
            </div>
            <div class="col-6">
                <Button label="Reset" type="button" id="resetBtn" color="secondary" icon="bi bi-x-square-fill" @click="onReset"/>
            </div>
        </div>
    </form>
</template>

<script setup>
    import Input from '../../components/Input.vue';
    import Button from '../../components/Button.vue';
    import { computed, reactive, watch } from 'vue';

    const props = defineProps({
        errors: { type: Object, default: () => ({}) },
        editCredit: { type: Object, default: null },
    });

    const emit = defineEmits([
        "reset-form",
        "update-credit",
    ]);

    const field = reactive({
        invoice_no: '',
        product_name: '',
        branch_name: '',
        client_name: '',
        total_amount: '',
        paid_amount: '',
    });

    const computedDue = computed(() => {
        const total = parseFloat(field.total_amount) || 0;
        const paid = parseFloat(field.paid_amount) || 0;
        return (total - paid).toFixed(2);
    });

    watch(() => props.editCredit, (val) => {
        if (val) {
            field.invoice_no = val.sale?.invoice_no || '';
            field.product_name = val.sale?.product?.name || '';
            field.branch_name = val.sale?.branch?.name || '';
            field.client_name = val.sale?.client?.name || val.sale?.client?.contact ||  '';
            field.total_amount = val.total_amount || '';
            field.paid_amount = val.paid_amount || '';
        }
    }, { immediate: true });

    const updateForm = () => {
        const formData = new FormData();
        formData.append('paid_amount', field.paid_amount);
        emit('update-credit', formData);
    };

    const resetForm = () => {
        if (props.editCredit) {
            field.invoice_no = props.editCredit.sale?.invoice_no || '';
            field.product_name = props.editCredit.sale?.product?.name || '';
            field.branch_name = props.editCredit.sale?.branch?.name || '';
            field.client_name = props.editCredit.sale?.client?.name || props.editCredit.sale?.client?.contact || '';
            field.total_amount = props.editCredit.total_amount || '';
            field.paid_amount = props.editCredit.paid_amount || '';
        }
    };

    const onReset = () => {
        resetForm();
        emit("reset-form");
    };

    defineExpose({
        resetForm,
        field,
    });
</script>

<style lang="scss" scoped>
</style>

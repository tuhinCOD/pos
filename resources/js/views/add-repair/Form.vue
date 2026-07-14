<template>
    <form id="repair-form" @submit.prevent="updateForm">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="m-0">Edit Repair</h4>
            <router-link to="/repairs" class="btn btn-primary btn-sm">
                <i class="bi bi-eye"></i> View Repairs
            </router-link>
        </div>
        <div class="my-3">
            <div class="row g-2">
                <div class="col-4">
                    <Input label="Damage" id="damage_display" type="text" name="damage_display" :value="damageDisplay" disabled/>
                </div>
                <div class="col-4">
                    <Input label="Product" id="product_display" type="text" name="product_display" :value="productDisplay" disabled/>
                </div>
                <div class="col-4">
                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                    <select class="form-select" v-model="field.status" id="status" name="status">
                        <option value="">Select Status</option>
                        <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <div id="statusHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.status?.[0] }}</div>
                </div>
                <div class="col-4">
                    <Input label="Qty" id="qty_display" type="text" name="qty_display" :value="String(field.qty || '-')" disabled/>
                </div>
                <div class="col-4">
                    <Input label="Unit" id="unit_display" type="text" name="unit_display" :value="unitDisplay" disabled/>
                </div>
                <div class="col-4">
                    <Input label="Branch" id="branch_display" type="text" name="branch_display" :value="branchDisplay" disabled/>
                </div>
                <div class="col-4">
                    <Input label="Repair Shop" id="repair_shop" type="text" errid="repairShopHelp" name="repair_shop" v-model="field.repair_shop" placeholder="Shop name" :err="errors.repair_shop?.[0]" must/>
                </div>
                <div class="col-4">
                    <Input label="Repair Cost" id="repair_cost" type="number" step="0.01" errid="repairCostHelp" name="repair_cost" v-model="field.repair_cost" placeholder="0" :err="errors.repair_cost?.[0]" must/>
                </div>
                <div class="col-4" style="margin-top: 14px;">
                    <label for="remarks" class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control" id="remarks" placeholder="Write remarks here..." v-model="field.remarks"></textarea>
                    <div id="remarksHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.remarks?.[0] }}</div>
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
        statuses: { type: Array, default: () => [] },
        errors: { type: Object, default: () => ({}) },
        editRepair: { type: Object, default: null },
    });

    const emit = defineEmits([
        "reset-form",
        "update-repair",
    ]);

    const field = reactive({
        damage: '',
        product: '',
        status: '',
        unit: '',
        branch: '',
        repair_shop: '',
        qty: '',
        repair_cost: '',
        remarks: '',
    });

    const damageDisplay = computed(() => {
        const d = props.editRepair;
        if (!d) return '-';
        if (d.damage) return `#${d.damage.id} - ${d.damage.product?.name || 'N/A'}`;
        return d.damage_id ? `#${d.damage_id}` : '-';
    });

    const productDisplay = computed(() => {
        const d = props.editRepair;
        if (!d) return '-';
        if (d.product) return `${d.product.name} (${d.product.barcode})`;
        return d.product_id ? `ID: ${d.product_id}` : '-';
    });

    const unitDisplay = computed(() => {
        return props.editRepair?.unit?.name || '-';
    });

    const branchDisplay = computed(() => {
        return props.editRepair?.branch?.name || '-';
    });

    const updateForm = () => {
        const formData = new FormData();
        for (const key in field) {
            formData.append(key, field[key]);
        }
        emit('update-repair', formData);
    };

    watch(() => props.editRepair, (val) => {
        if (val) {
            field.damage = val.damage_id || '';
            field.product = val.product_id || val.product?.id || '';
            field.status = val.status_id || '';
            field.unit = val.unit_id || '';
            field.branch = val.branch_id || '';
            field.repair_shop = val.repair_shop || '';
            field.qty = val.qty || '';
            field.repair_cost = val.repair_cost || '';
            field.remarks = val.remarks || '';
        }
    }, { immediate: true });

    const resetForm = () => {
        if (props.editRepair) {
            field.damage = props.editRepair.damage_id || '';
            field.product = props.editRepair.product_id || props.editRepair.product?.id || '';
            field.status = props.editRepair.status_id || '';
            field.unit = props.editRepair.unit_id || '';
            field.branch = props.editRepair.branch_id || '';
            field.repair_shop = props.editRepair.repair_shop || '';
            field.qty = props.editRepair.qty || '';
            field.repair_cost = props.editRepair.repair_cost || '';
            field.remarks = props.editRepair.remarks || '';
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

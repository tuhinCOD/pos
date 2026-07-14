<template>
    <form id="supplier-form" @submit.prevent="updateForm">
        <h3>Supplier Form</h3>
        <div class="my-3">
            <div class="row g-2">
                <div class="col-6">
                    <Input label="Name" id="name" type="text" errid="nameHelp" name="name" v-model="field.name" placeholder="Name" :err="errors.name?.[0]" must/>
                </div>
                <div class="col-6">
                    <Input label="Contact" id="contact" type="text" errid="contactHelp" name="contact" v-model="field.contact" placeholder="Contact" :err="errors.contact?.[0]" must/>
                </div>
                <div class="col-6">
                    <Input label="Email" id="email" type="email" errid="emailHelp" name="email" v-model="field.email" placeholder="Email" :err="errors.email?.[0]"/>
                </div>
                <div class="col-5 mt-3">
                    <label for="city" class="form-label">City</label>
                    <select class="form-select city" v-model="field.city" id="city" name="city">
                        <option value="">Select City</option>
                        <option v-for="city in cities" :key="city.id" :value="city.id">{{ city.name }}</option>
                    </select>
                    <div id="cityHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.city?.[0] }}</div>
                </div>
                <div class="col-10">
                    <label for="address" class="form-label">Address</label>
                    <textarea name="address" class="form-control" id="address" placeholder="Write address here..." v-model="field.address"></textarea>
                    <div id="addressHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.address?.[0] }}</div>
                </div>
                <div class="col-10">
                    <label for="remarks" class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control" id="remarks" placeholder="Write remarks here..." v-model="field.remarks"></textarea>
                    <div id="remarksHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.remarks?.[0] }}</div>
                </div>
            </div>
        </div>
        <div class="row g-2 my-3">
            <div class="col-6">
                <Button :label="editSupplier ? 'Update' : 'Submit'" id="submitBtn" type="submit" icon="bi bi-save-fill"/>
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
    import { reactive, ref, watch } from 'vue';

    const props = defineProps({
        cities: { type: Array, default: () => [] },
        errors: { type: Object, default: () => ({}) },
        editSupplier: { type: Object, default: null },
    });

    const emit = defineEmits([
        "reset-form",
        "update-supplier",
    ]);

    const field = reactive({
        name: '',
        contact: '',
        email: '',
        city: '',
        address: '',
        remarks: '',
    });

    const isEditing = ref(false);

    const updateForm = () => {
        const formData = new FormData();

        for (const key in field) {
            formData.append(key, field[key]);
        }

        emit('update-supplier', formData);
    };

    watch(() => props.editSupplier, (val) => {
        if (val) {
            isEditing.value = true;
            field.name = val.name || '';
            field.contact = val.contact || '';
            field.email = val.email || '';
            field.city = val.city_id || '';
            field.address = val.address || '';
            field.remarks = val.remarks || '';
        } else {
            isEditing.value = false;
        }
    }, { immediate: true });

    const resetForm = () => {
        field.name = '';
        field.contact = '';
        field.email = '';
        field.city = '';
        field.address = '';
        field.remarks = '';
        isEditing.value = false;
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

<template>
    <form id="branch-form" @submit.prevent="updateForm">
        <h3>Branch Form</h3>
        <div class="my-3">
            <div class="row g-2">
                <div class="col-6">
                    <Input label="Name" id="name" type="text" errid="nameHelp" name="name" v-model="field.name" placeholder="Name" :err="errors.name?.[0]" must/>
                </div>
                <div class="col-6">
                    <Input label="Contact" id="contact" type="text" errid="contactHelp" name="contact" v-model="field.contact" placeholder="Contact" :err="errors.contact?.[0]" must/>
                </div>
                <div class="col-6">
                    <Input label="Address" id="address" type="text" errid="addressHelp" name="address" v-model="field.address" placeholder="Address" :err="errors.address?.[0]" must/>
                </div>
                <div class="col-5 mt-3">
                    <label for="city" class="form-label">City</label>
                    <select class="form-control city" v-model="field.city" id="city" name="city">
                        <option value="">Select City</option>
                        <option v-for="city in cities" :key="city.id" :value="city.id">{{ city.name }}</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="row g-2 my-3">
            <div class="col-6">
                <Button :label="editBranch ? 'Update' : 'Submit'" id="submitBtn" type="submit" icon="bi bi-save-fill"/>
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
        editBranch: { type: Object, default: null },
    });

    const emit = defineEmits([
        "reset-form",
        "update-branch",
    ]);

    const field = reactive({
        name: '',
        contact: '',
        address: '',
        city: '',
    });

    const isEditing = ref(false);

    const updateForm = () => {
        const formData = new FormData();

        for (const key in field) {
            formData.append(key, field[key]);
        }

        emit('update-branch', formData);
    };

    watch(() => props.editBranch, (val) => {
        if (val) {
            isEditing.value = true;
            field.name = val.name || '';
            field.contact = val.contact || '';
            field.address = val.address || '';
            field.city = val.city_id || '';
        } else {
            isEditing.value = false;
        }
    }, { immediate: true });

    const resetForm = () => {
        field.name = '';
        field.contact = '';
        field.address = '';
        field.city = '';
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

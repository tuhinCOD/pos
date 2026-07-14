<template>
    <form id="unit-form" @submit.prevent="updateForm">
        <h3>Unit Form</h3>
        <div class="my-3">
            <div class="row g-2">
                <div class="col-12">
                    <Input label="Name" id="name" type="text" errid="nameHelp" name="name" v-model="field.name" placeholder="Name" :err="errors.name?.[0]" must/>
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" v-model="field.description" placeholder="Description"></textarea>
                </div>
            </div>
        </div>
        <div class="row g-2 my-3">
            <div class="col-6">
                <Button :label="editUnit ? 'Update' : 'Submit'" id="submitBtn" type="submit" icon="bi bi-save-fill"/>
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
        errors: { type: Object, default: () => ({}) },
        editUnit: { type: Object, default: null },
    });

    const emit = defineEmits([
        "reset-form",
        "update-unit",
    ]);

    const field = reactive({
        name: '',
        description: '',
    });

    const isEditing = ref(false);

    const updateForm = () => {
        const formData = new FormData();

        for (const key in field) {
            formData.append(key, field[key]);
        }

        emit('update-unit', formData);
    };

    watch(() => props.editUnit, (val) => {
        if (val) {
            isEditing.value = true;
            field.name = val.name || '';
            field.description = val.description || '';
        } else {
            isEditing.value = false;
        }
    }, { immediate: true });

    const resetForm = () => {
        field.name = '';
        field.description = '';
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

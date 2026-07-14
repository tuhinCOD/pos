<template>
    <form id="category-form" @submit.prevent="updateForm">
        <h3>Category Form</h3>
        <div class="my-3">
            <div class="row g-2">
                <div class="col-6">
                    <Input label="Name" id="name" type="text" errid="nameHelp" name="name" v-model="field.name" placeholder="Name" :err="errors.name?.[0]" must/>
                </div>
                <div class="col-5 mt-3">
                    <label for="parent" class="form-label">Parent Category</label>
                    <select class="form-control parent" v-model="field.parent" id="parent" name="parent">
                        <option value="">Select Parent Category</option>
                        <option v-for="parent in categories" :key="parent.id" :value="parent.id">{{ parent.name }}</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="row g-2 my-3">
            <div class="col-6">
                <Button :label="editCategory ? 'Update' : 'Submit'" id="submitBtn" type="submit" icon="bi bi-save-fill"/>
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
        categories: { type: Array, default: () => [] },
        errors: { type: Object, default: () => ({}) },
        editCategory: { type: Object, default: null },
    });

    const emit = defineEmits([
        "reset-form",
        "update-category",
    ]);

    const field = reactive({
        name: '',
        parent: '',
    });

    const isEditing = ref(false);

    const updateForm = () => {
        const formData = new FormData();

        for (const key in field) {
            formData.append(key, field[key]);
        }

        emit('update-category', formData);
    };

    watch(() => props.editCategory, (val) => {
        if (val) {
            isEditing.value = true;
            field.name = val.name || '';
            field.parent = val.parent_id || '';
        } else {
            isEditing.value = false;
        }
    }, { immediate: true });

    const resetForm = () => {
        field.name = '';
        field.parent = '';
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

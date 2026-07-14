<template>
    <div class="d-flex justify-content-between align-items-center">
        <h3>View Purchase Credits</h3>
    </div>
    <div class="my-3">
        <div class="row g-2">
            <div class="col-3">
                <Input label="Search" id="search" type="text" name="search" v-model="filters.search" placeholder="Search invoice..."/>
            </div>
        </div>
    </div>
</template>

<script setup>
import { nextTick, reactive, watch } from 'vue';
import Input from '../../components/Input.vue';

const emit = defineEmits(['update:filters']);

const props = defineProps({
    filterValues: { type: Object, default: () => ({}) },
});

let updatingFromProp = false;

const filters = reactive({
    search: props.filterValues?.search || '',
});

watch(() => props.filterValues, (val) => {
    if (!val) return;
    updatingFromProp = true;
    filters.search = val.search || '';
    nextTick(() => { updatingFromProp = false; });
}, { deep: true });

watch(filters, () => {
    if (updatingFromProp) return;
    emit('update:filters', { ...filters });
}, { deep: true, immediate: true });
</script>

<style lang="scss" scoped>

</style>

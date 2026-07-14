<template>
    <div class="d-flex justify-content-between align-items-center">
        <h3>View Users</h3>
        <button
            class="btn btn-primary btn-sm"
            @click="goToAddUser"
        >
            <i class="bi bi-plus-circle"></i>
            Add Client
        </button>
    </div>
    <div class="my-3">
        <div class="row g-2">
            <div class="col-3">
                <Input label="Search" id="search" type="text" name="search" v-model="filters.search" placeholder="Search"/>
            </div>
            <div class="col-3" style="margin-top: 14px;">
                <div class="dropdown w-100">
                    <label for="dropdown-btn" class="form-label">Role</label>
                    <button class="btn border-secondary-subtle w-100 d-flex justify-content-between align-items-center" id="dropdown-btn"
                            type="button"
                            data-bs-toggle="dropdown">
                        <span class="text-truncate category-text">{{ selectedRoleLabel }}</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <ul class="dropdown-menu p-0 w-100 dropdown-role" @click.stop>
                        <li v-for="role in props.roles" :key="role.id">
                            <label class="dropdown-item d-flex align-items-center gap-2 mb-0">
                                <input class="form-check-input m-0" type="checkbox" :value="role.id" v-model="filters.roles">
                                {{ role.name }}
                            </label>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="col-3" style="margin-top: 14px;">
                <label for="status" class="form-label">Status</label>
                <select id="status" class="form-select" aria-label="Default select example" name="status" v-model="filters.status">
                    <option value="">Select Status</option>
                    <option v-for="s in props.statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, reactive, watch } from 'vue';
import Input from '../../components/Input.vue';
import { useRouter } from 'vue-router';

const router = useRouter();

const goToAddUser = () => {
    router.push({ name: 'add-user' });
};

const emit = defineEmits(['update:filters']);

const props = defineProps({
    roles: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    filterValues: { type: Object, default: () => ({}) },
});

let updatingFromProp = false;

const filters = reactive({
    search: props.filterValues?.search || '',
    roles: props.filterValues?.roles ? [...props.filterValues.roles] : [],
    status: props.filterValues?.status || '',
});

const selectedRoleLabel = computed(() => {
    const count = filters.roles.length;
    if (count === 0) return 'Choose Role';
    if (count === 1) {
        const role = props.roles.find(r => r.id === filters.roles[0]);
        return role ? role.name : 'Choose Role';
    }
    const names = props.roles
        .filter(r => filters.roles.includes(r.id))
        .map(r => r.name);
    return names.join(', ');
});

watch(() => props.filterValues, (val) => {
    if (!val) return;
    updatingFromProp = true;
    filters.search = val.search || '';
    filters.roles = val.roles ? [...val.roles] : [];
    filters.status = val.status || '';
    nextTick(() => { updatingFromProp = false; });
}, { deep: true });

watch(filters, () => {
    if (updatingFromProp) return;
    emit('update:filters', { ...filters });
}, { deep: true, immediate: true });
</script>

<style lang="scss" scoped>
.dropdown-role {
    max-height: 300px;
    overflow-y: auto;
}
</style>

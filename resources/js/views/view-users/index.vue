<template>
    <div class="container-fluid">
        <div class="my-3">
            <div class="card card-body">
                <Form 
                :roles="roles"
                :statuses="statuses"
                :filterValues="filters"
                @update:filters="filters = $event"/>
                <hr style="margin-left:-1rem; margin-right:-1rem;">
                <Table
                :users="users"
                :pagination="pagination"
                :perPage="perPage"
                :page="page"
                :exportEndpoint="'users'"
                :exportFilters="buildApiFilters()"
                @edit-user="handleEditUser"
                @delete-user="handleDeleteUser"
                @page-change="handlePageChange"
                @per-page-change="handlePerPageChange"
                @reload="load"
                @export-started="handleExportStarted"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from "vue";
import debounce from "lodash/debounce";
import { useRoute, useRouter } from "vue-router";
import { useExport } from '../../composables/useExport';
import { useUser } from "../../stores/user";
import Table from './Table.vue';
import Form from './Form.vue';

const dash = useUser();
const router = useRouter();
const route = useRoute();
const { handleExportStarted } = useExport('users');

let fromUrl = false;
let syncingUrl = false;

function parseQuery(q) {
    return {
        search: q.search || '',
        roles: q.roles
            ? String(q.roles).split(',').map(Number).filter(n => !isNaN(n))
            : [],
        status: q.status || '',
    };
}

const filters = ref(parseQuery(route.query));
const page = ref(Number(route.query.page) || 1);
const perPage = ref(Number(route.query.perPage) || 15);

const roles = computed(() => dash.allRoles);
const statuses = computed(() => dash.allStatuses);
const users = computed(() => dash.allUsers);
const pagination = computed(() => dash.allPaginations);

function buildApiFilters() {
    const f = filters.value;
    return { search: f.search, roles: f.roles, status: f.status };
};

function syncRoute() {
    syncingUrl = true;
    const query = {};
    if (page.value > 1) query.page = page.value;
    if (perPage.value !== 15) query.perPage = perPage.value;
    if (filters.value.search) query.search = filters.value.search;
    if (filters.value.roles.length) query.roles = filters.value.roles.join(',');
    if (filters.value.status) query.status = filters.value.status;
    router.replace({ query });
    nextTick(() => { syncingUrl = false; });
};

function load() {
    dash.loadUsers(buildApiFilters(), page.value, perPage.value);
}

onMounted(() => {
    if (window.Echo) {
        window.Echo.channel('users')
            .listen('.user-updated', () => {
                load();
            });
    }
});

watch(() => route.query, (q) => {
    if (syncingUrl) return;
    fromUrl = true;
    filters.value = parseQuery(q);
    page.value = Number(q.page) || 1;
    perPage.value = Number(q.perPage) || 15;
    syncRoute();
    load();
    nextTick(() => { fromUrl = false; });
}, { immediate: true });

const debouncedLoad = debounce(() => {
    page.value = 1;
    syncRoute();
    load();
}, 1000); 

watch(filters, () => {
    if (fromUrl) return;
    if (filters.value.search) {
        debouncedLoad();
    } else {
        page.value = 1;
        syncRoute();
        load();
    }
}, { deep: true });

const handlePageChange = (newPage) => {
    page.value = newPage;
    syncRoute();
    load();
};

const handlePerPageChange = (newPerPage) => {
    perPage.value = newPerPage;
    page.value = 1;
    syncRoute();
    load();
};

const handleEditUser = (user) => {
    router.push({ name: 'edit-user', params: { id: user.id } });
};

const handleDeleteUser = async (user) => {
    if (!confirm(`Are you sure you want to delete user "${user.name}"?`)) return;
    const result = await dash.deleteUser(user.id);
    if (result.status === 'success') {
        load();
    }
};
</script>

<style lang="scss" scoped>

</style>

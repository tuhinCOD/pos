<template>
    <div class="container-fluid">
        <div class="row my-3">
            <div class="col-lg-6" v-if="auth.user?.role?.name !== 'super admin'">
                <div class="card card-body">
                    <Form
                        ref="formRef"
                        :cities="cities"
                        :errors="formErrors"
                        :edit-branch="editBranch"
                        @reset-form="handleReset"
                        @update-branch="handleUpdateBranch"
                    />
                </div>
            </div>
            <div :class="auth.user?.role?.name === 'super admin' ? 'col-12' : 'col-lg-6'">
                <div class="card card-body">
                    <Table
                        :branches="branches"
                        :pagination="pagination"
                        :perPage="perPage"
                        :page="page"
                        :search="searchTerm"
                        :exportEndpoint="'branches'"
                        :exportFilters="{ search: searchTerm }"
                        @search="handleSearchInput"
                        @search-enter="handleSearchEnter"
                        @search-reset="handleSearchReset"
                        @edit-branch="handleEditBranch"
                        @delete-branch="handleDeleteBranch"
                        @page-change="handlePageChange"
                        @per-page-change="handlePerPageChange"
                        @reload="load"
                        @export-started="handleExportStarted"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
    import { computed, nextTick, onMounted, reactive, ref, watch } from "vue";
    import { useRoute, useRouter } from "vue-router";
    import { useExport } from '../../composables/useExport';
    import { useAuth } from '../../stores/auth';
    import { useBranch } from "../../stores/branch";
    import Form from "./Form.vue";
    import Table from "./Table.vue";

    const auth = useAuth();
    const { handleExportStarted } = useExport('branches');
    const dash = useBranch();
    const router = useRouter();
    const route = useRoute();

    const branches = computed(() => dash.allBranches);
    const cities = computed(() => dash.allCities);
    const pagination = computed(() => dash.allPaginations);

    const formRef = ref(null);
    const editBranch = ref(null);
    const searchTerm = ref('');
    const page = ref(1);
    const perPage = ref(15);

    const formErrors = reactive({
        name: '',
        contact: '',
        address: '',
        city: '',
    });

    let fromUrl = false;
    let syncingUrl = false;
    let pendingEditId = null;
    let searchTimeout = null;

    watch(branches, (brs) => {
        if (pendingEditId && brs.length) {
            const found = brs.find(c => c.id === pendingEditId);
            if (found) {
                editBranch.value = found;
                pendingEditId = null;
                syncRoute();
            }
        }
    });

    function syncRoute() {
        syncingUrl = true;
        const query = {};
        if (page.value > 1) query.page = page.value;
        if (perPage.value !== 15) query.perPage = perPage.value;
        if (searchTerm.value) query.search = searchTerm.value;
        if (editBranch.value) query.edit = editBranch.value.id;
        router.replace({ query }).then(() => {
            syncingUrl = false;
        });
    }

    function load() {
        dash.loadBranches(searchTerm.value ? { search: searchTerm.value } : {}, page.value, perPage.value);
    }

    onMounted(() => {
        if (window.Echo) {
            window.Echo.channel('branches')
                .listen('.branch-updated', () => {
                    load();
                });
        }
    });

    watch(() => route.query, (q) => {
        if (syncingUrl) return;
        fromUrl = true;
        searchTerm.value = q.search || '';
        page.value = Number(q.page) || 1;
        perPage.value = Number(q.perPage) || 15;
        const editId = q.edit ? Number(q.edit) : null;

        load();

        if (editId) {
            const found = dash.allBranches.find(c => c.id === editId);
            if (found) {
                editBranch.value = found;
                syncRoute();
            } else {
                pendingEditId = editId;
            }
        }
        nextTick(() => { fromUrl = false; });
    }, { immediate: true });

    watch(searchTerm, () => {
        if (fromUrl) return;
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            page.value = 1;
            load();
            syncRoute();
        }, 350);
    });

    const handleSearchInput = (val) => {
        searchTerm.value = val;
    };

    const handleUpdateBranch = async (branchData) => {
        const filters = searchTerm.value ? { search: searchTerm.value } : {};
        let result;
        if (editBranch.value) {
            result = await dash.updateBranch(editBranch.value.id, branchData, filters, page.value, perPage.value);
        } else {
            result = await dash.createBranch(branchData, filters, page.value, perPage.value);
        }

        Object.keys(formErrors).forEach(key => formErrors[key] = '');

        if (result.status === 'error' && result.errors) {
            for (const key in result.errors) {
                if (formErrors[key] !== undefined) {
                    formErrors[key] = result.errors[key];
                }
            }
        }

        if (result.status === 'success') {
            handleReset();
        }
        return result;
    };

    const handleEditBranch = (branch) => {
        editBranch.value = branch;
        syncRoute();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const handleDeleteBranch = async (branchId) => {
        if (!confirm('Are you sure you want to delete this branch?')) return;
        const filters = searchTerm.value ? { search: searchTerm.value } : {};
        const result = await dash.deleteBranch(branchId, filters, page.value, perPage.value);
        if (result.status === 'error') {
            alert('Failed to delete branch');
        }
    };

    const handleReset = () => {
        if (formRef.value && typeof formRef.value.resetForm === 'function') {
            formRef.value.resetForm();
        }
        Object.keys(formErrors).forEach(key => formErrors[key] = '');
        editBranch.value = null;
        syncingUrl = true;
        const query = { ...route.query };
        delete query.edit;
        router.replace({ query }).then(() => {
            syncingUrl = false;
        });
    };

    const handlePageChange = (newPage) => {
        page.value = newPage;
        load();
        syncRoute();
    };

    const handlePerPageChange = (newPerPage) => {
        perPage.value = newPerPage;
        page.value = 1;
        load();
        syncRoute();
    };

    const handleSearchEnter = () => {
        clearTimeout(searchTimeout);
        page.value = 1;
        load();
        syncRoute();
    };

    const handleSearchReset = () => {
        clearTimeout(searchTimeout);
        searchTerm.value = '';
        page.value = 1;
        load();
        syncRoute();
    };
</script>

<style lang="scss" scoped>

</style>

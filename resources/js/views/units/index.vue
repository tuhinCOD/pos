<template>
    <div class="container-fluid">
        <div class="row my-3">
            <div v-if="auth.user?.role?.name !== 'super admin'" class="col-lg-6">
                <div class="card card-body">
                    <Form
                        ref="formRef"
                        :errors="formErrors"
                        :edit-unit="editUnit"
                        @reset-form="handleReset"
                        @update-unit="handleUpdateUnit"
                    />
                </div>
            </div>
            <div :class="auth.user?.role?.name === 'super admin' ? 'col-12' : 'col-lg-6'">
                <div class="card card-body">
                    <Table
                        :units="units"
                        :pagination="pagination"
                        :perPage="perPage"
                        :page="page"
                        :search="searchTerm"
                        :exportEndpoint="'units'"
                        :exportFilters="{ search: searchTerm }"
                        @search="handleSearchInput"
                        @search-enter="handleSearchEnter"
                        @search-reset="handleSearchReset"
                        @edit-unit="handleEditUnit"
                        @delete-unit="handleDeleteUnit"
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
    import { useAuth } from "../../stores/auth";
    import { useUnit } from "../../stores/unit";
    import { useExport } from '../../composables/useExport';
    import Form from "./Form.vue";
    import Table from "./Table.vue";

    const auth = useAuth();

    const dash = useUnit();
    const router = useRouter();
    const route = useRoute();
    const { handleExportStarted } = useExport('units');

    const units = computed(() => dash.allUnits);
    const pagination = computed(() => dash.allPaginations);

    const formRef = ref(null);
    const editUnit = ref(null);
    const searchTerm = ref('');
    const page = ref(1);
    const perPage = ref(15);

    const formErrors = reactive({
        name: '',
        description: '',
    });

    let fromUrl = false;
    let syncingUrl = false;
    let pendingEditId = null;
    let searchTimeout = null;

    watch(units, (uts) => {
        if (pendingEditId && uts.length) {
            const found = uts.find(u => u.id === pendingEditId);
            if (found) {
                editUnit.value = found;
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
        if (editUnit.value) query.edit = editUnit.value.id;
        router.replace({ query }).then(() => {
            syncingUrl = false;
        });
    }

    function load() {
        dash.loadUnits(searchTerm.value ? { search: searchTerm.value } : {}, page.value, perPage.value);
    }

    onMounted(() => {
        if (window.Echo) {
            window.Echo.channel('units')
                .listen('.unit-updated', () => {
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
            const found = dash.allUnits.find(u => u.id === editId);
            if (found) {
                editUnit.value = found;
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

    const handleUpdateUnit = async (unitData) => {
        const filters = searchTerm.value ? { search: searchTerm.value } : {};
        let result;
        if (editUnit.value) {
            result = await dash.updateUnit(editUnit.value.id, unitData, filters, page.value, perPage.value);
        } else {
            result = await dash.createUnit(unitData, filters, page.value, perPage.value);
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

    const handleEditUnit = (unit) => {
        editUnit.value = unit;
        syncRoute();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const handleDeleteUnit = async (unitId) => {
        if (!confirm('Are you sure you want to delete this unit?')) return;
        const filters = searchTerm.value ? { search: searchTerm.value } : {};
        const result = await dash.deleteUnit(unitId, filters, page.value, perPage.value);
        if (result.status === 'error') {
            alert('Failed to delete unit');
        }
    };

    const handleReset = () => {
        if (formRef.value && typeof formRef.value.resetForm === 'function') {
            formRef.value.resetForm();
        }
        Object.keys(formErrors).forEach(key => formErrors[key] = '');
        editUnit.value = null;
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

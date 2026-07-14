<template>
    <div class="container-fluid">
        <div class="row my-3">
            <div class="col-lg-6" v-if="auth.user?.role?.name !== 'super admin'">
                <div class="card card-body">
                    <Form
                        ref="formRef"
                        :cities="cities"
                        :errors="formErrors"
                        :edit-supplier="editSupplier"
                        @reset-form="handleReset"
                        @update-supplier="handleUpdateSupplier"
                    />
                </div>
            </div>
            <div :class="auth.user?.role?.name === 'super admin' ? 'col-12' : 'col-lg-6'">
                <div class="card card-body">
                    <Table
                        :suppliers="suppliers"
                        :pagination="pagination"
                        :perPage="perPage"
                        :page="page"
                        :search="searchTerm"
                        :exportEndpoint="'suppliers'"
                        :exportFilters="{ search: searchTerm }"
                        @search="handleSearchInput"
                        @search-enter="handleSearchEnter"
                        @search-reset="handleSearchReset"
                        @edit-supplier="handleEditSupplier"
                        @delete-supplier="handleDeleteSupplier"
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
    import { useSupplier } from "../../stores/supplier";
    import { useAuth } from '../../stores/auth';
    import { useExport } from '../../composables/useExport';
    import Form from "./Form.vue";
    import Table from "./Table.vue";

    const auth = useAuth();
    
    const dash = useSupplier();
    const { handleExportStarted } = useExport('suppliers');
    const router = useRouter();
    const route = useRoute();

    const suppliers = computed(() => dash.allSuppliers);
    const cities = computed(() => dash.allCities);
    const pagination = computed(() => dash.allPaginations);

    const formRef = ref(null);
    const editSupplier = ref(null);
    const searchTerm = ref('');
    const page = ref(1);
    const perPage = ref(15);

    const formErrors = reactive({
        name: '',
        contact: '',
        email: '',
        city: '',
        address: '',
        remarks: '',
    });

    let fromUrl = false;
    let syncingUrl = false;
    let pendingEditId = null;
    let searchTimeout = null;

    watch(suppliers, (list) => {
        if (pendingEditId && list.length) {
            const found = list.find(s => s.id === pendingEditId);
            if (found) {
                editSupplier.value = found;
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
        if (editSupplier.value) query.edit = editSupplier.value.id;
        router.replace({ query }).then(() => {
            syncingUrl = false;
        });
    }

    function load() {
        dash.loadSuppliers(searchTerm.value ? { search: searchTerm.value } : {}, page.value, perPage.value);
    }

    onMounted(() => {
        if (window.Echo) {
            window.Echo.channel('suppliers')
                .listen('.supplier-updated', () => {
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
            const found = dash.allSuppliers.find(s => s.id === editId);
            if (found) {
                editSupplier.value = found;
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

    const handleUpdateSupplier = async (supplierData) => {
        const filters = searchTerm.value ? { search: searchTerm.value } : {};
        let result;
        if (editSupplier.value) {
            result = await dash.updateSupplier(editSupplier.value.id, supplierData, filters, page.value, perPage.value);
        } else {
            result = await dash.createSupplier(supplierData, filters, page.value, perPage.value);
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

    const handleEditSupplier = (supplier) => {
        editSupplier.value = supplier;
        syncRoute();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const handleDeleteSupplier = async (supplierId) => {
        if (!confirm('Are you sure you want to delete this supplier?')) return;
        const filters = searchTerm.value ? { search: searchTerm.value } : {};
        const result = await dash.deleteSupplier(supplierId, filters, page.value, perPage.value);
        if (result.status === 'error') {
            alert('Failed to delete supplier');
        }
    };

    const handleReset = () => {
        if (formRef.value && typeof formRef.value.resetForm === 'function') {
            formRef.value.resetForm();
        }
        Object.keys(formErrors).forEach(key => formErrors[key] = '');
        editSupplier.value = null;
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

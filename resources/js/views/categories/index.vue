<template>
    <div class="container-fluid">
        <div class="row my-3">
            <div v-if="auth.user?.role?.name !== 'super admin'" class="col-lg-6">
                <div class="card card-body">
                    <Form
                        ref="formRef"
                        :categories="parentCategories"
                        :errors="formErrors"
                        :edit-category="editCategory"
                        @reset-form="handleReset"
                        @update-category="handleUpdateCategory"
                    />
                </div>
            </div>
            <div :class="auth.user?.role?.name === 'super admin' ? 'col-12' : 'col-lg-6'">
                <div class="card card-body">
                    <Table
                        :categories="categories"
                        :pagination="pagination"
                        :perPage="perPage"
                        :page="page"
                        :search="searchTerm"
                        :exportEndpoint="'categories'"
                        :exportFilters="{ search: searchTerm }"
                        @search="handleSearchInput"
                        @search-enter="handleSearchEnter"
                        @search-reset="handleSearchReset"
                        @edit-category="handleEditCategory"
                        @delete-category="handleDeleteCategory"
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
    import { useCategory } from "../../stores/category";
    import Form from "./Form.vue";
    import Table from "./Table.vue";

    const auth = useAuth();
    const { handleExportStarted } = useExport('categories');

    const dash = useCategory();
    const router = useRouter();
    const route = useRoute();

    const categories = computed(() => dash.allCategories);
    const parentCategories = computed(() => dash.allParentCategories);
    const pagination = computed(() => dash.allPaginations);

    const formRef = ref(null);
    const editCategory = ref(null);
    const searchTerm = ref('');
    const page = ref(1);
    const perPage = ref(15);

    const formErrors = reactive({
        name: '',
        parent: '',
    });

    let fromUrl = false;
    let syncingUrl = false;
    let pendingEditId = null;
    let searchTimeout = null;

    watch(categories, (cats) => {
        if (pendingEditId && cats.length) {
            const found = cats.find(c => c.id === pendingEditId);
            if (found) {
                editCategory.value = found;
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
        if (editCategory.value) query.edit = editCategory.value.id;
        router.replace({ query }).then(() => {
            syncingUrl = false;
        });
    }

    function load() {
        dash.loadCategories(searchTerm.value ? { search: searchTerm.value } : {}, page.value, perPage.value);
    }

    onMounted(() => {
        if (window.Echo) {
            window.Echo.channel('categories')
                .listen('.category-updated', () => {
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
            const found = dash.allCategories.find(c => c.id === editId);
            if (found) {
                editCategory.value = found;
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
        }, 500);
    });

    const handleSearchInput = (val) => {
        searchTerm.value = val;
    };

    const handleUpdateCategory = async (categoryData) => {
        const filters = searchTerm.value ? { search: searchTerm.value } : {};
        let result;
        if (editCategory.value) {
            result = await dash.updateCategory(editCategory.value.id, categoryData, filters, page.value, perPage.value);
        } else {
            result = await dash.createCategory(categoryData, filters, page.value, perPage.value);
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

    const handleEditCategory = (category) => {
        editCategory.value = category;
        syncRoute();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const handleDeleteCategory = async (categoryId) => {
        if (!confirm('Are you sure you want to delete this category?')) return;
        const filters = searchTerm.value ? { search: searchTerm.value } : {};
        const result = await dash.deleteCategory(categoryId, filters, page.value, perPage.value);
        if (result.status === 'error') {
            alert('Failed to delete category');
        }
    };

    const handleReset = () => {
        if (formRef.value && typeof formRef.value.resetForm === 'function') {
            formRef.value.resetForm();
        }
        Object.keys(formErrors).forEach(key => formErrors[key] = '');
        editCategory.value = null;
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

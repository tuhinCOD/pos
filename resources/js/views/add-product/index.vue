<template>
    <div class="container-fluid">
        <div class="my-3">
            <div class="card card-body">
                <Form
                ref="formRef"
                :categories="categories"
                :statuses="statuses"
                :units="units"
                :errors="formErrors"
                :editProduct="editProduct"
                @images-changed="selectedImages = $event"
                @reset-form="handleReset"
                @update-product="handleUpdateProduct"
                />
            </div>
        </div>
        <div class="my-3" v-if="editProduct">
            <div class="card card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted">Editing product: <strong>{{ editProduct.name }}</strong></span>
                    <div>
                        <button class="btn btn-danger me-2" @click="handleDelete">
                            <i class="bi bi-trash"></i> Delete Product
                        </button>
                        <button class="btn btn-secondary" @click="cancelEdit">
                            <i class="bi bi-x-circle"></i> Cancel Edit
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- <div class="my-3">
            <div class="card card-body">
                <Table :products="products" @edit-product="handleEditProduct" @delete-product="handleDeleteFromTable"/>
            </div>
        </div> -->
    </div>
</template>

<script setup>
    import { computed, onMounted, reactive, ref, watch } from "vue";
    import { useRoute, useRouter } from "vue-router";
    import { useProduct } from "../../stores/product";
    import Form from "./Form.vue";
    import Table from "./Table.vue";

    const dash = useProduct();
    const route = useRoute();
    const router = useRouter();

    const categories =  computed(() => dash.allCategories);
    const statuses =  computed(() => dash.allStatuses);
    const units =  computed(() => dash.allUnits);
    const products =  computed(() => dash.allProducts);

    const formRef = ref(null);
    const editProduct = ref(null);
    const selectedImages = ref([]);

    const formErrors = reactive({
        barcode: '',
        name: '',
        parent_category: '',
        category: '',
        unit_id: '',
        status: '',
        attributes: '',
        description: '',
        images: '',
        title: '',
    });

    const handleUpdateProduct = async (productData) => {
        let result;

        if (editProduct.value) {
            result = await dash.updateProduct(editProduct.value.id, productData);
        } else {
            result = await dash.createProduct(productData);
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

    const handleEditProduct = (product) => {
        editProduct.value = product;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const handleDelete = async () => {
        if (!editProduct.value) return;
        if (!confirm(`Are you sure you want to delete product "${editProduct.value.name}"?`)) return;

        const result = await dash.deleteProduct(editProduct.value.id);
        if (result.status === 'success') {
            handleReset();
        } else {
            alert('Failed to delete product');
        }
    };

    const handleDeleteFromTable = async (product) => {
        if (!confirm(`Are you sure you want to delete product "${product.name}"?`)) return;
        await dash.deleteProduct(product.id);
    };

    const handleReset = () => {
        if (formRef.value && typeof formRef.value.resetForm === 'function') {
            formRef.value.resetForm();
        }
        Object.keys(formErrors).forEach(key => formErrors[key] = '');
        editProduct.value = null;
        router.push({ name: 'add-product' });
    };

    const cancelEdit = () => {
        handleReset();
    };

    onMounted(async () => {
        if (!dash.allCategories.length) {
            await dash.loadProducts();
        }

        const id = route.params.id;
        if (id) {
            const found = dash.allProducts.find(p => p.id === Number(id));
            if (found) {
                editProduct.value = found;
            }
        }
    });

    onMounted(() => {
        if (window.Echo) {
            window.Echo.channel('products')
                .listen('.product-updated', () => {
                    if (!dash.allProducts.length) {
                        dash.loadProducts();
                    }
                });
        }
    });

    watch(() => route.params.id, (id) => {
        if (!id) {
            handleReset();
        } else {
            const found = dash.allProducts.find(p => p.id === Number(id));
            if (found) {
                editProduct.value = found;
            }
        }
    });
</script>

<style lang="scss" scoped>

</style>

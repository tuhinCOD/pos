<template>
    <form id="product-form" @submit.prevent="updateForm">
        <div class="d-flex justify-content-between align-items-center">
            <h3 class="mb-0">
                {{ isEditPage ? 'Edit Product' : 'Add Product' }}
            </h3>
            <button
            class="btn btn-primary btn-sm"
            @click="goToViewProduct"
            >
                <i class="bi bi-eye"></i>
                View Product
            </button>
        </div>
        <div class="my-3">
            <div class="row g-4">
                <div class="col-3">
                    <Input label="Barcode" id="barcode" type="text" errid="barcodeHelp" name="barcode" :err="errors.barcode?.[0]" v-model="field.barcode" placeholder="Product Barcode" must/>
                </div>
                <div class="col-3">
                    <Input label="Name" id="name" type="text" errid="nameHelp" name="name" :err="errors.name?.[0]" v-model="field.name" placeholder="Product Name" must/>
                </div>
                <div class="col-3" style="margin-top: 30px;">
                    <label for="barcodeType" class="form-label">
                        Barcode Type <span class="text-danger">*</span>
                    </label>
                    <select id="barcodeType" class="form-select" aria-label="Barcode type" name="barcode_type" v-model="field.barcode_type">
                        <option value="single">Single (one barcode per product)</option>
                        <option value="piece">Piece (unique barcode per unit)</option>
                        <option value="weight">Weight (one barcode per batch)</option>
                    </select>
                    <div id="barcodeTypeHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.barcode_type?.[0] }}</div>
                </div>
                <div class="col-3" style="margin-top: 30px;">
                    <label for="parentCategory" class="form-label">
                        Parent Category <span class="text-danger">*</span>
                    </label>
                    <select id="parentCategory" class="form-select" aria-label="Parent category" name="parent_category" v-model="field.parent_category">
                        <option value="">Select Category</option>
                        <option v-for="category in props.categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                    </select>
                    <div id="categoryHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.parent_category?.[0] }}</div>
                </div>
                <div class="col-3" style="margin-top: 30px;">
                    <label for="childCategory" class="form-label">
                        Child Category <span class="text-danger">*</span>
                    </label>
                    <select id="childCategory" class="form-select" aria-label="Child category" name="category" v-model="field.category">
                        <option v-if="childCategories.length" value="">Select Category</option>
                        <option v-for="category in childCategories" :key="category.id" :value="category.id">{{ category.name }}</option>
                    </select>
                    <div id="categoryHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.category?.[0] }}</div>
                </div>
                <div class="col-3" style="margin-top: 30px;">
                    <label for="status" class="form-label">
                        Unit <span class="text-danger">*</span>
                    </label>
                    <select id="unit" class="form-select" name="unit_id" v-model="field.unit_id">
                        <option value="">Select Unit</option>
                        <option v-for="u in props.units" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                    <div id="unitHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.unit_id?.[0] }}</div>
                </div>
                <div class="col-3" style="margin-top: 30px;">
                    <label for="status" class="form-label">
                        Status <span class="text-danger">*</span>
                    </label>
                    <select id="status" class="form-select" aria-label="Default select example" name="status" v-model="field.status">
                        <option value="">Select Status</option>
                        <option v-for="status in props.statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
                    </select>
                    <div id="statusHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.status?.[0] }}</div>
                </div>
                <div class="col-5" style="margin-top: 30px;">
                    <label class="form-label" for="image">Images Upload</label>
                    <input ref="imageInput" type="file" name="images[]" id="image" class="form-control" multiple accept="image/*" @change="handleFileChange"/>
                    <div class="form-text text-danger fw-bold" id="imageHelp" style="font-size: 10px;">{{ errors.images?.[0] }}</div>
                </div>
                <div class="col-3">
                    <Input  label="Images Title" id="title" type="text" errid="titleHelp" name="title" v-model="field.title" :err="errors.title?.[0]" placeholder="Product Images Title"/>
                </div>
                <div class="col-5">
                    <label for="description" class="form-label">Description</label>
                    <textarea name="description" class="form-control" id="description" placeholder='Write product description here...' v-model="field.description"></textarea>
                    <div id="descriptionHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.description?.[0] }}</div>
                </div>
                <div class="col-5" style="margin-top: 30px;">
                    <label class="form-label me-1">Attributes</label>
                    <div v-for="(attr, index) in field.attributes" :key="index" class="input-group mb-1">
                        <input type="text" class="form-control form-control-sm" v-model="attr.key" placeholder="Key (e.g. size)" style="width: 40%;" />
                        <input type="text" class="form-control form-control-sm" v-model="attr.value" placeholder="Value(s) (e.g. S, M, L)" />
                        <button type="button" class="btn btn-outline-danger btn-sm" @click="removeAttribute(index)">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-1" @click="addAttribute">
                        <i class="bi bi-plus"></i> Add Attribute
                    </button>
                </div>
            </div>
            <div class="col-12 mt-3">
                <div class="form-text text-danger">* mark every input field is required field</div>
                <div id="errorDiv" class="form-text text-danger"></div>
            </div>
        </div>
        <div class="row g-2 my-3">
            <div class="col-6">
                <Button :label="props.editProduct ? 'Update' : 'Submit'" id="submitBtn" type="submit" icon="bi bi-save-fill"/>
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
    import { reactive, ref, computed, watch } from 'vue';
    import { useRoute, useRouter } from "vue-router";

    const route = useRoute();
    const router = useRouter();

    const goToViewProduct = () => {
        router.push({ name: 'products' });
    };

    const isEditPage = computed(() => {
        return route.name === 'edit-product';
    });

    const props = defineProps({
        categories: { type: Array, default: () => [] },
        statuses: { type: Array, default: () => [] },
        units: { type: Array, default: () => [] },
        errors: { type: Object, default: () => ({}) },
        editProduct: { type: Object, default: null },
    });

    const emit = defineEmits([
        "reset-form",
        "images-changed",
        "update-product",
    ]);

    const imageInput = ref(null);
    const field = reactive({
        barcode: '',
        name: '',
        barcode_type: 'single',
        parent_category: '',
        category: '',
        unit_id: '',
        status: '',
        attributes: [],
        description: '',
        images: [],
        title: '',
    });

    const addAttribute = () => {
        field.attributes.push({ key: '', value: '' });
    };

    const removeAttribute = (index) => {
        field.attributes.splice(index, 1);
    };

    const childCategories = computed(() => {
        const children = props.categories.filter(
            c => c.parent_id === field.parent_category
        );

        if (children.length) {
            return children;
        }

        return props.categories.filter(
            c => c.id === field.parent_category
        );
    });
    
    const isInitializing = ref(false);
    watch(() => field.parent_category, () => {
        if (!isInitializing.value) {
            field.category = '';
        }
    });

    const isEditing = ref(false);

    const handleFileChange = (event) => {
        field.images = Array.from(event.target.files);
        emit("images-changed", field.images);
    };

    const updateForm = () => {
        const formData = new FormData();

        for (const key in field) {
            if (key === 'images') {
                field.images.forEach((image) => {
                    formData.append('images[]', image);
                });
            } else if (key === 'attributes') {
                const obj = {};
                field.attributes.forEach((attr) => {
                    if (attr.key) {
                        const vals = attr.value.split(',').map(v => v.trim()).filter(v => v);
                        obj[attr.key] = vals.length > 1 ? vals : vals[0] || '';
                    }
                });
                formData.append('attributes', JSON.stringify(obj));
            } else {
                formData.append(key, field[key]);
            }
        }

        emit('update-product', formData);
    };

    watch(() => props.editProduct, (val) => {
        if (val) {
            isEditing.value = true;
            isInitializing.value = true;
            field.barcode = val.barcode || '';
            field.name = val.name || '';
            field.barcode_type = val.barcode_type || 'single';
            const selectedCategory = props.categories.find(c => c.id === (val.category_id || val.category?.id));
            field.parent_category = selectedCategory?.parent_id || '';
            field.category = val.category_id || val.category?.id || '';
            field.unit_id = val.unit_id || '';
            field.status = val.status_id || val.status?.id || '';
            field.attributes = val.attributes
                ? (typeof val.attributes === 'string' ? JSON.parse(val.attributes) : val.attributes)
                : [];
            if (!Array.isArray(field.attributes)) {
                field.attributes = Object.entries(field.attributes).map(([k, v]) => ({ key: k, value: Array.isArray(v) ? v.join(', ') : v }));
            }
            field.description = val.description || '';
            field.title = '';
            field.images = [];

            setTimeout(() => {
                isInitializing.value = false;
            });
        } else {
            isEditing.value = false;
        }
    }, { immediate: true });

    const resetForm = () => {
        field.barcode = '';
        field.name = '';
        field.barcode_type = 'single';
        field.parent_category = '';
        field.category = '';
        field.unit_id = '';
        field.status = '';
        field.attributes = [];
        field.description = '';
        field.images = [];
        field.title = '';
        isEditing.value = false;
        if (imageInput.value) {
            imageInput.value.value = '';
        }

        emit("images-changed", []);
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
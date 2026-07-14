<template>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr class="text-center">
                    <th class="col-1">id</th>
                    <th class="col-1">barcode</th>
                    <th class="col-1">name</th>
                    <th class="col-1">category</th>
                    <th class="col-1">status</th>
                    <th class="col-1">description</th>
                    <th class="col-1">attributes</th>
                    <th class="col-1">images</th>
                    <th class="col-1">action</th>
                </tr>
            </thead>
            <tbody id="list">
                <tr v-if="!products || products.length === 0">
                    <td colspan="9" class="text-center text-muted">
                        No data available.
                    </td>
                </tr>
                <tr v-for="product in products" :key="product.id" class="text-center align-middle" style="font-size: 12px">
                    <td>{{ product.id }}</td>
                    <td>{{ product.barcode }}</td>
                    <td>{{ product.name }}</td>
                    <td>{{ product.category?.name }}</td>
                    <td>{{ product.status?.name }}</td>
                    <td>{{ product.description }}</td>
                    <td>
                        <template v-if="product.attributes && typeof product.attributes === 'object'">
                            <div v-for="(v, k) in product.attributes" :key="k" class="small">{{ k }}: {{ v }}</div>
                        </template>
                        <span v-else-if="product.attributes">{{ product.attributes }}</span>
                        <span v-else class="text-muted">-</span>
                    </td>
                    <td>
                        <span v-if="product.images?.length" class="badge bg-info">{{ product.images.length }}</span>
                        <span v-else class="text-muted">-</span>
                    </td>
                    <td>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Basic mixed styles example">
                        <button type="button" class="btn btn-info release-btn" id="release-btn"><i class="bi bi-link-45deg"></i></button>
                        <button type="button" class="btn btn-warning edit-btn" @click="emit('edit-product', product)"><i class="bi bi-pencil-square" ></i></button>
                        <button class="btn btn-danger delete-btn" @click="emit('delete-product', product)"><i class="bi bi-trash"></i></button>
                    </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
defineProps({
    products: { type: Array, default: () => [] },
});

const emit = defineEmits(['edit-product', 'delete-product']);
</script>

<style lang="scss" scoped>

</style>

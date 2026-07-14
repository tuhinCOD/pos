<template>
    <div class="table-responsive">
        <div v-if="selectedCount" class="d-flex gap-2 mb-2 p-2 bg-light border rounded">
            <span class="fw-bold small align-self-center">{{ selectedCount }} selected</span>
            <button v-if="auth.user?.role?.name !== 'super admin' && auth.user?.role?.name !== 'cashier' && auth.user?.role?.name !== 'manager' && auth.user?.role?.name !== 'warehouse staff'" class="btn btn-sm btn-danger" @click="deleteSelected">
                <i class="bi bi-trash"></i> Delete
            </button>
            <button v-if="auth.user?.role?.name !== 'super admin'" class="btn btn-sm btn-success" @click="confirmExportSelected">
                <i class="bi bi-file-earmark-excel-fill"></i> Excel
            </button>
            <button class="btn btn-sm btn-outline-secondary" @click="clearSelection">Clear</button>
        </div>
        <table class="table table-bordered table-striped" style="min-width: 576px;">
            <thead>
                <tr class="text-center">
                    <th class="col-checkbox">
                        <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" />
                    </th>
                    <th class="col-4">Product</th>
                    <th class="col-4">Details</th>
                    <th class="col-2">Price</th>
                    <th class="col-1">Action</th>
                </tr>
            </thead>
            <tbody id="list">
                <tr v-if="!products || products.length === 0">
                    <td colspan="10" class="text-center text-muted">
                        No data available.
                    </td>
                </tr>
                <tr v-for="product in props.products" :key="product.id" class="text-center align-middle" style="font-size: 12px">
                    <td class="col-checkbox"><input type="checkbox" :checked="selectedIds.has(product.id)" @change="toggleSelect(product.id)" /></td>
                    <td style="font-size: 12px;" class="text-start">
                        <div class="about-product">
                            <div>
                                <i class="bi bi-upc-scan"></i>
                                Barcode: {{ product.barcode || '-' }}
                            </div>
                            <div>
                                <i class="bi bi-qr-code"></i>
                                Type: {{ product.barcode_type || 'single' }}
                            </div>
                            <div>
                                <i class="bi bi-bag-fill"></i>
                                Name: {{ product.name || '-' }}<br>
                            </div>
                            <div>
                                <i class="bi bi-tag-fill"></i>
                                Category: {{ product.category?.name || '-' }}<br>
                            </div>
                            <div>
                                <i class="bi bi-box-fill"></i>
                                Unit: {{ product.unit?.name || '-' }}
                            </div>
                            <div>
                                <i class="bi bi-clipboard2-pulse-fill"></i>
                                Status: {{ product.status?.name || '-' }}
                            </div>
                        </div>
                    </td>
                    <td style="font-size: 12px;" class="text-start">
                        <div class="about-product-details">
                            <div>
                                <i class="bi bi-envelope-fill"></i>
                                Description: {{ product.description || '-' }}<br>
                            </div>
                            <div>
                                <i class="bi bi-filetype-json"></i>>
                                Attributes:
                                <template v-if="product.attributes && typeof product.attributes === 'object'">
                                    <span v-for="(v, k, i) in product.attributes" :key="k">
                                        {{ k }}: {{ v }}<span v-if="i < Object.keys(product.attributes).length - 1">, </span>
                                    </span>
                                </template>
                                <span v-else-if="product.attributes">{{ product.attributes }}</span>
                                <span v-else>-</span>
                            </div>
                            <div>
                                <i class="bi bi-person-fill"></i>
                                Created by: {{ product.user?.name || '-' }}<br>
                            </div>
                            <div>
                                <i class="bi bi-person-fill"></i>
                                updated by: {{ product.updated_by?.name || '-' }}<br>
                            </div>
                        </div>
                    </td>
                    <!-- <td>
                        <span v-if="product.images?.length" class="badge bg-info">{{ product.images.length }}</span>
                        <span v-else class="text-muted">-</span>
                    </td> -->
                    <td style="font-size: 12px;" class="text-start">
                        <div class="about-product-price">
                            <div>
                                <i class="bi bi-currency-dollar"></i>
                                Price: {{ product.product_price?.price || '-' }}
                            </div>
                            <div>
                                <i class="bi bi-file-earmark-text-fill"></i>
                                Vat: {{ product.product_price?.vat || '-' }}<br>
                            </div>
                            <div>
                                <i class="bi bi-star-fill"></i>
                                Point: {{ product.product_price?.point || '-' }}<br>
                            </div>
                        </div>
                    </td>
                    <td>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Basic mixed styles example">
                        <button type="button" class="btn btn-success release-btn" id="release-btn" @click="emit('view-product', product)" title="view images"><i class="bi bi-eye"></i></button>
                        <button v-if="auth.user?.role?.name !== 'super admin' && auth.user?.role?.name !== 'cashier' && auth.user?.role?.name !== 'warehouse staff'" type="button" class="btn btn-warning edit-btn" @click="emit('edit-product', product)" title="Edit product"><i class="bi bi-pencil-square" ></i></button>
                        <button v-if="auth.user?.role?.name !== 'super admin' && auth.user?.role?.name !== 'cashier' && auth.user?.role?.name !== 'warehouse staff'" type="button" class="btn btn-info text-white" @click="emit('edit-product-price', product)" title="Edit price"><i class="bi bi-currency-dollar"></i></button>
                        <button v-if="auth.user?.role?.name !== 'super admin' && auth.user?.role?.name !== 'cashier' && auth.user?.role?.name !== 'manager' && auth.user?.role?.name !== 'warehouse staff'" class="btn btn-danger delete-btn" @click="emit('delete-product', product)"><i class="bi bi-trash" title="Delete product"></i></button>
                    </div>
                    </td>
                </tr>
            </tbody>
        </table>
        <div class="card card-body" style="min-width: 576px;">
            <nav aria-label="Page navigation">
                <div class="d-flex flex-nowrap align-items-center justify-content-between gap-3">
                    <div class="text-muted" style="font-size: 14px;">
                        {{ showingText }}
                    </div>

                    <ul class="pagination mb-0" v-if="props.pagination?.last_page > 1">
                        <!-- Previous -->
                        <li class="page-item" :class="{ disabled: props.pagination.current_page === 1 }">
                            <button class="page-link" @click="emit('page-change', props.pagination.current_page - 1)">
                                Previous
                            </button>
                        </li>

                        <!-- Page Numbers -->
                        <li
                            v-for="page in pageNumbers"
                            :key="page + '-' + props.pagination.current_page"
                            class="page-item"
                            :class="{ active: page === props.pagination.current_page, disabled: page.toString().includes('ellipsis') }"
                        >
                            <button
                                v-if="typeof page === 'number'"
                                class="page-link"
                                @click="emit('page-change', page)"
                            >
                                {{ page }}
                            </button>
                            <span v-else class="page-link">&hellip;</span>
                        </li>

                        <!-- Next -->
                        <li class="page-item" :class="{ disabled: props.pagination.current_page === props.pagination.last_page }">
                            <button class="page-link" @click="emit('page-change', props.pagination.current_page + 1)">
                                Next
                            </button>
                        </li>
                    </ul>

                    <div class="d-flex align-items-center gap-2" v-if="props.pagination?.last_page > 1">
                        <label class="form-label mb-0 text-nowrap" style="font-size: 13px;">Go to page:</label>
                        <input
                            type="number"
                            class="form-control form-control-sm"
                            style="width: 70px;"
                            :min="1"
                            :max="props.pagination.last_page"
                            v-model.number="jumpPage"
                            @keyup.enter="goToPage"
                        />
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <label class="form-label mb-0 text-nowrap" style="font-size: 13px;">Per page:</label>
                        <select class="form-select form-select-sm" style="width: auto;" :value="props.perPage" @change="emit('per-page-change', Number($event.target.value))">
                            <option :value="5">5</option>
                            <option :value="10">10</option>
                            <option :value="15">15</option>
                            <option :value="20">20</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                        </select>
                    </div>

                    <div v-if="exportEndpoint">
                        <ExportButton v-if="auth.user?.role?.name !== 'super admin'" :endpoint="exportEndpoint" :filters="exportFilters" :asyncExport="true" @export-started="(file) => emit('export-started', file)" />
                    </div>
                </div>
            </nav>
        </div>
    </div>
</template>

<script setup>
    import { useAuth } from '../../stores/auth';
    import ExportButton from '../../components/ExportButton.vue';
    const auth = useAuth();
    const props = defineProps({
        products: { type: Array, default: () => [] },
        pagination: { type: Object, default: () => ({}) },
        perPage: { type: Number, default: 15 },
        page: { type: Number, default: 1 },
        exportEndpoint: { type: String, default: '' },
        exportFilters: { type: Object, default: () => ({}) },
    });

    const emit = defineEmits(['edit-product', 'delete-product', 'page-change', 'per-page-change', 'edit-product-price', 'view-product', 'reload', 'export-started']);

    import { computed, ref, watch } from 'vue';
    import axios from 'axios';

    const selectedIds = ref(new Set());

    const selectedCount = computed(() => selectedIds.value.size);

    const allSelected = computed(() =>
        props.products.length > 0 && props.products.every(item => selectedIds.value.has(item.id))
    );

    const confirmExportSelected = async () => {
        const ids = Array.from(selectedIds.value);
        if (!ids.length) return;
        if (!confirm(`Are you sure you want to export ${ids.length} data?`)) return;
        try {
            const params = { ids: ids.join(',') };
            const res = await axios.get(`/api/${props.exportEndpoint}/export`, { params });
            emit('export-started', res.data.file);
        } catch {
            alert('Export failed');
        }
    };

    const toggleSelectAll = () => {
        if (allSelected.value) {
            props.products.forEach(item => selectedIds.value.delete(item.id));
            selectedIds.value = new Set(selectedIds.value);
        } else {
            const next = new Set(selectedIds.value);
            props.products.forEach(item => next.add(item.id));
            selectedIds.value = next;
        }
    };

    const toggleSelect = (id) => {
        const next = new Set(selectedIds.value);
        if (next.has(id)) next.delete(id);
        else next.add(id);
        selectedIds.value = next;
    };

    const clearSelection = () => {
        selectedIds.value = new Set();
    };

    const deleteSelected = async () => {
        const ids = Array.from(selectedIds.value);
        if (!ids.length) return;
        if (!confirm(`Delete ${ids.length} item(s)?`)) return;
        try {
            await axios.post(`/api/${props.exportEndpoint}/delete-bulk`, { ids });
            clearSelection();
            emit('reload');
        } catch {
            alert('Failed to delete some items');
        }
    };

    const jumpPage = ref(props.page);

    watch(() => props.page, (val) => {
        jumpPage.value = val
    });

    const goToPage = () => {
        const p = Number(jumpPage.value)
        if (p >= 1 && p <= (props.pagination?.last_page || 1)) {
            emit('page-change', p)
        }
    };

    const pageNumbers = computed(() => {
        const pages = []
        const last = props.pagination?.last_page || 0
        const current = props.pagination?.current_page || 1

        if (!last) return pages

        let addedLeftEllipsis = false
        let addedRightEllipsis = false

        for (let i = 1; i <= last; i++) {
            // Always show first 2, last 2, and 2 around current
            if (
                i <= 2 || 
                i > last - 2 || 
                (i >= current - 1 && i <= current + 1)
            ) {
                pages.push(i)
            } else if (i < current - 1 && !addedLeftEllipsis && i > 2) {
                pages.push('left-ellipsis')
                addedLeftEllipsis = true
            } else if (i > current + 1 && !addedRightEllipsis && i < last - 1) {
                pages.push('right-ellipsis')
                addedRightEllipsis = true
            }
        }

        return pages
    });

    const showingText = computed(() => {
        const pagination = props.pagination || {}
        if (!pagination.total || pagination.total === 0) return 'No products to show'

        const from = pagination.from || 1
        const to = pagination.to || pagination.data?.length || 0
        const total = pagination.total || 0

        return `Showing ${from} to ${to} of ${total} products`
    });
</script>

<style lang="scss" scoped>
.col-checkbox {
    width: 3%;
    white-space: nowrap;
    text-align: center;
    vertical-align: middle;
}
</style>

<template>
    <div class="table-responsive">
        <div v-if="selectedCount" class="d-flex gap-2 mb-2 p-2 bg-light border rounded">
            <span class="fw-bold small align-self-center">{{ selectedCount }} selected</span>
            <button v-if="auth.user?.role?.name !== 'super admin'" class="btn btn-sm btn-primary" @click="printSelected">
                <i class="bi bi-printer"></i> Print
            </button>
            <button v-if="auth.user?.role?.name !== 'super admin' && auth.user?.role?.name !== 'cashier' && auth.user?.role?.name !== 'manager' && auth.user?.role?.name !== 'warehouse staff'" class="btn btn-sm btn-danger" @click="deleteSelected">
                <i class="bi bi-trash"></i> Delete
            </button>
            <button v-if="auth.user?.role?.name !== 'super admin'" class="btn btn-sm btn-success" @click="confirmExportSelected">
                <i class="bi bi-file-earmark-excel-fill"></i> Excel
            </button>
            <button class="btn btn-sm btn-outline-secondary" @click="clearSelection">Clear</button>
        </div>
        <table class="table table-bordered table-striped" style="min-width: 800px;">
            <thead>
                <tr class="text-center">
                    <th class="col-checkbox">
                        <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" />
                    </th>
                    <th class="col-1">Barcode</th>
                    <th class="col-2">Product</th>
                    <th class="col-1">Purchase</th>
                    <th class="col-1">Variant</th>
                    <th class="col-1">Price</th>
                    <th class="col-1" v-if="auth.user?.role?.name !== 'super admin'">Action</th>
                </tr>
            </thead>
            <tbody id="list">
                <tr v-if="!barcodes || barcodes.length === 0">
                    <td colspan="10" class="text-center text-muted">
                        No data available.
                    </td>
                </tr>
                <tr v-for="bc in barcodes" :key="bc.id" class="text-center align-middle" style="font-size: 12px">
                    <td class="col-checkbox">
                        <input type="checkbox" :checked="selectedIds.has(bc.id)" @change="toggleSelect(bc.id)" />
                    </td>
                    <td><code>{{ bc.barcode }}</code></td>
                    <td class="text-start">
                        <div>{{ bc.product?.name || '-' }}</div>
                        <div class="text-muted small">{{ bc.product?.barcode || '' }}</div>
                    </td>
                    <td>{{ bc.purchase?.invoice_no || '-' }}</td>
                    <td>
                        <template v-if="bc.attributes && typeof bc.attributes === 'object' && Object.keys(bc.attributes).length">
                            <div v-for="(v, k) in bc.attributes" :key="k" class="small">{{ k }}: {{ v }}</div>
                        </template>
                        <span v-else class="text-muted">-</span>
                    </td>
                    <td class="text-start">
                        <div>Price: {{ bc.price }}</div>
                        <div>VAT: {{ bc.vat }}</div>
                        <div v-if="bc.discount">Disc: {{ bc.discount }}</div>
                        <div v-if="bc.point">Point: {{ bc.point }}</div>
                    </td>
                    <td v-if="auth.user?.role?.name !== 'super admin'">
                        <div class="btn-group btn-group-sm" role="group">
                            <button class="btn btn-sm btn-warning" @click="emit('edit-barcode', bc)" title="Edit barcode">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button v-if="auth.user?.role?.name !== 'super admin' && auth.user?.role?.name !== 'cashier' && auth.user?.role?.name !== 'manager' && auth.user?.role?.name !== 'warehouse staff'" class="btn btn-sm btn-danger" @click="emit('delete-barcode', bc)" title="Delete barcode">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        <div class="card card-body" style="min-width: 800px;">
            <nav aria-label="Page navigation">
                <div class="d-flex flex-nowrap align-items-center justify-content-between gap-3">
                    <div class="text-muted" style="font-size: 14px;">
                        {{ showingText }}
                    </div>

                    <ul class="pagination mb-0" v-if="pagination?.last_page > 1">
                        <li class="page-item" :class="{ disabled: pagination.current_page === 1 }">
                            <button class="page-link" @click="emit('page-change', pagination.current_page - 1)">Previous</button>
                        </li>
                        <li v-for="page in pageNumbers" :key="page" class="page-item"
                            :class="{ active: page === pagination.current_page, disabled: page.toString().includes('ellipsis') }">
                            <button v-if="typeof page === 'number'" class="page-link" @click="emit('page-change', page)">{{ page }}</button>
                            <span v-else class="page-link">&hellip;</span>
                        </li>
                        <li class="page-item" :class="{ disabled: pagination.current_page === pagination.last_page }">
                            <button class="page-link" @click="emit('page-change', pagination.current_page + 1)">Next</button>
                        </li>
                    </ul>

                    <div class="d-flex align-items-center gap-2" v-if="pagination?.last_page > 1">
                        <label class="form-label mb-0 text-nowrap" style="font-size: 13px;">Go to page:</label>
                        <input type="number" class="form-control form-control-sm" style="width: 70px;"
                            :min="1" :max="pagination.last_page" v-model.number="jumpPage" @keyup.enter="goToPage"/>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <label class="form-label mb-0 text-nowrap" style="font-size: 13px;">Per page:</label>
                        <select class="form-select form-select-sm" style="width: auto;"
                            :value="perPage" @change="emit('per-page-change', Number($event.target.value))">
                            <option :value="5">5</option>
                            <option :value="10">10</option>
                            <option :value="15">15</option>
                            <option :value="20">20</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                        </select>
                    </div>
                    <div class="d-flex justify-content-end mb-2">
                        <ExportButton v-if="auth.user?.role?.name !== 'super admin'" :endpoint="exportEndpoint" :filters="exportFilters" :asyncExport="true" :requireDays="true" @export-started="(file) => emit('export-started', file)" />
                    </div>
                </div>
            </nav>
        </div>
    </div>

    <div id="bulk-sticker-layout" class="sticker-layout"></div>
</template>

<script setup>
    import { computed, ref, watch, nextTick } from 'vue';
    import { useAuth } from '../../stores/auth';
    import ExportButton from '../../components/ExportButton.vue';
    import JsBarcode from 'jsbarcode';
    import axios from 'axios';

    const auth = useAuth();

    const props = defineProps({
        barcodes: { type: Array, default: () => [] },
        pagination: { type: Object, default: () => ({}) },
        perPage: { type: Number, default: 15 },
        page: { type: Number, default: 1 },
        exportEndpoint: { type: String, default: '' },
        exportFilters: { type: Object, default: () => ({}) },
    });

    const emit = defineEmits(['edit-barcode', 'delete-barcode', 'page-change', 'per-page-change', 'reload', 'export-started']);

    const selectedIds = ref(new Set());

    const selectedCount = computed(() => selectedIds.value.size);

    const confirmExportSelected = async () => {
        const ids = Array.from(selectedIds.value);
        if (!ids.length) return;
        if (confirm(`Are you sure you want to download ${ids.length} data?`)) {
            try {
                const res = await axios.get(`/api/${props.exportEndpoint}/export?ids=${ids.join(',')}`);
                emit('export-started', res.data.file);
            } catch (err) {
                alert(err.response?.data?.message || 'Export failed');
            }
        }
    };

    const allSelected = computed(() =>
        props.barcodes.length > 0 && props.barcodes.every(bc => selectedIds.value.has(bc.id))
    );

    const selectedBarcodes = computed(() =>
        props.barcodes.filter(bc => selectedIds.value.has(bc.id))
    );

    const toggleSelectAll = () => {
        if (allSelected.value) {
            props.barcodes.forEach(bc => selectedIds.value.delete(bc.id));
            selectedIds.value = new Set(selectedIds.value);
        } else {
            const next = new Set(selectedIds.value);
            props.barcodes.forEach(bc => next.add(bc.id));
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
        if (!confirm(`Delete ${ids.length} selected barcode(s)?`)) return;
        try {
            await axios.post('/api/barcodes/delete-bulk', { ids });
            clearSelection();
            emit('reload');
        } catch {
            alert('Failed to delete some barcodes');
        }
    };

    const printSelected = async () => {
        if (!selectedIds.value.size) return;
        const ids = Array.from(selectedIds.value);
        const container = document.getElementById('bulk-sticker-layout');
        container.innerHTML = '';
        ids.forEach(id => {
            const bc = props.barcodes.find(b => b.id === id);
            if (!bc) return;
            const div = document.createElement('div');
            div.className = 'sticker';
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            div.appendChild(svg);
            const code = document.createElement('div');
            code.className = 'sticker-barcode-text';
            code.textContent = bc.barcode;
            div.appendChild(code);
            const prod = document.createElement('div');
            prod.className = 'sticker-product';
            prod.textContent = bc.product?.name || '';
            div.appendChild(prod);
            const price = document.createElement('div');
            price.className = 'sticker-price';
            price.textContent = bc.price;
            div.appendChild(price);
            container.appendChild(div);
            try {
                JsBarcode(svg, bc.barcode, {
                    format: 'CODE128',
                    width: 1.5,
                    height: 40,
                    displayValue: false,
                    margin: 0,
                });
            } catch {}
        });
        window.print();
    };

    const jumpPage = ref(props.page);

    watch(() => props.page, (val) => { jumpPage.value = val });

    const goToPage = () => {
        const p = Number(jumpPage.value);
        if (p >= 1 && p <= (props.pagination?.last_page || 1)) {
            emit('page-change', p);
        }
    };

    const pageNumbers = computed(() => {
        const pages = [];
        const last = props.pagination?.last_page || 0;
        const current = props.pagination?.current_page || 1;
        if (!last) return pages;
        let leftEllipsis = false, rightEllipsis = false;
        for (let i = 1; i <= last; i++) {
            if (i <= 2 || i > last - 2 || (i >= current - 1 && i <= current + 1)) {
                pages.push(i);
            } else if (i < current - 1 && !leftEllipsis && i > 2) {
                pages.push('left-ellipsis');
                leftEllipsis = true;
            } else if (i > current + 1 && !rightEllipsis && i < last - 1) {
                pages.push('right-ellipsis');
                rightEllipsis = true;
            }
        }
        return pages;
    });

    const showingText = computed(() => {
        const p = props.pagination || {};
        if (!p.total || p.total === 0) return 'No barcodes to show';
        return `Showing ${p.from || 1} to ${p.to || 0} of ${p.total} barcodes`;
    });
</script>

<style lang="scss" scoped>
    .sticker-layout {
        display: none;
    }
</style>

<style lang="scss">
    @media print {
        @page { margin: 0; size: auto; }
        body { visibility: hidden; }
        body * { visibility: hidden; }
        #bulk-sticker-layout { visibility: visible; display: flex !important; flex-wrap: wrap; gap: 2px; position: absolute; top: 0; left: 0; width: 100%; padding: 5px; }
        #bulk-sticker-layout, #bulk-sticker-layout * { visibility: visible; }
        #bulk-sticker-layout .sticker { width: 1.5in; padding: 2px 4px; text-align: center; font-family: Arial, Helvetica, sans-serif; page-break-inside: avoid; break-inside: avoid; }
        #bulk-sticker-layout .sticker svg { max-width: 100%; height: auto; }
        #bulk-sticker-layout .sticker-barcode-text { font-size: 10px; font-weight: bold; letter-spacing: 1px; margin-top: 1px; }
        #bulk-sticker-layout .sticker-product { font-size: 7px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 1px; }
        #bulk-sticker-layout .sticker-price { font-size: 9px; font-weight: bold; margin-top: 1px; }
    }

    .col-checkbox {
        width: 1%;
        white-space: nowrap;
        text-align: center;
        vertical-align: middle;
    }

</style>

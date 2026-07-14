<template>
    <div style="overflow-x: auto;">
        <div style="min-width: 700px;">
            <div v-if="selectedCount" class="d-flex gap-2 mb-2 p-2 bg-light border rounded">
                <span class="fw-bold small align-self-center">{{ selectedCount }} selected</span>
                <button v-if="auth.user?.role?.name !== 'super admin'" class="btn btn-sm btn-danger" @click="deleteSelected">
                    <i class="bi bi-trash"></i> Delete
                </button>
                <button v-if="auth.user?.role?.name !== 'super admin'" class="btn btn-sm btn-success" @click="confirmExportSelected">
                    <i class="bi bi-file-earmark-excel-fill"></i> Excel
                </button>
                <button class="btn btn-sm btn-outline-secondary" @click="clearSelection">Clear</button>
            </div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" id="selectAll" />
                <label for="selectAll" class="small text-muted mb-0">Select all</label>
            </div>
            <div v-if="!groupedTemps || groupedTemps.length === 0" class="text-center text-muted py-4">
                No data available.
            </div>
            <div v-for="group in groupedTemps" :key="group.invoice_no" class="card mb-3 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <div class="d-flex align-items-center gap-3">
                        <input type="checkbox" :checked="selectedInvoices.has(group.invoice_no)" @change="toggleSelect(group.invoice_no)" class="me-1" />
                        <strong class="fs-6">{{ group.invoice_no }}</strong>
                        <span :class="statusBadge(group.items[0]?.status?.name)" class="badge">{{ group.items[0]?.status?.name || '-' }}</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-info">{{ group.items.length }} product(s)</span>
                        <div class="btn-group btn-group-sm" role="group">
                            <button v-if="auth.user?.role?.name !== 'super admin'" type="button" class="btn btn-info" @click="printGroup(group)" title="Print receipt"><i class="bi bi-printer"></i></button>
                            <button v-if="auth.user?.role?.name !== 'super admin'" type="button" class="btn btn-warning" @click="emit('edit-temp', group)" title="Edit invoice"><i class="bi bi-pencil-square"></i></button>
                            <button v-if="auth.user?.role?.name !== 'super admin'" class="btn btn-danger" @click="emit('delete-temp', group)"><i class="bi bi-trash" title="Delete invoice"></i></button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive mb-0">
                        <table class="table table-bordered table-striped mb-0">
                            <thead>
                                <tr class="text-center" style="font-size: 12px;">
                                    <th class="col-3">Product</th>
                                    <th class="col-2">Price</th>
                                    <th class="col-1">Qty</th>
                                    <th class="col-1">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="temp in group.items" :key="temp.id" style="font-size: 12px;">
                                    <td class="text-start">
                                        <div><i class="bi bi-bag-fill"></i> {{ temp.product?.name || '-' }}</div>
                                        <div v-if="temp.attributes && typeof temp.attributes === 'object'" class="mt-1">
                                            <template v-if="Array.isArray(temp.attributes)">
                                                <span v-for="grp in groupedAttrs(temp.attributes)" :key="grp.key" class="badge bg-light text-dark me-1 badge-attr">
                                                    {{ grp.key }}: {{ grp.values.join(', ') }}
                                                </span>
                                            </template>
                                            <template v-else>
                                                <span v-for="(v, k, i) in temp.attributes" :key="k" class="badge bg-light text-dark me-1 badge-attr">
                                                    {{ k }}: {{ v }}
                                                </span>
                                            </template>
                                        </div>
                                        <div><i class="bi bi-box-fill"></i> Unit: {{ temp.unit?.name || '-' }}</div>
                                        <div><i class="bi bi-person-fill"></i> {{ temp.client?.name || temp.client?.contact || '-' }}</div>
                                        <div><i class="bi bi-building-fill"></i> {{ temp.branch?.name || '-' }}</div>
                                        <div><i class="bi bi-person-badge-fill"></i> {{ temp.user?.name || '-' }}</div>
                                        <div v-if="temp.updated_by"><i class="bi bi-person-fill"></i> {{ temp.updated_by?.name || '-' }}</div>
                                    </td>
                                    <td class="text-start">
                                        <div><i class="bi bi-currency-dollar"></i> Price: {{ temp.price || '-' }}</div>
                                        <div><i class="bi bi-file-earmark-text-fill"></i> Vat: {{ temp.vat || '-' }}</div>
                                        <div><i class="bi bi-bookmark-dash-fill"></i> Discount: {{ temp.discount || '-' }}</div>
                                    </td>
                                    <td>{{ temp.qty || '-' }}</td>
                                    <td>{{ ((temp.qty * temp.price) + (temp.qty * (temp.vat || 0)) - (temp.qty * (temp.discount || 0))) || '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div v-if="group.remarks" class="card-footer py-2">
                    <div style="font-size: 12px;">
                        <strong>Description:</strong> {{ group.remarks }}
                    </div>
                </div>
                <ReceiptPrint
                    v-if="showPrintInvoiceNo === group.invoice_no"
                    v-bind="getReceiptProps(group)"
                    @close="showPrintInvoiceNo = null"
                />
            </div>
            <div class="card card-body">
                <nav aria-label="Page navigation">
                    <div class="d-flex flex-nowrap align-items-center justify-content-between gap-3">
                        <div class="text-muted" style="font-size: 14px;">
                            {{ showingText }}
                        </div>
    
                        <ul class="pagination mb-0" v-if="props.pagination?.last_page > 1">
                            <li class="page-item" :class="{ disabled: props.pagination.current_page === 1 }">
                                <button class="page-link" @click="emit('page-change', props.pagination.current_page - 1)">
                                    Previous
                                </button>
                            </li>
    
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
                        <div class="d-flex justify-content-end mb-2">
                            <ExportButton v-if="auth.user?.role?.name !== 'super admin'" :endpoint="exportEndpoint" :filters="exportFilters" :asyncExport="true" :requireDays="true" @export-started="emit('export-started', $event)" />
                        </div>
                    </div>
                </nav>
            </div>
        </div>
    </div>
</template>

<script setup>
    import { useAuth } from '../../stores/auth';
    const auth = useAuth();
    const props = defineProps({
        groupedTemps: { type: Array, default: () => [] },
        pagination: { type: Object, default: () => ({}) },
        perPage: { type: Number, default: 15 },
        page: { type: Number, default: 1 },
        exportEndpoint: { type: String, default: '' },
        exportFilters: { type: Object, default: () => ({}) },
    });

    const emit = defineEmits(['edit-temp', 'delete-temp', 'page-change', 'per-page-change', 'reload', 'export-started']);

    import { computed, ref, watch } from 'vue';
    import ExportButton from '../../components/ExportButton.vue';
    import ReceiptPrint from '../../components/ReceiptPrint.vue';
    import axios from 'axios';
    const jumpPage = ref(props.page);

    const showPrintInvoiceNo = ref(null);

    const confirmExportSelected = async () => {
        const ids = Array.from(selectedInvoices.value);
        if (!ids.length) return;
        if (confirm(`Are you sure you want to download ${ids.length} data?`)) {
            try {
                const res = await axios.get(`/api/${props.exportEndpoint}/export?invoice_nos=${ids.join(',')}`);
                emit('export-started', res.data.file);
            } catch (err) {
                alert(err.response?.data?.message || 'Export failed');
            }
        }
    };

    const exportableIds = computed(() => Array.from(selectedInvoices.value));

    const printGroup = (group) => {
        showPrintInvoiceNo.value = group.invoice_no;
    };

    const getReceiptProps = (group) => {
        const items = group.items.map(item => ({
            product_name: item.product?.name || '-',
            qty: item.qty,
            price: item.price,
            attributes: item.attributes || {}
        }));
        const subtotal = items.reduce((s, it) => s + (it.qty * it.price), 0);
        const totalVat = group.items.reduce((s, it) => s + (it.qty * (it.vat || 0)), 0);
        const totalDiscount = group.items.reduce((s, it) => s + (it.qty * (it.discount || 0)), 0);
        return {
            invoiceNo: group.invoice_no,
            date: group.items[0]?.created_at || '',
            branchName: group.items[0]?.branch?.name || '',
            clientName: group.items[0]?.client?.name || group.items[0]?.client?.contact || '',
            items,
            subtotal,
            totalVat,
            totalDiscount,
            grandTotal: subtotal + totalVat - totalDiscount,
            paidAmount: 0,
            dueAmount: 0,
        };
    };

    const selectedInvoices = ref(new Set());

    const selectedCount = computed(() => selectedInvoices.value.size);

    const allSelected = computed(() =>
        props.groupedTemps.length > 0 && props.groupedTemps.every(g => selectedInvoices.value.has(g.invoice_no))
    );

    const toggleSelectAll = () => {
        if (allSelected.value) {
            props.groupedTemps.forEach(g => selectedInvoices.value.delete(g.invoice_no));
            selectedInvoices.value = new Set(selectedInvoices.value);
        } else {
            const next = new Set(selectedInvoices.value);
            props.groupedTemps.forEach(g => next.add(g.invoice_no));
            selectedInvoices.value = next;
        }
    };

    const toggleSelect = (inv) => {
        const next = new Set(selectedInvoices.value);
        if (next.has(inv)) next.delete(inv);
        else next.add(inv);
        selectedInvoices.value = next;
    };

    const clearSelection = () => {
        selectedInvoices.value = new Set();
    };

    const deleteSelected = async () => {
        const invs = Array.from(selectedInvoices.value);
        if (!invs.length) return;
        if (!confirm(`Delete ${invs.length} invoice(s)?`)) return;
        try {
            await axios.post(`/api/${props.exportEndpoint}/delete-bulk`, { invoice_nos: invs });
            clearSelection();
            emit('reload');
        } catch {
            alert('Failed to delete some invoices');
        }
    };

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
        if (!pagination.total || pagination.total === 0) return 'No temps to show'

        const from = pagination.from || 1
        const to = pagination.to || pagination.data?.length || 0
        const total = pagination.total || 0

        return `Showing ${from} to ${to} of ${total} temps`
    });

    const statusBadge = (status) => {
        const map = {
            'completed': 'badge bg-success',
            'partial completed': 'badge bg-info text-dark',
            'pending': 'badge bg-warning text-dark',
            'cancelled': 'badge bg-danger',
        }
        return map[status] || 'badge bg-secondary'
    }

    const groupedAttrs = (attrs) => {
        const map = {};
        for (const attr of attrs) {
            if (!map[attr.key]) map[attr.key] = { key: attr.key, values: [] };
            if (!map[attr.key].values.includes(attr.value)) {
                map[attr.key].values.push(attr.value);
            }
        }
        return Object.values(map);
    };
</script>

<style lang="scss" scoped>
    .card-header .badge {
        font-size: 0.8rem;
    }
    .badge-attr {
        font-size: inherit;
    }
</style>

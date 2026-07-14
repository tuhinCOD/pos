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
        <table class="table table-bordered table-striped" style="min-width: 600px;">
            <thead>
                <tr class="text-center">
                    <th class="col-checkbox">
                        <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" />
                    </th>
                    <th class="col-2">Invoice</th>
                    <th class="col-1">Total</th>
                    <th class="col-1">Paid</th>
                    <th class="col-1">Due</th>
                    <th class="col-1">Status</th>
                    <th class="col-1" v-if="auth.user?.role?.name !== 'super admin'">Action</th>
                </tr>
            </thead>
            <tbody id="list">
                <tr v-if="!credits || credits.length === 0">
                    <td colspan="7" class="text-center text-muted">
                        No data available.
                    </td>
                </tr>
                <tr v-for="credit in props.credits" :key="credit.id" class="text-center align-middle" style="font-size: 12px">
                    <td class="col-checkbox"><input type="checkbox" :checked="selectedIds.has(credit.id)" @change="toggleSelect(credit.id)" /></td>
                    <td style="font-size: 12px;" class="text-start">
                        <div class="about-purchase">
                            <div>
                                <i class="bi bi-bag-fill"></i>
                                {{ credit.invoice_no || '-' }}
                            </div>
                            <div>
                                <i class="bi bi-person-fill"></i>
                                created: {{ credit.user?.name || '-' }}
                            </div>
                            <div>
                                <i class="bi bi-person-fill"></i>
                                updated by: {{ credit.updated_by?.name || '-' }}
                            </div>
                        </div>    
                    </td>
                    <td>{{ credit.total_amount }}</td>
                    <td>{{ credit.paid_amount }}</td>
                    <td>{{ credit.due_amount }}</td>
                    <td>
                        <span :class="dueBadge(credit.due_amount)">{{ credit.due_amount > 0 ? 'Due' : 'Paid' }}</span>
                    </td>
                    <td v-if="auth.user?.role?.name !== 'super admin'">
                        <div class="">
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-success" @click="emit('make-payment', credit)" :disabled="credit.due_amount <= 0" title="Make payment"><i class="bi bi-cash"></i></button>
                                <button v-if="auth.user?.role?.name !== 'super admin' && auth.user?.role?.name !== 'cashier' && auth.user?.role?.name !== 'manager' && auth.user?.role?.name !== 'warehouse staff'" class="btn btn-danger delete-btn" @click="emit('delete-credit', credit)" :disabled="credit.due_amount > 0"><i class="bi bi-trash" title="Delete credit"></i></button>
                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        <div class="card card-body" style="min-width: 600px;">
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
                    <div v-if="exportEndpoint">
                        <ExportButton v-if="auth.user?.role?.name !== 'super admin'" :endpoint="exportEndpoint" :filters="exportFilters" :asyncExport="true" :requireDays="true" @export-started="emit('export-started', $event)" />
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
        credits: { type: Array, default: () => [] },
        pagination: { type: Object, default: () => ({}) },
        perPage: { type: Number, default: 15 },
        page: { type: Number, default: 1 },
        exportEndpoint: { type: String, default: '' },
        exportFilters: { type: Object, default: () => ({}) },
    });

    const emit = defineEmits(['make-payment', 'delete-credit', 'page-change', 'per-page-change', 'reload', 'export-started']);

    import { computed, ref, watch } from 'vue';
    import axios from 'axios';

    const selectedIds = ref(new Set());

    const selectedCount = computed(() => selectedIds.value.size);

    const confirmExportSelected = async () => {
        const ids = Array.from(selectedIds.value);
        if (!ids.length) return;
        if (confirm(`Are you sure you want to download ${ids.length} data?`)) {
            try {
                const res = await axios.get(`/api/${props.exportEndpoint}/export?ids=${ids.join(',')}`);
                emit('export-started', res.data.file);
            } catch {
                alert('Export failed');
            }
        }
    };

    const allSelected = computed(() =>
        props.credits.length > 0 && props.credits.every(item => selectedIds.value.has(item.id))
    );

    const toggleSelectAll = () => {
        if (allSelected.value) {
            props.credits.forEach(item => selectedIds.value.delete(item.id));
            selectedIds.value = new Set(selectedIds.value);
        } else {
            const next = new Set(selectedIds.value);
            props.credits.forEach(item => next.add(item.id));
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
        if (!pagination.total || pagination.total === 0) return 'No sale credits to show'

        const from = pagination.from || 1
        const to = pagination.to || pagination.data?.length || 0
        const total = pagination.total || 0

        return `Showing ${from} to ${to} of ${total} sale credits`
    });

    const dueBadge = (due) => {
        return due > 0 ? 'badge bg-warning text-dark' : 'badge bg-success'
    }
</script>

<style lang="scss" scoped>
.col-checkbox {
    width: 1%;
    white-space: nowrap;
    text-align: center;
    vertical-align: middle;
}
</style>

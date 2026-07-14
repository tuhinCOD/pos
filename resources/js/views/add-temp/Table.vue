<template>
    <div class="card card-body p-0">
        <div class="card-header d-flex justify-content-between align-items-center bg-light">
            <h6 class="m-0">Pending Invoices</h6>
            <button class="btn btn-sm btn-outline-primary" @click="$emit('refresh')" title="Refresh">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
        <div class="list-group list-group-flush" style="max-height: calc(100vh - 200px); overflow-y: auto;">
            <div v-if="loading" class="text-center py-3 text-muted small">
                <div class="spinner-border spinner-border-sm" role="status"></div> Loading...
            </div>
            <div v-else-if="!props.temps.length" class="text-center py-3 text-muted small">
                No pending invoices.
            </div>
            <div
                v-for="group in props.temps"
                :key="group.invoice_no"
                class="list-group-item list-group-item-action px-3 py-2"
                style="cursor: pointer;"
                @click="$emit('load-invoice', group.invoice_no, group.items)"
            >
                <div class="d-flex justify-content-between align-items-center">
                    <strong class="small">{{ group.invoice_no }}</strong>
                    <span class="badge bg-warning text-dark">{{ group.items.length }} items</span>
                </div>
                <div class="small text-muted mt-1">
                    <span v-if="group.client">Client: {{ group.client?.name || group.client?.contact || 'N/A' }}</span>
                    <span v-if="group.branch"> | {{ group.branch?.name || '' }}</span>
                </div>
                <div class="small text-muted">
                    Total: {{ calculateTotal(group.items).toFixed(2) }}
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
    const props = defineProps({
        temps: { type: Array, default: () => [] },
        loading: { type: Boolean, default: false },
    });

    const emit = defineEmits(['load-invoice', 'refresh']);

    const calculateTotal = (items) => {
        return items.reduce((sum, item) => {
            return sum + (item.qty * item.price) + (item.qty * (item.vat || 0)) - (item.qty * (item.discount || 0));
        }, 0);
    };
</script>

<style lang="scss" scoped>
    .list-group-item:hover {
        background-color: #f0f7ff;
    }
    .list-group-item:active {
        background-color: #dbeafe;
    }
</style>

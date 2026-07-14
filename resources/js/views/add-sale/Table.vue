<template>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr class="text-center">
                    <th class="col-1">Id</th>
                    <th class="col-2">Invoice</th>
                    <th class="col-3">Product</th>
                    <th class="col-1">Qty</th>
                    <th class="col-1">Price</th>
                    <th class="col-1">VAT</th>
                    <th class="col-1">Discount</th>
                    <th class="col-1">Status</th>
                </tr>
            </thead>
            <tbody id="list">
                <tr v-for="sale in props.sales" class="text-center align-middle">
                    <td>{{ sale.id }}</td>
                    <td style="font-size: 12px;">{{ sale.invoice_no }}</td>
                    <td style="font-size: 12px;" class="text-start">
                        <div class="about-sale">
                            <div><i class="bi bi-box-fill"></i> {{ sale.product?.name || '-' }}</div>
                            <div><i class="bi bi-person-fill"></i> Client: {{ sale.client?.name || '-' }}</div>
                            <div><i class="bi bi-building-fill"></i> Branch: {{ sale.branch?.name || '-' }}</div>
                            <div><i class="bi bi-person-badge-fill"></i> By: {{ sale.user?.name || '-' }}</div>
                        </div>
                    </td>
                    <td>{{ sale.qty }}</td>
                    <td>{{ sale.price }}</td>
                    <td>{{ sale.vat }}</td>
                    <td>{{ sale.discount || '-' }}</td>
                    <td>
                        <span :class="statusBadge(sale.status?.name)">{{ sale.status?.name || '-' }}</span>
                    </td>
                </tr>
                <tr v-if="props.sales.length === 0">
                    <td colspan="8" class="text-center text-muted">No data available.</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
    const props = defineProps({
        sales: { type: Array, default: () => [] },
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
</script>

<style lang="scss" scoped>
</style>

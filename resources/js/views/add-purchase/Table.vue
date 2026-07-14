<template>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr class="text-center">
                    <th class="col-1">Id</th>
                    <th class="col-2">Invoice</th>
                    <th class="col-3">Product</th>
                    <th class="col-1">Unit</th>
                    <th class="col-1">Qty</th>
                    <th class="col-1">Price</th>
                    <th class="col-1">VAT</th>
                    <th class="col-1">Discount</th>
                    <th class="col-1">Status</th>
                </tr>
            </thead>
            <tbody id="list">
                <tr v-for="purchase in props.purchases" class="text-center align-middle">
                    <td>{{ purchase.id }}</td>
                    <td style="font-size: 12px;">{{ purchase.invoice_no }}</td>
                    <td style="font-size: 12px;" class="text-start">
                        <div class="about-purchase">
                            <div><i class="bi bi-box-fill"></i> {{ purchase.product?.name || '-' }}</div>
                            <div><i class="bi bi-person-fill"></i> Supplier: {{ purchase.supplier?.name || '-' }}</div>
                            <div><i class="bi bi-building-fill"></i> Branch: {{ purchase.branch?.name || '-' }}</div>
                            <div><i class="bi bi-person-badge-fill"></i> By: {{ purchase.user?.name || '-' }}</div>
                        </div>
                    </td>
                    <td>{{ purchase.unit?.name || '-' }}</td>
                    <td>{{ purchase.qty || purchase.product_unit_qty }}</td>
                    <td>{{ purchase.price }}</td>
                    <td>{{ purchase.vat }}</td>
                    <td>{{ purchase.discount || '-' }}</td>
                    <td>
                        <span :class="statusBadge(purchase.status?.name)">{{ purchase.status?.name || '-' }}</span>
                    </td>
                </tr>
                <tr v-if="props.purchases.length === 0">
                    <td colspan="9" class="text-center text-muted">No data available.</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
    const props = defineProps({
        purchases: { type: Array, default: () => [] },
    });

    const statusBadge = (status) => {
        const map = {
            'received': 'badge bg-success',
            'pending': 'badge bg-warning text-dark',
            'ordered': 'badge bg-info text-dark',
            'cancelled': 'badge bg-danger',
        }
        return map[status] || 'badge bg-secondary'
    }
</script>

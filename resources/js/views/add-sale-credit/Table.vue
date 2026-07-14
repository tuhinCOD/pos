<template>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr class="text-center">
                    <th>Id</th>
                    <th>Invoice</th>
                    <th>Total</th>
                    <th>Paid</th>
                    <th>Due</th>
                </tr>
            </thead>
            <tbody id="list">
                <tr v-if="!credits || credits.length === 0">
                    <td colspan="5" class="text-center text-muted">No data available.</td>
                </tr>
                <tr v-for="credit in credits" :key="credit.id" class="text-center align-middle" style="font-size: 12px">
                    <td>{{ credit.id }}</td>
                    <td>{{ credit.sale?.invoice_no || '-' }}</td>
                    <td>{{ credit.total_amount }}</td>
                    <td>{{ credit.paid_amount }}</td>
                    <td>{{ credit.due_amount }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
    const props = defineProps({
        credits: { type: Array, default: () => [] },
    });
</script>

<style lang="scss" scoped>
</style>

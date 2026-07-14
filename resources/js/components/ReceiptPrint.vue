<template>
    <div class="receipt-overlay" @click.self="$emit('close')">
        <div class="receipt-wrapper">
            <div ref="printArea" id="receipt-print-area" class="receipt p-3">
                <div class="receipt-header text-center mb-2">
                    <h4 v-if="company.name" class="fw-bold mb-0">{{ company.name }}</h4>
                    <p v-if="company.address" class="mb-0 small">{{ company.address }}</p>
                    <p v-if="company.contact || company.email" class="mb-0 small">
                        {{ company.contact }}<template v-if="company.contact && company.email"> | </template>{{ company.email }}
                    </p>
                    <hr class="my-2">
                    <h5 class="fw-bold mb-1">INVOICE</h5>
                    <div class="d-flex justify-content-between small px-1">
                        <span><strong>Invoice:</strong> {{ invoiceNo }}</span>
                        <span><strong>Date:</strong> {{ date }}</span>
                    </div>
                    <div class="d-flex justify-content-between small px-1">
                        <span v-if="branchName"><strong>Branch:</strong> {{ branchName }}</span>
                        <span v-if="clientName"><strong>Client:</strong> {{ clientName }}</span>
                    </div>
                    <hr class="my-2">
                </div>

                <table class="receipt-table w-100">
                    <thead>
                        <tr>
                            <th style="text-align:left;">Item</th>
                            <th style="text-align:center;">Qty</th>
                            <th style="text-align:right;">Price</th>
                            <th style="text-align:right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, idx) in items" :key="idx">
                            <td>
                                {{ item.product_name }}
                                <template v-if="item.attributes && Object.keys(item.attributes).length">
                                    <br><span class="text-muted" style="font-size:10px;">
                                        <span v-for="(v, k) in item.attributes" :key="k">{{ k }}: {{ v }} </span>
                                    </span>
                                </template>
                            </td>
                            <td style="text-align:center;">{{ item.qty }}</td>
                            <td style="text-align:right;">{{ Number(item.price).toFixed(2) }}</td>
                            <td style="text-align:right;">{{ (Number(item.qty) * Number(item.price)).toFixed(2) }}</td>
                        </tr>
                    </tbody>
                </table>

                <hr class="my-2">

                <div class="receipt-totals">
                    <div class="d-flex justify-content-between small">
                        <span>Sub Total:</span>
                        <span>{{ subtotal.toFixed(2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span>VAT:</span>
                        <span>{{ totalVat.toFixed(2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span>Discount:</span>
                        <span>({{ totalDiscount.toFixed(2) }})</span>
                    </div>
                    <div class="d-flex justify-content-between fw-bold fs-6 border-top pt-1 mt-1">
                        <span>Grand Total:</span>
                        <span>{{ grandTotal.toFixed(2) }}</span>
                    </div>
                    <div v-if="paidAmount" class="d-flex justify-content-between small text-success">
                        <span>Paid:</span>
                        <span>{{ Number(paidAmount).toFixed(2) }}</span>
                    </div>
                    <div v-if="dueAmount" class="d-flex justify-content-between small text-danger">
                        <span>Due:</span>
                        <span>{{ Number(dueAmount).toFixed(2) }}</span>
                    </div>
                </div>

                <div class="text-center text-muted small mt-3">
                    <p class="mb-0">Thank you for your business!</p>
                </div>
            </div>

            <div class="receipt-actions text-center mt-3">
                <button class="btn btn-primary me-2" v-print="'#receipt-print-area'">
                    <i class="bi bi-printer"></i> Print
                </button>
                <button class="btn btn-secondary" @click="$emit('close')">
                    Close
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
    import { ref, onMounted } from 'vue';
    import axios from 'axios';

    const props = defineProps({
        invoiceNo: { type: String, required: true },
        date: { type: String, default: '' },
        branchName: { type: String, default: '' },
        clientName: { type: String, default: '' },
        items: { type: Array, required: true },
        subtotal: { type: Number, default: 0 },
        totalVat: { type: Number, default: 0 },
        totalDiscount: { type: Number, default: 0 },
        grandTotal: { type: Number, default: 0 },
        paidAmount: { type: Number, default: 0 },
        dueAmount: { type: Number, default: 0 },
    });

    defineEmits(['close']);

    const company = ref({});

    const fetchCompany = async () => {
        try {
            const res = await axios.get('/api/storefront/company');
            if (res.data?.company) {
                company.value = res.data.company;
            }
        } catch {}
    };

    onMounted(() => {
        fetchCompany();
    });
</script>

<style>
    @media print {
        body * {
            visibility: hidden;
        }
        #receipt-print-area, #receipt-print-area * {
            visibility: visible;
        }
        #receipt-print-area {
            position: absolute;
            left: 0;
            top: 0;
            width: 80mm;
            padding: 10px;
        }
        .receipt-actions {
            display: none !important;
        }
        .receipt-overlay {
            position: static !important;
            background: none !important;
        }
        .receipt-wrapper {
            box-shadow: none !important;
            padding: 0 !important;
        }
    }
</style>

<style scoped>
    .receipt-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1050;
    }
    .receipt-wrapper {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        max-width: 420px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
    }
    .receipt {
        font-size: 13px;
        font-family: 'Courier New', 'Courier', monospace;
    }
    .receipt-table {
        border-collapse: collapse;
    }
    .receipt-table th {
        border-bottom: 1px dashed #333;
        padding: 3px 2px;
        font-size: 11px;
    }
    .receipt-table td {
        padding: 3px 2px;
        vertical-align: top;
    }
    .receipt-totals > div {
        padding: 2px 0;
    }
</style>

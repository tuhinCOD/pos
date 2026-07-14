<template>
    <div class="modal-backdrop" @click.self="$emit('close')">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-credit-card me-2"></i>Payment</h5>
                    <button type="button" class="btn-close" @click="$emit('close')"></button>
                </div>
                <form @submit.prevent="handlePayment">
                    <div class="modal-body pt-3">
                        <div class="summary-card rounded-3 p-3 mb-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-white-50 d-block">Invoice No</small>
                                    <strong class="text-white fs-6">{{ invoiceNo }}</strong>
                                </div>
                                <div class="text-end">
                                    <small class="text-white-50 d-block">Amount Due</small>
                                    <strong class="text-white fs-4">{{ currency }}{{ amount.toFixed(2) }}</strong>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold text-muted small text-uppercase">Payment Method <span class="text-danger">*</span></label>
                            <select class="form-select" v-model="form.method_id" required>
                                <option value="">-- Select Payment Method --</option>
                                <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
                            </select>
                            <div v-if="fieldErrors.method_id" class="form-text text-danger">{{ fieldErrors.method_id }}</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold text-muted small text-uppercase">Paid Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text fw-bold">{{ currency }}</span>
                                <input type="number" step="0.01" class="form-control form-control-lg" v-model.number="form.paid_amount" min="0.01" placeholder="0.00" required />
                            </div>
                            <div v-if="fieldErrors.paid_amount" class="form-text text-danger">{{ fieldErrors.paid_amount }}</div>
                            <div v-if="form.paid_amount > 0" class="form-text d-flex align-items-center gap-1 mt-1">
                                <i :class="change >= 0 ? 'bi bi-check-circle-fill text-success' : 'bi bi-exclamation-triangle-fill text-warning'"></i>
                                <span :class="change >= 0 ? 'text-success fw-semibold' : 'text-warning fw-semibold'">
                                    {{ change === 0 ? 'Paid' : change > 0 ? 'Change: ' + currency + ' ' + change.toFixed(2) : 'Due: ' + currency + ' ' + Math.abs(change).toFixed(2) }}
                                </span>
                            </div>
                        </div>

                        <div class="mb-3" v-if="isBkash">
                            <label class="form-label fw-semibold text-muted small text-uppercase">Payer Number (bKash) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" v-model="form.payer_no" placeholder="017XXXXXXXX" maxlength="14" />
                            <div v-if="fieldErrors.payer_no" class="form-text text-danger">{{ fieldErrors.payer_no }}</div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold text-muted small text-uppercase">Payment Date</label>
                                <input type="date" class="form-control" v-model="form.payment_date" required />
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold text-muted small text-uppercase">Transaction ID</label>
                                <input type="text" class="form-control" v-model="form.trx_id" placeholder="Optional" />
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold text-muted small text-uppercase">Note</label>
                            <textarea class="form-control" v-model="form.note" placeholder="Add a note..." rows="2"></textarea>
                        </div>

                        <div v-if="errorMsg" class="alert alert-danger d-flex align-items-center gap-2 py-2 mb-0">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <span>{{ errorMsg }}</span>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light px-4" @click="$emit('close')">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4" :disabled="processing">
                            <span v-if="processing" class="spinner-border spinner-border-sm me-1"></span>
                            <i v-else class="bi bi-check-lg me-1"></i>
                            {{ processing ? 'Processing...' : 'Pay ' + currency + ' ' + (form.paid_amount || 0).toFixed(2) }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
    import { reactive, ref, computed } from 'vue';
    import axios from 'axios';

    const props = defineProps({
        invoiceNo: { type: String, required: true },
        amount: { type: Number, required: true },
        paymentMethods: { type: Array, default: () => [] },
        paymentType: { type: String, default: 'sale' },
    });

    const emit = defineEmits(['close', 'payment-completed']);

    const form = reactive({
        method_id: '',
        paid_amount: 0,
        trx_id: '',
        payer_no: '',
        note: '',
        payment_date: new Date().toISOString().split('T')[0],
    });

    const fieldErrors = reactive({
        method_id: '',
        paid_amount: '',
        payer_no: '',
    });

    const processing = ref(false);
    const errorMsg = ref('');
    const currency = '$';

    const selectedMethod = computed(() => {
        return props.paymentMethods.find(m => Number(m.id) === Number(form.method_id));
    });

    const isBkash = computed(() => {
        return selectedMethod.value?.name?.toLowerCase() === 'bkash';
    });

    const change = computed(() => {
        return Number(form.paid_amount) - props.amount;
    });

    const handlePayment = async () => {
        fieldErrors.method_id = '';
        fieldErrors.paid_amount = '';
        fieldErrors.payer_no = '';
        errorMsg.value = '';

        if (!form.method_id) {
            fieldErrors.method_id = 'Please select a payment method';
            return;
        }
        if (!form.paid_amount || form.paid_amount <= 0) {
            fieldErrors.paid_amount = 'Please enter a valid amount';
            return;
        }
        if (isBkash.value && !form.payer_no) {
            fieldErrors.payer_no = 'Please enter the payer bKash number';
            return;
        }
        if (form.paid_amount > props.amount) {
            errorMsg.value = `Paid amount (${currency}${form.paid_amount}) cannot exceed the total (${currency}${props.amount}).`;
            return;
        }
        if (form.paid_amount < props.amount) {
            if (!confirm(`Paid amount (${currency}${form.paid_amount}) is less than the total (${currency}${props.amount}). Continue?`)) {
                return;
            }
        }
        processing.value = true;

        try {
            const payload = {
                payment_invoice_no: props.invoiceNo,
                amount: form.paid_amount,
                payment_method_id: form.method_id,
                payment_type: props.paymentType,
                trx_id: form.trx_id || '',
                note: form.note || '',
                payment_date: form.payment_date,
            };
            if (isBkash.value) {
                payload.payer_no = form.payer_no;
            }
            await axios.post('/api/v1/payments', payload);
            emit('payment-completed', form.paid_amount);
        } catch (err) {
            errorMsg.value = err.response?.data?.message || 'Payment failed';
        }
        processing.value = false;
    };
</script>

<style scoped>
    .modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(4px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1050;
        padding: 1rem;
        overflow-y: auto;
    }
    .modal-dialog {
        width: 100%;
        max-width: 480px;
        margin: auto;
    }
    .modal-content {
        border: none;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        overflow-y: auto;
        max-height: 90vh;
    }
    .modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
        padding: 1.25rem 1.5rem 0.5rem;
    }
    .modal-header .btn-close {
        filter: brightness(0) invert(1);
        opacity: 0.7;
    }
    .modal-header .btn-close:hover {
        opacity: 1;
    }
    .modal-header .modal-title {
        font-size: 1.15rem;
    }
    .modal-body {
        padding: 1.25rem 1.5rem;
    }
    .modal-footer {
        padding: 0.75rem 1.5rem 1.25rem;
    }
    .summary-card {
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    .form-label {
        margin-bottom: 0.35rem;
        letter-spacing: 0.3px;
    }
    .form-control:focus,
    .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }
    .input-group-text {
        background: #f1f5f9;
        border-right: none;
    }
    .input-group .form-control {
        border-left: none;
    }
    .input-group .form-control:focus {
        border-left: none;
    }
    .btn {
        border-radius: 10px;
        font-weight: 600;
    }
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
    }
    .btn-primary:hover:not(:disabled) {
        background: linear-gradient(135deg, #5a6fd6 0%, #6a4192 100%);
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }
    .btn-primary:disabled {
        opacity: 0.65;
    }
    .btn-light {
        background: #f1f5f9;
        border: none;
    }
    .btn-light:hover {
        background: #e2e8f0;
    }
    .alert {
        border-radius: 10px;
    }
</style>

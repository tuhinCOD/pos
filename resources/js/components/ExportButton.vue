<template>
    <button v-if="requireDays" class="btn btn-success btn-sm" title="Download Excel" @click="showModal = true">
        <i class="bi bi-file-earmark-excel-fill me-1"></i> Excel
    </button>
    <button v-else class="btn btn-success btn-sm" title="Download Excel" @click="confirmExport" :disabled="processing">
        <span v-if="processing" class="spinner-border spinner-border-sm me-1"></span>
        <i v-else class="bi bi-file-earmark-excel-fill me-1"></i>
        {{ processing ? 'Exporting...' : 'Excel' }}
    </button>

    <div v-if="showModal" class="modal-backdrop" @click.self="showModal = false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-excel-fill me-2"></i>Export Data</h5>
                    <button type="button" class="btn-close" @click="showModal = false"></button>
                </div>
                <form @submit.prevent="handleExport">
                    <div class="modal-body pt-3">
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-muted small text-uppercase">Days</label>
                            <input type="number" class="form-control" v-model.number="days" min="1" placeholder="Enter number of days" />
                            <small class="text-muted">Export data from the last N days</small>
                        </div>
                        <div v-if="errorMsg" class="alert alert-danger d-flex align-items-center gap-2 py-2 mb-0">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <span>{{ errorMsg }}</span>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light px-4" @click="showModal = false">Cancel</button>
                        <button type="submit" class="btn btn-success px-4" :disabled="processing">
                            <span v-if="processing" class="spinner-border spinner-border-sm me-1"></span>
                            <i v-else class="bi bi-download me-1"></i>
                            {{ processing ? 'Downloading...' : 'Download' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';

const emit = defineEmits(['export-started']);

const props = defineProps({
    endpoint: { type: String, required: true },
    filters: { type: Object, default: () => ({}) },
    requireDays: { type: Boolean, default: false },
    asyncExport: { type: Boolean, default: false },
});

const showModal = ref(false);
const days = ref(30);
const processing = ref(false);
const errorMsg = ref('');

const confirmExport = async () => {
    if (props.asyncExport) {
        if (!confirm('download all data?')) return;
        processing.value = true;
        errorMsg.value = '';
        try {
            const params = new URLSearchParams();
            Object.entries(props.filters).forEach(([key, value]) => {
                if (value !== null && value !== undefined && value !== '') {
                    if (Array.isArray(value) && value.length) {
                        params.append(key, value.join(','));
                    } else if (!Array.isArray(value)) {
                        params.append(key, value);
                    }
                }
            });
            const res = await axios.get(`/api/${props.endpoint}/export`, { params });
            emit('export-started', res.data.file);
        } catch (err) {
            errorMsg.value = err.response?.data?.message || 'Export failed';
        }
        processing.value = false;
    } else {
        if (confirm('Download all data?')) {
            window.location.href = exportUrl.value;
        }
    }
};

const exportUrl = computed(() => {
    const params = new URLSearchParams();
    Object.entries(props.filters).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            if (Array.isArray(value) && value.length) {
                params.append(key, value.join(','));
            } else if (!Array.isArray(value)) {
                params.append(key, value);
            }
        }
    });
    const qs = params.toString();
    return `/api/${props.endpoint}/export${qs ? '?' + qs : ''}`;
});

const handleExport = async () => {
    if (!days.value || days.value < 1) {
        errorMsg.value = 'Please enter a valid number of days';
        return;
    }

    processing.value = true;
    errorMsg.value = '';

    try {
        const params = { ...props.filters, days: days.value };
        const res = await axios.get(`/api/${props.endpoint}/export`, { params });

        if (props.asyncExport) {
            emit('export-started', res.data.file);
        }

        showModal.value = false;
        days.value = 30;
    } catch (err) {
        errorMsg.value = err.response?.data?.message || 'Export failed';
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
    max-width: 420px;
    margin: auto;
}
.modal-content {
    border: none;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}
.modal-header {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
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
.modal-body {
    padding: 1.25rem 1.5rem;
}
.modal-footer {
    padding: 0.75rem 1.5rem 1.25rem;
}
.form-control:focus {
    border-color: #28a745;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}
.btn {
    border-radius: 10px;
    font-weight: 600;
}
.btn-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border: none;
}
.btn-success:hover:not(:disabled) {
    background: linear-gradient(135deg, #218838 0%, #1ba87e 100%);
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
}
.btn-success:disabled {
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

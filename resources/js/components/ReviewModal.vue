<template>
    <div class="modal-backdrop" @click.self="$emit('close')">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-star me-2"></i>Review Product</h5>
                    <button type="button" class="btn-close" @click="$emit('close')"></button>
                </div>
                <form @submit.prevent="handleSubmit">
                    <div class="modal-body pt-3">
                        <div class="d-flex align-items-center gap-3 mb-4 p-3 rounded-3" style="background:#f8fafc;">
                            <img
                                :src="productImage"
                                class="rounded"
                                style="width:56px;height:56px;object-fit:cover;"
                            >
                            <div>
                                <h6 class="fw-bold mb-1" style="font-size:0.9rem">{{ productName }}</h6>
                            </div>
                        </div>

                        <div class="mb-4 text-center">
                            <label class="form-label fw-semibold text-muted small text-uppercase mb-2">Rating</label>
                            <div class="d-flex justify-content-center gap-1">
                                <button
                                    v-for="star in 5"
                                    :key="star"
                                    type="button"
                                    class="star-btn"
                                    @click="form.rating = star"
                                >
                                    <i
                                        class="bi"
                                        :class="star <= form.rating ? 'bi-star-fill text-warning' : 'bi-star text-secondary'"
                                        style="font-size:1.8rem;"
                                    ></i>
                                </button>
                            </div>
                            <div v-if="fieldErrors.rating" class="form-text text-danger">{{ fieldErrors.rating }}</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold text-muted small text-uppercase">Review</label>
                            <textarea
                                class="form-control"
                                v-model="form.review"
                                rows="3"
                                placeholder="Share your thoughts about this product..."
                                maxlength="500"
                            ></textarea>
                            <div class="d-flex justify-content-between mt-1">
                                <div v-if="fieldErrors.review" class="form-text text-danger">{{ fieldErrors.review }}</div>
                                <small class="text-muted">{{ form.review.length }}/500</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold text-muted small text-uppercase">Images <small class="text-muted">(optional)</small></label>
                            <div class="border rounded-3 p-3" style="background:#f8fafc;">
                                <div v-if="previews.length" class="d-flex flex-wrap gap-2 mb-2">
                                    <div v-for="(preview, idx) in previews" :key="idx" class="position-relative">
                                        <img :src="preview" class="rounded" style="width:64px;height:64px;object-fit:cover;">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-danger rounded-circle position-absolute"
                                            style="top:-6px;right:-6px;width:20px;height:20px;padding:0;font-size:10px;line-height:1;"
                                            @click="removeImage(idx)"
                                        >
                                            &times;
                                        </button>
                                    </div>
                                </div>
                                <label class="upload-btn d-flex flex-column align-items-center justify-content-center rounded-3 cursor-pointer"
                                    style="border:2px dashed #d1d5db;padding:1rem;cursor:pointer;"
                                >
                                    <i class="bi bi-camera fs-3 text-muted"></i>
                                    <small class="text-muted mt-1">Click to upload images</small>
                                    <input
                                        type="file"
                                        accept="image/jpeg,image/png,image/jpg"
                                        multiple
                                        class="d-none"
                                        @change="handleFiles"
                                    />
                                </label>
                            </div>
                        </div>

                        <div v-if="errorMsg" class="alert alert-danger d-flex align-items-center gap-2 py-2 mb-0">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <span>{{ errorMsg }}</span>
                        </div>
                        <div v-if="successMsg" class="alert alert-success d-flex align-items-center gap-2 py-2 mb-0">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>{{ successMsg }}</span>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light px-4" @click="$emit('close')">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4" :disabled="processing">
                            <span v-if="processing" class="spinner-border spinner-border-sm me-1"></span>
                            <i v-else class="bi bi-check-lg me-1"></i>
                            {{ processing ? 'Submitting...' : 'Submit Review' }}
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
        productId: { type: [Number, String], required: true },
        productName: { type: String, default: '' },
        productImage: { type: String, default: '' },
        clientId: { type: [Number, String], required: true },
    });

    const emit = defineEmits(['close', 'review-submitted']);

    const form = reactive({
        rating: 0,
        review: '',
    });

    const fieldErrors = reactive({
        rating: '',
        review: '',
    });

    const files = ref([]);
    const previews = ref([]);
    const processing = ref(false);
    const errorMsg = ref('');
    const successMsg = ref('');

    const handleFiles = (e) => {
        const selected = Array.from(e.target.files || []);
        selected.forEach(file => {
            files.value.push(file);
            const reader = new FileReader();
            reader.onload = (ev) => {
                previews.value.push(ev.target.result);
            };
            reader.readAsDataURL(file);
        });
        e.target.value = '';
    };

    const removeImage = (idx) => {
        files.value.splice(idx, 1);
        previews.value.splice(idx, 1);
    };

    const handleSubmit = async () => {
        fieldErrors.rating = '';
        fieldErrors.review = '';
        errorMsg.value = '';
        successMsg.value = '';

        if (!form.rating) {
            fieldErrors.rating = 'Please select a rating';
            return;
        }
        if (!form.review.trim()) {
            fieldErrors.review = 'Please write a review';
            return;
        }

        processing.value = true;

        try {
            const fd = new FormData();
            fd.append('product', props.productId);
            fd.append('rating', form.rating);
            fd.append('review', form.review);
            fd.append('client', props.clientId);

            files.value.forEach(f => fd.append('images[]', f));

            await axios.post('/api/product_reviews', fd, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });

            successMsg.value = 'Review submitted successfully!';
            setTimeout(() => {
                emit('review-submitted');
                emit('close');
            }, 1500);
        } catch (err) {
            errorMsg.value = err.response?.data?.message || 'Failed to submit review';
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
    .modal-body {
        padding: 1.25rem 1.5rem;
    }
    .modal-footer {
        padding: 0.75rem 1.5rem 1.25rem;
    }
    .star-btn {
        background: none;
        border: none;
        padding: 0;
        cursor: pointer;
        transition: transform 0.15s;
    }
    .star-btn:hover {
        transform: scale(1.15);
    }
    .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
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
    .upload-btn:hover {
        border-color: #667eea !important;
        background: rgba(102, 126, 234, 0.05);
    }
    .cursor-pointer {
        cursor: pointer;
    }
</style>

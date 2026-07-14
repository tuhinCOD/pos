import axios from "axios";
import { defineStore } from "pinia";

export const useBarcode = defineStore("dashBarcode", {
    state: () => ({
        barcodes: [],
        purchases: [],
        products: [],
        branches: [],
        units: [],
        pagination: {},
        loading: false,
        errors: null,
        message: ""
    }),

    getters: {
        allBarcodes: (state) => state.barcodes,
        allPurchases: (state) => state.purchases,
        allProducts: (state) => state.products,
        allBranches: (state) => state.branches,
        allUnits: (state) => state.units,
        allPaginations: (state) => state.pagination,
    },

    actions: {
        async loadBarcodes(filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const params = { page, perPage }
            if (filters.search) params.search = filters.search
            if (filters.product_id) params.product_id = filters.product_id
            if (filters.purchase_id) params.purchase_id = filters.purchase_id
            if (filters.branch_id) params.branch_id = filters.branch_id
            const result = await axios("/api/barcodes", { params })
            .then((res) => {
                this.barcodes = res.data.barcodes?.data || [];
                this.pagination = res.data.barcodes;
                this.branches = res.data.branches || [];
                this.products = res.data.products || [];
                this.purchases = res.data.purchases || [];
                this.units = res.data.units || [];
            })
            .catch((errors) => {
                console.log(errors);
            })
            .finally(() => {
                this.loading = false;
            });

            return result;
        },

        async generateBarcodes(payload) {
            this.loading = true;
            const result = await axios.post("/api/barcodes/generate", payload)
            .then((res) => {
                return { status: 'success', data: res.data };
            })
            .catch ((error) => {
                if (error.response?.status === 422) {
                    return { status: 'error', errors: error.response.data.errors, message: error.response.data.message };
                }
                if (error.response?.status === 409) {
                    return { status: 'error', message: error.response.data.message };
                }
                return { status: 'error', message: error.message };
            })
            .finally(() => {
                this.loading = false;
            });

            return result;
        },

        async deleteBarcode(id) {
            this.loading = true;
            const result = await axios.post(`/api/barcodes/delete/${id}`)
            .then((res) => {
                return { status: 'success', data: res.data };
            })
            .catch ((error) => {
                return { status: 'error', message: error.message };
            })
            .finally(() => {
                this.loading = false;
            });

            return result;
        },

        async getBarcodesByPurchase(purchaseId) {
            const result = await axios(`/api/barcodes/by-purchase/${purchaseId}`)
            .then((res) => {
                return { status: 'success', data: res.data };
            })
            .catch ((error) => {
                return { status: 'error', message: error.message };
            });

            return result;
        },

        async getBarcodesByProduct(productId) {
            const result = await axios(`/api/barcodes/by-product/${productId}`)
            .then((res) => {
                return { status: 'success', data: res.data };
            })
            .catch ((error) => {
                return { status: 'error', message: error.message };
            });

            return result;
        },

        async getBarcode(id) {
            const result = await axios(`/api/barcodes/${id}`)
            .then((res) => {
                return { status: 'success', data: res.data };
            })
            .catch ((error) => {
                return { status: 'error', message: error.message };
            });

            return result;
        },

        async updateBarcode(id, data) {
            this.loading = true;
            const result = await axios.post(`/api/barcodes/update/${id}`, data)
            .then((res) => {
                return { status: 'success', data: res.data };
            })
            .catch ((error) => {
                if (error.response?.status === 422) {
                    return { status: 'error', errors: error.response.data.errors };
                }
                return { status: 'error', message: error.message };
            })
            .finally(() => {
                this.loading = false;
            });

            return result;
        },
    }
});

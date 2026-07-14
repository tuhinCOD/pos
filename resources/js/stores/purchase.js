import axios from "axios";
import { defineStore } from "pinia";

export const usePurchase = defineStore("dashPurchase", {
    state: () => ({
        purchases: [],
        editPurchase: null,
        statuses: [],
        branches: [],
        products: [],
        suppliers: [],
        units: [],
        pagination: {},
        todayPurchases: [],
        loading: false,
        errors: null,
        message: ""
    }),

    getters: {
        allPurchases: (state) => state.purchases,
        allEditPurchase: (state) => state.editPurchase,
        allStatuses: (state) => state.statuses,
        allBranches: (state) => state.branches,
        allProducts: (state) => state.products,
        allSuppliers: (state) => state.suppliers,
        allUnits: (state) => state.units,
        allPaginations: (state) => state.pagination,
        allTodayPurchases: (state) => state.todayPurchases,
    },

    actions: {
        async loadPurchases(filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const params = { page, perPage }
            if (filters.search) params.search = filters.search
            if (filters.products) params.products = filters.products
            if (filters.status) params.status = filters.status
            if (filters.branch) params.branch = filters.branch
            const result = await axios("/api/purchases", { params })
            .then((res) => {
                this.purchases = res.data.purchases?.data || [];
                this.pagination = res.data.purchases;
                this.editPurchase = null;
                this.statuses = res.data.statuses || [];
                this.branches = res.data.branches || [];
                this.products = res.data.products || [];
                this.suppliers = res.data.suppliers || [];
                this.units = res.data.unit || [];
            })
            .catch((errors) => {
                console.log(errors);
            })
            .finally(() => {
                this.loading = false;
            });

            return result;
        },

        async loadTodayPurchases() {
            const result = await axios("/api/purchases/today")
            .then((res) => {
                this.todayPurchases = res.data.purchases || [];
            })
            .catch((errors) => {
                console.log(errors);
            });

            return result;
        },

        async getPurchasesByInvoice(invoiceNo) {
            const result = await axios(`/api/purchases/by-invoice/${invoiceNo}`)
            .then((res) => {
                return { status: 'success', data: res.data.purchases || [] };
            })
            .catch((error) => {
                return { status: 'error', message: error.message };
            });

            return result;
        },

        async createPurchase(purchaseData) {
            this.loading = true;
            const result = await axios.post("/api/purchases", purchaseData)
            .then((res) => {
                this.loadTodayPurchases();
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

        async updatePurchase(id, purchaseData) {
            this.loading = true;
            const result = await axios.post(`/api/purchases/update/${id}`, purchaseData)
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

        async deletePurchase(id, filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const result = await axios.post(`/api/purchases/delete/${id}`)
            .then((res) => {
                this.loadPurchases(filters, page, perPage);
                return { status: 'success' };
            }) 
            .catch ((error) => {
                return { status: 'error', message: error.message };
            })
            .finally(() => {
                this.loading = false;
            });

            return result;
        },

        async deletePurchasesByInvoice(invoiceNo, filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const result = await axios.post('/api/purchases/delete-by-invoice', { invoice_no: invoiceNo })
            .then((res) => {
                this.loadPurchases(filters, page, perPage);
                return { status: 'success' };
            })
            .catch ((error) => {
                return { status: 'error', message: error.message };
            })
            .finally(() => {
                this.loading = false;
            });

            return result;
        },

        async getPurchase(id) {
            const result = await axios(`/api/purchases/${id}`)
            .then((res) => {
                return { status: 'success', data: res.data };
            })
            .catch ((error) => {
                return { status: 'error', message: error.message };
            });

            return result;
        },
    }
});
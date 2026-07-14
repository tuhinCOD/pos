import axios from "axios";
import { defineStore } from "pinia";

export const usePurchaseReturn = defineStore("dashPurchaseReturn", {
    state: () => ({
        supplierReturns: [],
        editReturn: null,
        statuses: [],
        products: [],
        purchases: [],
        units: [],
        branches: [],
        pagination: {},
        loading: false,
        errors: null,
        message: ""
    }),

    getters: {
        allReturns: (state) => state.supplierReturns,
        allEditReturn: (state) => state.editReturn,
        allStatuses: (state) => state.statuses,
        allProducts: (state) => state.products,
        allPurchases: (state) => state.purchases,
        allUnits: (state) => state.units,
        allBranches: (state) => state.branches,
        allPaginations: (state) => state.pagination,
    },

    actions: {
        async loadReturns(filters = {}, page = 1, perPage = 20) {
            this.loading = true;
            const params = { page, perPage }
            if (filters.search) params.search = filters.search
            if (filters.products) params.products = filters.products
            if (filters.status) params.status = filters.status
            if (filters.branch) params.branch = filters.branch
            const result = await axios("/api/supplier_returns", { params })
            .then((res) => {
                this.supplierReturns = res.data.supplierReturns?.data || [];
                this.pagination = res.data.supplierReturns;
                this.editReturn = null;
                this.statuses = res.data.statuses || [];
                this.products = res.data.products || [];
                this.purchases = res.data.purchases || [];
                this.units = res.data.units || [];
                this.branches = res.data.branches || [];
            })
            .catch((errors) => {
                console.log(errors);
            })
            .finally(() => {
                this.loading = false;
            });

            return result;
        },

        async createReturn(returnData) {
            this.loading = true;
            const result = await axios.post("/api/supplier_returns", returnData)
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

        async updateReturn(id, returnData) {
            this.loading = true;
            const result = await axios.post(`/api/supplier_returns/update/${id}`, returnData)
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

        async deleteReturn(id, filters = {}, page = 1, perPage = 20) {
            this.loading = true;
            const result = await axios.post(`/api/supplier_returns/delete/${id}`)
            .then((res) => {
                this.loadReturns(filters, page, perPage);
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

        async getReturn(id) {
            this.loading = true;
            const result = await axios(`/api/supplier_returns/${id}`)
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
    }
});

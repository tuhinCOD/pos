import axios from "axios";
import { defineStore } from "pinia";

export const useClientReturn = defineStore("dashClientReturn", {
    state: () => ({
        clientReturns: [],
        editClientReturn: null,
        statuses: [],
        products: [],
        branches: [],
        units: [],
        sales: [],
        pagination: {},
        loading: false,
        errors: null,
        message: ""
    }),

    getters: {
        allClientReturns: (state) => state.clientReturns,
        allEditClientReturn: (state) => state.editClientReturn,
        allStatuses: (state) => state.statuses,
        allProducts: (state) => state.products,
        allBranches: (state) => state.branches,
        allUnits: (state) => state.units,
        allSales: (state) => state.sales,
        allPaginations: (state) => state.pagination,
    },

    actions: {
        async loadClientReturns(filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const params = { page, perPage }
            if (filters.search) params.search = filters.search
            if (filters.products) params.products = filters.products
            if (filters.status) params.status = filters.status
            if (filters.branch) params.branch = filters.branch
            const result = await axios("/api/client_returns", { params })
            .then((res) => {
                this.clientReturns = res.data.clientReturns?.data || [];
                this.pagination = res.data.clientReturns;
                this.editClientReturn = null;
                this.statuses = res.data.statuses || [];
                this.products = res.data.products || [];
                this.branches = res.data.branches || [];
                this.units = res.data.units || [];
                this.sales = res.data.sales || [];
            })
            .catch((errors) => {
                console.log(errors);
            })
            .finally(() => {
                this.loading = false;
            });

            return result;
        },

        async createClientReturn(returnData) {
            this.loading = true;
            const result = await axios.post("/api/client_returns", returnData)
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

        async updateClientReturn(id, returnData) {
            this.loading = true;
            const result = await axios.post(`/api/client_returns/update/${id}`, returnData)
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

        async deleteClientReturn(id, filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const result = await axios.post(`/api/client_returns/delete/${id}`)
            .then((res) => {
                this.loadClientReturns(filters, page, perPage);
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

        async getClientReturn(id) {
            const result = await axios(`/api/client_returns/${id}`)
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

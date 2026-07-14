import axios from "axios";
import { defineStore } from "pinia";

export const useTemp = defineStore("dashTemp", {
    state: () => ({
        temps: [],
        editTemp: null,
        statuses: [],
        branches: [],
        products: [],
        productPrices: [],
        clients: [],
        units: [],
        barcodes: [],
        pagination: {},
        todayTemps: [],
        loading: false,
        errors: null,
        message: ""
    }),

    getters: {
        allTemps: (state) => state.temps,
        allEditTemp: (state) => state.editTemp,
        allStatuses: (state) => state.statuses,
        allBranches: (state) => state.branches,
        allProducts: (state) => state.products,
        allProductPrices: (state) => state.productPrices,
        allClients: (state) => state.clients,
        allUnits: (state) => state.units,
        allBarcodes: (state) => state.barcodes,
        allPaginations: (state) => state.pagination,
        allTodayTemps: (state) => state.todayTemps,
    },

    actions: {
        async loadGroupedTemps(filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const params = { page, perPage }
            if (filters.search) params.search = filters.search
            if (filters.products) params.products = filters.products
            if (filters.status) params.status = filters.status
            if (filters.branch) params.branch = filters.branch
            const result = await axios("/api/temps/grouped", { params })
            .then((res) => {
                this.temps = res.data.temps || [];
                this.pagination = res.data.pagination || {};
                this.statuses = res.data.statuses || [];
                this.branches = res.data.branches || [];
                this.products = res.data.products || [];
                this.editTemp = null;
            })
            .catch((errors) => {
                console.log(errors);
            })
            .finally(() => {
                this.loading = false;
            });

            return result;
        },

        async getTempsByInvoice(invoiceNo) {
            const result = await axios(`/api/temps/by-invoice/${invoiceNo}`)
            .then((res) => {
                return { status: 'success', data: res.data.temps };
            })
            .catch((error) => {
                return { status: 'error', message: error.message };
            });

            return result;
        },

        async deleteTempsByInvoice(invoiceNo, filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const result = await axios.post('/api/temps/delete-by-invoice', { invoice_no: invoiceNo })
            .then((res) => {
                this.loadGroupedTemps(filters, page, perPage);
                return { status: 'success' };
            })
            .catch((error) => {
                return { status: 'error', message: error.message };
            })
            .finally(() => {
                this.loading = false;
            });

            return result;
        },

        async loadTemps(filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const params = { page, perPage }
            if (filters.search) params.search = filters.search
            if (filters.products) params.products = filters.products
            if (filters.status) params.status = filters.status
            if (filters.branch) params.branch = filters.branch
            const result = await axios("/api/temps", { params })
            .then((res) => {
                this.temps = res.data.temps?.data || [];
                this.pagination = res.data.temps;
                this.editTemp = null;
                this.statuses = res.data.statuses || [];
                this.branches = res.data.branches || [];
                this.products = res.data.products || [];
                this.productPrices = res.data.product_price || [];
                this.clients = res.data.clients || [];
                this.units = res.data.unit || [];
                this.barcodes = res.data.barcodes || [];
            })
            .catch((errors) => {
                console.log(errors);
            })
            .finally(() => {
                this.loading = false;
            });

            return result;
        },

        async loadTodayTemps() {
            const result = await axios("/api/temps/today")
            .then((res) => {
                this.todayTemps = res.data.temps || [];
            })
            .catch((errors) => {
                console.log(errors);
            });

            return result;
        },

        async createTemp(tempData) {
            this.loading = true;
            const result = await axios.post("/api/temps", tempData)
            .then((res) => {
                this.loadTodayTemps();
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

        async updateTemp(id, tempData) {
            this.loading = true;
            const result = await axios.post(`/api/temps/update/${id}`, tempData)
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

        async deleteTemp(id, filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const result = await axios.post(`/api/temps/delete/${id}`)
            .then((res) => {
                this.loadTemps(filters, page, perPage);
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

        async getTemp(id) {
            const result = await axios(`/api/temps/${id}`)
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

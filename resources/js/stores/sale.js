import axios from "axios";
import { defineStore } from "pinia";

export const useSale = defineStore("dashSale", {
    state: () => ({
        sales: [],
        editSale: null,
        statuses: [],
        branches: [],
        products: [],
        productPrices: [],
        clients: [],
        units: [],
        barcodes: [],
        pagination: {},
        loading: false,
        errors: null,
        message: ""
    }),

    getters: {
        allSales: (state) => state.sales,
        allEditSale: (state) => state.editSale,
        allStatuses: (state) => state.statuses,
        allBranches: (state) => state.branches,
        allProducts: (state) => state.products,
        allProductPrices: (state) => state.productPrices,
        allClients: (state) => state.clients,
        allUnits: (state) => state.units,
        allBarcodes: (state) => state.barcodes,
        allPaginations: (state) => state.pagination,
    },

    actions: {
        async loadSales(filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const params = { page, perPage }
            if (filters.search) params.search = filters.search
            if (filters.products) params.products = filters.products
            if (filters.status) params.status = filters.status
            if (filters.branch) params.branch = filters.branch
            const result = await axios("/api/sales", { params })
            .then((res) => {
                this.sales = res.data.sales?.data || [];
                this.pagination = res.data.sales;
                this.editSale = null;
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

        async createSale(saleData) {
            this.loading = true;
            const result = await axios.post("/api/sales", saleData)
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

        async updateSale(id, saleData) {
            this.loading = true;
            const result = await axios.post(`/api/sales/update/${id}`, saleData)
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

        async deleteSale(id, filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const result = await axios.post(`/api/sales/delete/${id}`)
            .then((res) => {
                this.loadSales(filters, page, perPage);
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

        async deleteSalesByInvoice(invoiceNo, filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const result = await axios.post('/api/sales/delete-by-invoice', { invoice_no: invoiceNo })
            .then((res) => {
                this.loadSales(filters, page, perPage);
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

        async getSale(id) {
            const result = await axios(`/api/sales/${id}`)
            .then((res) => {
                return { status: 'success', data: res.data };
            })
            .catch ((error) => {
                return { status: 'error', message: error.message };
            });

            return result;
        },

        async getSalesByInvoice(invoiceNo) {
            const result = await axios(`/api/sales/by-invoice/${invoiceNo}`)
            .then((res) => {
                return { status: 'success', data: res.data.sales };
            })
            .catch ((error) => {
                return { status: 'error', message: error.message };
            });

            return result;
        },
    }
});

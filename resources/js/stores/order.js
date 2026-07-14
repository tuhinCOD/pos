import axios from "axios";
import { defineStore } from "pinia";

export const useOrder = defineStore("dashOrder", {
    state: () => ({
        orders: [],
        statuses: [],
        products: [],
        productPrices: [],
        units: [],
        pagination: {},
        loading: false,
        errors: null,
        message: ""
    }),

    getters: {
        allOrders: (state) => state.orders,
        allStatuses: (state) => state.statuses,
        allProducts: (state) => state.products,
        allProductPrices: (state) => state.productPrices,
        allUnits: (state) => state.units,
        allPaginations: (state) => state.pagination,
    },

    actions: {
        async loadOrders(filters = {}, page = 1, perPage = 20) {
            this.loading = true;
            const params = { page, perPage }
            if (filters.search) params.search = filters.search
            if (filters.products) params.products = filters.products
            if (filters.status) params.status = filters.status
            const result = await axios("/api/orders", { params })
            .then((res) => {
                this.orders = res.data.orders?.data || [];
                this.pagination = res.data.orders;
                this.statuses = res.data.statuses || [];
                this.products = res.data.products || [];
                this.productPrices = res.data.product_price || [];
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

        async deleteOrder(id, filters = {}, page = 1, perPage = 20) {
            this.loading = true;
            const result = await axios.post(`/api/orders/delete/${id}`)
            .then((res) => {
                this.loadOrders(filters, page, perPage);
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
    }
});

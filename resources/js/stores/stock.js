import axios from "axios";
import { defineStore } from "pinia";

export const useStock = defineStore("dashStock", {
    state: () => ({
        stocks: [],
        products: [],
        branches: [],
        pagination: {},
        loading: false,
        errors: null,
        message: ""
    }),

    getters: {
        allStocks: (state) => state.stocks,
        allProducts: (state) => state.products,
        allBranches: (state) => state.branches,
        allPaginations: (state) => state.pagination,
    },

    actions: {
        async loadStocks(filters = {}, page = 1, perPage = 20) {
            this.loading = true;
            const params = { page, perPage }
            if (filters.search) params.search = filters.search
            if (filters.products?.length) params.products = filters.products
            if (filters.branches?.length) params.branches = filters.branches
            const result = await axios("/api/stock", { params })
            .then((res) => {
                this.stocks = res.data.stock?.data || [];
                this.pagination = res.data.stock;
                this.products = res.data.products || [];
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
    }
});

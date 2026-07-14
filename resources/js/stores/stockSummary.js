import axios from "axios";
import { defineStore } from "pinia";

export const useStockSummary = defineStore("dashStockSummary", {
    state: () => ({
        stocks: [],
        products: [],
        pagination: {},
        loading: false,
        errors: null,
        message: ""
    }),

    getters: {
        allStocks: (state) => state.stocks,
        allProducts: (state) => state.products,
        allPaginations: (state) => state.pagination,
    },

    actions: {
        async loadStocks(filters = {}, page = 1, perPage = 20) {
            this.loading = true;
            const params = { page, perPage }
            if (filters.product_id) params.product_id = filters.product_id
            if (filters.search) params.search = filters.search
            const result = await axios("/api/stock/summary", { params })
            .then((res) => {
                this.stocks = res.data.stock?.data || [];
                this.pagination = res.data.stock;
                this.products = res.data.products || [];
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

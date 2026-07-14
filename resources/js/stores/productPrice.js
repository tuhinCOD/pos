import axios from "axios";
import { defineStore } from "pinia";

export const useProductPrice = defineStore("dashProductPrice", {
    state: () => ({
        productPrices: [],
        products: [],
        units: [],
        pagination: {},
        loading: false,
        errors: null,
        message: ""
    }),

    getters: {
        allProductPrices: (state) => state.productPrices,
        allProducts: (state) => state.products,
        allUnits: (state) => state.units,
        allPaginations: (state) => state.pagination,
    },

    actions: {
        async loadProductPrices(filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const params = { page, perPage }
            if (filters.search) params.search = filters.search
            const result = await axios("/api/product_prices", { params })
            .then((res) => {
                this.productPrices = res.data.productPrices?.data || [];
                this.products = res.data.products || [];
                this.units = res.data.unit || [];
                this.pagination = res.data.productPrices;
            })
            .catch((errors) => {
                console.log(errors);
            })
            .finally(() => {
                this.loading = false;
            });

            return result;
        },

        async createProductPrice(formData) {
            this.loading = true;
            const result = await axios.post("/api/product_prices", formData)
            .then((res) => {
                this.loadProductPrices();
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

        async updateProductPrice(id, formData) {
            this.loading = true;
            const result = await axios.post(`/api/product_prices/update/${id}`, formData)
            .then((res) => {
                this.loadProductPrices();
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

        async deleteProductPrice(id) {
            this.loading = true;
            const result = await axios.post(`/api/product_prices/delete/${id}`)
            .then((res) => {
                this.loadProductPrices();
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

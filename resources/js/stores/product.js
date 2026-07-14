import axios from "axios";
import { defineStore } from "pinia";

export const useProduct = defineStore("dashProduct", {
    state: () => ({
        products: [],
        categories: [],
        statuses: [],
        units: [],
        pagination: {},
        loading: false,
        // editProduct: null,
        errors: null,
        message: ""
    }),

    getters: {
        allCategories: (state) => state.categories,
        allStatuses: (state) => state.statuses,
        allUnits: (state) => state.units,
        allProducts: (state) => state.products,
        allPaginations: (state) => state.pagination,
        // getEditProduct: (state) => state.editProduct,
    },

    actions: {
        async loadProducts(filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const params = { page, perPage }
            if (filters.search) params.search = filters.search
            if (filters.categories?.length) params.categories = filters.categories
            if (filters.status) params.status = filters.status
            const result = await axios("/api/products", { params })
            .then((res) => {
                this.products = res.data.products?.data || [];
                this.categories = res.data.categories;
                this.statuses = res.data.statuses;
                this.units = res.data.units || [];
                this.pagination = res.data.products;
            })
            .catch((errors) => {
                console.log(errors);
            })
            .finally(() => {
                this.loading = false;
            });

            return result;
        },

        async createProduct(productData) {
            this.loading = true;
            const result = await axios.post("/api/products", productData)
            .then((res) => {
                this.loadProducts();
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

        async updateProduct(id, productData) {
            this.loading = true;
            const result = await axios.post(`/api/products/update/${id}`, productData)
            .then((res) => {
                // this.editProduct = null;
                this.loadProducts();
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

        async deleteProduct(id) {
            this.loading = true;
            const result = await axios.post(`/api/products/delete/${id}`)
            .then((res) => {
                // this.editProduct = null;
                this.loadProducts();
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

import axios from "axios";
import { defineStore } from "pinia";

export const useCategory = defineStore("dashCategory", {
    state: () => ({
        categories: [],
        parentCategories: [],
        pagination: {},
        loading: false,
        errors: null,
        message: ""
    }),

    getters: {
        allCategories: (state) => state.categories,
        allParentCategories: (state) => state.parentCategories,
        allPaginations: (state) => state.pagination,
    },

    actions: {
        async loadCategories(filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const params = { page, perPage }
            if (filters.search) params.search = filters.search
            const result = await axios("/api/categories", { params })
            .then((res) => {
                this.categories = res.data.categories?.data || [];
                this.parentCategories = res.data.allCategories || [];
                this.pagination = res.data.categories;
            })
            .catch((errors) => {
                console.log(errors);
            })
            .finally(() => {
                this.loading = false;
            });

            return result;
        },

        async createCategory(categoryData, filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const result = await axios.post("/api/categories", categoryData)
            .then((res) => {
                this.loadCategories(filters, page, perPage);
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

        async updateCategory(id, categoryData, filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const result = await axios.post(`/api/categories/update/${id}`, categoryData)
            .then((res) => {
                this.loadCategories(filters, page, perPage);
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

        async deleteCategory(id, filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const result = await axios.post(`/api/categories/delete/${id}`)
            .then((res) => {
                this.loadCategories(filters, page, perPage);
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

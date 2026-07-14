import axios from "axios";
import { defineStore } from "pinia";

export const useDamage = defineStore("dashDamage", {
    state: () => ({
        damages: [],
        editDamage: null,
        statuses: [],
        products: [],
        branches: [],
        units: [],
        pagination: {},
        loading: false,
        errors: null,
        message: ""
    }),

    getters: {
        allDamages: (state) => state.damages,
        allEditDamage: (state) => state.editDamage,
        allStatuses: (state) => state.statuses,
        allProducts: (state) => state.products,
        allBranches: (state) => state.branches,
        allUnits: (state) => state.units,
        allPaginations: (state) => state.pagination,
    },

    actions: {
        async loadDamages(filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const params = { page, perPage }
            if (filters.search) params.search = filters.search
            if (filters.products) params.products = filters.products
            if (filters.status) params.status = filters.status
            if (filters.branch) params.branch = filters.branch
            const result = await axios("/api/damages", { params })
            .then((res) => {
                this.damages = res.data.damages?.data || [];
                this.pagination = res.data.damages;
                this.editDamage = null;
                this.statuses = res.data.statuses || [];
                this.products = res.data.products || [];
                this.branches = res.data.branches || [];
                this.units = res.data.units || [];
            })
            .catch((errors) => {
                console.log(errors);
            })
            .finally(() => {
                this.loading = false;
            });

            return result;
        },

        async createDamage(formData) {
            this.loading = true;
            const result = await axios.post("/api/damages", formData)
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

        async updateDamage(id, formData) {
            this.loading = true;
            const result = await axios.post(`/api/damages/update/${id}`, formData)
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

        async deleteDamage(id, filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const result = await axios.post(`/api/damages/delete/${id}`)
            .then((res) => {
                this.loadDamages(filters, page, perPage);
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

        async getDamage(id) {
            const result = await axios(`/api/damages/${id}`)
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

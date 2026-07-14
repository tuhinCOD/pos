import axios from "axios";
import { defineStore } from "pinia";

export const useRepair = defineStore("dashRepair", {
    state: () => ({
        repairs: [],
        editRepair: null,
        statuses: [],
        products: [],
        damages: [],
        branches: [],
        units: [],
        pagination: {},
        loading: false,
        errors: null,
        message: ""
    }),

    getters: {
        allRepairs: (state) => state.repairs,
        allEditRepair: (state) => state.editRepair,
        allStatuses: (state) => state.statuses,
        allProducts: (state) => state.products,
        allDamages: (state) => state.damages,
        allBranches: (state) => state.branches,
        allUnits: (state) => state.units,
        allPaginations: (state) => state.pagination,
    },

    actions: {
        async loadRepairs(filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const params = { page, perPage }
            if (filters.search) params.search = filters.search
            if (filters.products) params.products = filters.products
            if (filters.status) params.status = filters.status
            if (filters.branch) params.branch = filters.branch
            const result = await axios("/api/repairs", { params })
            .then((res) => {
                this.repairs = res.data.repairs?.data || [];
                this.pagination = res.data.repairs;
                this.editRepair = null;
                this.statuses = res.data.statuses || [];
                this.products = res.data.products || [];
                this.damages = res.data.damages || [];
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

        async createRepair(formData) {
            this.loading = true;
            const result = await axios.post("/api/repairs", formData)
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

        async updateRepair(id, formData) {
            this.loading = true;
            const result = await axios.post(`/api/repairs/update/${id}`, formData)
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

        async deleteRepair(id, filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const result = await axios.post(`/api/repairs/delete/${id}`)
            .then((res) => {
                this.loadRepairs(filters, page, perPage);
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

        async getRepair(id) {
            const result = await axios(`/api/repairs/${id}`)
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

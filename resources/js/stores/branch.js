import axios from "axios";
import { defineStore } from "pinia";

export const useBranch = defineStore("dashBranch", {
    state: () => ({
        branches: [],
        cities: [],
        pagination: {},
        loading: false,
        errors: null,
        message: ""
    }),

    getters: {
        allBranches: (state) => state.branches,
        allCities: (state) => state.cities,
        allPaginations: (state) => state.pagination,
    },

    actions: {
        async loadBranches(filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const params = { page, perPage }
            if (filters.search) params.search = filters.search
            const result = await axios("/api/branches", { params })
            .then((res) => {
                this.branches = res.data.branches?.data || [];
                this.cities = res.data.cities || [];
                this.pagination = res.data.branches;
            })
            .catch((errors) => {
                console.log(errors);
            })
            .finally(() => {
                this.loading = false;
            });

            return result;
        },

        async createBranch(branchData, filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const result = await axios.post("/api/branches", branchData)
            .then((res) => {
                this.loadBranches(filters, page, perPage);
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

        async updateBranch(id, branchData, filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const result = await axios.post(`/api/branches/update/${id}`, branchData)
            .then((res) => {
                this.loadBranches(filters, page, perPage);
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

        async deleteBranch(id, filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const result = await axios.post(`/api/branches/delete/${id}`)
            .then((res) => {
                this.loadBranches(filters, page, perPage);
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

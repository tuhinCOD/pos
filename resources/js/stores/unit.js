import axios from "axios";
import { defineStore } from "pinia";

export const useUnit = defineStore("dashUnit", {
    state: () => ({
        units: [],
        pagination: {},
        loading: false,
        errors: null,
        message: ""
    }),

    getters: {
        allUnits: (state) => state.units,
        allPaginations: (state) => state.pagination,
    },

    actions: {
        async loadUnits(filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const params = { page, perPage }
            if (filters.search) params.search = filters.search
            const result = await axios("/api/units", { params })
            .then((res) => {
                this.units = res.data.units?.data || [];
                this.pagination = res.data.units;
            })
            .catch((errors) => {
                console.log(errors);
            })
            .finally(() => {
                this.loading = false;
            });

            return result;
        },

        async createUnit(unitData, filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const result = await axios.post("/api/units", unitData)
            .then((res) => {
                this.loadUnits(filters, page, perPage);
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

        async updateUnit(id, unitData, filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const result = await axios.post(`/api/units/update/${id}`, unitData)
            .then((res) => {
                this.loadUnits(filters, page, perPage);
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

        async deleteUnit(id, filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const result = await axios.post(`/api/units/delete/${id}`)
            .then((res) => {
                this.loadUnits(filters, page, perPage);
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

import axios from "axios";
import { defineStore } from "pinia";

export const useSupplier = defineStore("dashSupplier", {
    state: () => ({
        suppliers: [],
        cities: [],
        pagination: {},
        loading: false,
        errors: null,
        message: ""
    }),

    getters: {
        allSuppliers: (state) => state.suppliers,
        allCities: (state) => state.cities,
        allPaginations: (state) => state.pagination,
    },

    actions: {
        async loadSuppliers(filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const params = { page, perPage }
            if (filters.search) params.search = filters.search
            const result = await axios("/api/suppliers", { params })
            .then((res) => {
                this.suppliers = res.data.suppliers?.data || [];
                this.cities = res.data.cities || [];
                this.pagination = res.data.suppliers;
            })
            .catch((errors) => {
                console.log(errors);
            })
            .finally(() => {
                this.loading = false;
            });

            return result;
        },

        async createSupplier(supplierData, filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const result = await axios.post("/api/suppliers", supplierData)
            .then((res) => {
                this.loadSuppliers(filters, page, perPage);
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

        async updateSupplier(id, supplierData, filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const result = await axios.post(`/api/suppliers/update/${id}`, supplierData)
            .then((res) => {
                this.loadSuppliers(filters, page, perPage);
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

        async deleteSupplier(id, filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const result = await axios.post(`/api/suppliers/delete/${id}`)
            .then((res) => {
                this.loadSuppliers(filters, page, perPage);
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

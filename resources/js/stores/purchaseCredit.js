import axios from "axios";
import { defineStore } from "pinia";

export const usePurchaseCredit = defineStore("dashPurchaseCredit", {
    state: () => ({
        credits: [],
        purchases: [],
        pagination: {},
        loading: false,
        errors: null,
        message: ""
    }),

    getters: {
        allCredits: (state) => state.credits,
        allPurchases: (state) => state.purchases,
        allPaginations: (state) => state.pagination,
    },

    actions: {
        async loadCredits(filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const params = { page, perPage, credit_type: 'purchase' }
            if (filters.search) params.search = filters.search
            const result = await axios("/api/credits", { params })
            .then((res) => {
                this.credits = res.data.credits?.data || [];
                this.pagination = res.data.credits;
                this.purchases = res.data.purchases || [];
            })
            .catch((errors) => {
                console.log(errors);
            })
            .finally(() => {
                this.loading = false;
            });

            return result;
        },

        async getCredit(id) {
            this.loading = true;
            const result = await axios(`/api/credits/${id}`)
            .then((res) => {
                return { status: 'success', data: res.data };
            })
            .catch ((error) => {
                return { status: 'error', message: error.message };
            })
            .finally(() => {
                this.loading = false;
            });

            return result;
        },

        async updateCredit(id, formData) {
            this.loading = true;
            const result = await axios.post(`/api/credits/update/${id}`, formData)
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

        async deleteCredit(id, filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const result = await axios.post(`/api/credits/delete/${id}`)
            .then((res) => {
                this.loadCredits(filters, page, perPage);
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

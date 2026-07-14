import axios from "axios";
import { defineStore } from "pinia";

export const useUser = defineStore("dashUser", {
    state: () => ({
        users: [],
        branches: [],
        roles: [],
        cities: [],
        statuses: [],
        pagination: {},
        loading: false,
        errors: null,
        message: ""
    }),

    getters: {
        allBranches: (state) => state.branches,
        allRoles: (state) => state.roles,
        allCities: (state) => state.cities,
        allStatuses: (state) => state.statuses,
        allUsers: (state) => state.users,
        allPaginations: (state) => state.pagination,
    },

    actions: {
        async loadUsers(filters = {}, page = 1, perPage = 15) {
            this.loading = true;
            const params = { page, perPage }
            if (filters.search) params.search = filters.search
            if (filters.status) params.status = filters.status
            if (filters.roles?.length) params.roles = filters.roles
            const result = await axios("/api/users", { params })
            .then((res) => {
                this.users = res.data.users?.data || [];
                this.branches = res.data.branches;
                this.roles = res.data.roles;
                this.cities = res.data.cities;
                this.statuses = res.data.statuses;
                this.pagination = res.data.users;
            })
            .catch((errors) => {
                console.log(errors);
            })
            .finally(() => {
                this.loading = false;
            });

            return result;
        },

        async createUser(userData) {
            this.loading = true;
            const result = await axios.post("/api/users", userData)
            .then((res) => {
                this.loadUsers();
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

        async updateUser(id, userData) {
            this.loading = true;
            const result = await axios.post(`/api/users/update/${id}`, userData)
            .then((res) => {
                this.loadUsers();
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

        async deleteUser(id) {
            this.loading = true;
            const result = await axios.post(`/api/users/delete/${id}`)
            .then((res) => {
                this.loadUsers();
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

import { defineStore } from 'pinia'

export const useLoadingStore = defineStore('loading', {
    state: () => ({
        pendingRequests: 0,
    }),
    getters: {
        isLoading: (state) => state.pendingRequests > 0,
    },
    actions: {
        start() {
            this.pendingRequests++
        },
        stop() {
            this.pendingRequests = Math.max(0, this.pendingRequests - 1)
        },
    },
})

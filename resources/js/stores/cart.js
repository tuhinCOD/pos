import axios from "axios";
import { defineStore } from "pinia";

export const useCart = defineStore("cart", {
  state: () => ({
    items: [],
    loading: false,
  }),

  getters: {
    count: (state) => state.items.reduce((sum, item) => sum + Number(item.qty), 0),
    subtotal: (state) => state.items.reduce((sum, item) => sum + Number(item.qty) * Number(item.product?.product_price?.price || 0), 0),
  },

  actions: {
    async fetch() {
      try {
        const res = await axios.get("/api/cart");
        this.items = res.data.cart?.data || [];
      } catch { this.items = [] }
    },

    async add(productId, qty = 1, attributes = null) {
      this.loading = true;
      try {
        const payload = { product: productId, qty };
        if (attributes) payload.attributes = attributes;
        await axios.post("/api/cart", payload);
        await this.fetch();
      } finally { this.loading = false }
    },

    async update(id, qty) {
      const item = this.items.find(i => i.id === id);
      if (!item) return;
      try {
        await axios.post(`/api/cart/update/${id}`, { product: item.product_id, qty });
        await this.fetch();
      } catch { /* silent */ }
    },

    async remove(id) {
      try {
        await axios.post(`/api/cart/delete/${id}`);
        this.items = this.items.filter(i => i.id !== id);
      } catch { /* silent */ }
    },

    clear() { this.items = []; },
  },
});

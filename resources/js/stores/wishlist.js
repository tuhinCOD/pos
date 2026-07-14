import axios from "axios";
import { defineStore } from "pinia";

export const useWishlist = defineStore("wishlist", {
  state: () => ({
    items: [],
  }),

  actions: {
    async fetch() {
      try {
        const res = await axios.get("/api/wishlists");
        this.items = res.data.wishlists || [];
      } catch { this.items = [] }
    },

    async toggle(productId) {
      const exists = this.items.find(i => i.product_id === productId);
      if (exists) {
        try {
          await axios.post(`/api/wishlists/delete/${exists.id}`);
          this.items = this.items.filter(i => i.id !== exists.id);
        } catch { /* silent */ }
      } else {
        try {
          const res = await axios.post("/api/wishlists", { product_id: productId });
          if (res.data.wishlist) this.items.push(res.data.wishlist);
        } catch { /* silent */ }
      }
    },

    isWishlisted(productId) {
      return this.items.some(i => i.product_id === productId);
    },
  },
});

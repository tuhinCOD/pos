<template>
  <div class="d-flex flex-column min-vh-100" :class="{ busy: loadingStore.isLoading }">
    <Navbar />
    <main class="flex-grow-1">
      <router-view />
    </main>
    <Footer />
  </div>
</template>

<script setup>
import Navbar from './Navbar.vue';
import Footer from './Footer.vue';
import { ref, onMounted, provide } from 'vue';
import axios from 'axios';
import { useCart } from '@/stores/cart';
import { useWishlist } from '@/stores/wishlist';
import { useAuth } from '@/stores/auth';
import { useLoadingStore } from '@/stores/loading';

const loadingStore = useLoadingStore();
const cart = useCart();
const wishlist = useWishlist();
const auth = useAuth();
const companyName = ref('Store');
const companyEmail = ref('');
const companyContact = ref('');

provide('companyName', companyName);
provide('companyEmail', companyEmail);
provide('companyContact', companyContact);

onMounted(async () => {
  try {
    const res = await axios.get('/api/storefront/company');
    if (res.data.company) {
      companyName.value = res.data.company.name || 'Store';
      companyEmail.value = res.data.company.email || '';
      companyContact.value = res.data.company.contact || '';
      localStorage.setItem('company_name', companyName.value);
    }
  } catch { /* silent */ }

  if (auth.user) {
    cart.fetch();
    wishlist.fetch();
  }
});
</script>

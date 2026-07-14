<template>
  <footer class="bg-dark text-light pt-5 pb-3 mt-5">
    <div class="container">
      <div class="row g-4">
        <div class="col-lg-4">
          <h5 class="fw-bold mb-3"><i class="bi bi-shop me-2"></i>{{ companyName || 'Shop' }}</h5>
          <p class="text-secondary small">Your trusted online store. Quality products at the best prices.</p>
        </div>
        <div class="col-lg-2 col-md-4">
          <h6 class="fw-bold mb-3">Quick Links</h6>
          <ul class="list-unstyled small">
            <li class="mb-2"><router-link to="/shop" class="text-secondary text-decoration-none">Shop</router-link></li>
            <li class="mb-2"><router-link to="/cart" class="text-secondary text-decoration-none">Cart</router-link></li>
          </ul>
        </div>
        <div class="col-lg-2 col-md-4">
          <h6 class="fw-bold mb-3">Account</h6>
          <ul class="list-unstyled small">
            <li class="mb-2"><router-link to="/login" class="text-secondary text-decoration-none">Login</router-link></li>
            <li class="mb-2"><router-link to="/signup" class="text-secondary text-decoration-none">Register</router-link></li>
          </ul>
        </div>
        <div class="col-lg-4 col-md-4">
          <h6 class="fw-bold mb-3">Contact</h6>
          <ul class="list-unstyled small text-secondary">
            <li class="mb-2"><i class="bi bi-envelope me-2"></i>{{ companyEmail || 'support@example.com' }}</li>
            <li class="mb-2"><i class="bi bi-telephone me-2"></i>+88 {{ companyContact || '01xxxxxxxxx' }}</li>
          </ul>
        </div>
      </div>
      <hr class="border-secondary my-3">
      <p class="text-center text-secondary small m-0">&copy; {{ new Date().getFullYear() }} {{ companyName || 'Shop' }}. All rights reserved.</p>
    </div>
  </footer>
</template>

<script setup>
import { inject } from 'vue';

const companyName = inject('companyName', 'Store');
const companyEmail = inject('companyEmail', '');
const companyContact = inject('companyContact', '');
</script>

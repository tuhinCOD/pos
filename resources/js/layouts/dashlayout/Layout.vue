<template>
    <div class="content-block d-flex" data-name="layout" :class="{ busy: loadingStore.isLoading }">
      <Sidebar/>
      <div class="mainbody">
        <Header/>  
        <div class="content">
          <slot></slot>
        </div>       
        <Footer/>
      </div><!--/mainbody-->
    </div><!--/layout-->
</template>

<script setup>
import { useLoadingStore } from '../../stores/loading';
import ('../../assets/custom/custom.js');
import '../../assets/custom/style.scss';
import Sidebar from './Sidebar.vue';
import Header from './Header.vue';
import Footer from './Footer.vue';

const loadingStore = useLoadingStore();
</script>

<style lang="scss" scoped>

</style>

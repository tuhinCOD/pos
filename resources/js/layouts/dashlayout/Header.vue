<template>
    <header class="topbar">
        <nav class="navbar navbar-light p-0 shadow-sm border-bottom bg-light">
        <button type="button" class="btn btn-light rounded-0 shadow-none m-0 h-100" data-push="push">
            <i class="bi bi-list pe-none"></i>
        </button>
        <ul class="list-inline ms-sm-auto ms-md-auto mx-md-0 m-0">
            <li class="list-inline-item active m-0">
            <a class="nav-link px-3 py-2" href="#">
                <i class="bi bi-envelope-fill fs-5"></i>
                <span class="badge rounded-pill bg-danger">5</span>
            </a>
            </li>
            <li class="list-inline-item m-0">
            <a class="nav-link px-3 py-2" href="#">
                <i class="bi bi-bell-fill fs-5"></i>
                <span class="badge rounded-pill bg-primary">99+</span>
            </a>
            </li>
            <li class="list-inline-item dropdown m-0">
            <a class="nav-link dropdown-toggle px-3 py-2" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown"
                aria-haspopup="true" aria-expanded="false">
                <i class="bi bi-person-circle fs-5"></i>
            </a>
            <div class="dropdown-menu dropdown-menu-end me-2 py-0 fz-1" aria-labelledby="navbarDropdown">
                <router-link class="dropdown-item d-flex align-items-center ps-0 py-1" to="/account/profile">
                <div class="menu-icon d-flex justify-content-center w-25">
                    <i class="bi bi-person"></i>
                </div>
                <div class="ps-2">Profile</div>
                </router-link>
                <router-link class="dropdown-item d-flex align-items-center ps-0 py-1" to="/resetpass">
                <div class="menu-icon d-flex justify-content-center w-25">
                    <i class="bi bi-pencil-square"></i>
                </div>
                <div class="ps-2">Reset Password</div>
                </router-link>
                <div class="dropdown-divider my-0"></div>
                <a class="dropdown-item d-flex align-items-center ps-0 py-1" href="#">
                <div class="menu-icon d-flex justify-content-center w-25">
                    <i class="bi bi-power"></i>
                </div>
                <div class="ps-2">
                    <button type="button" @click="handleLogout" name="logout" class="ps-2 border-0">Logout</button>
                </div>
                </a>
            </div>
            </li>
        </ul>
        </nav><!--/navbar-->
    </header><!--/header-->
</template>

<script setup>
import { useAuth } from '@/stores/auth';

const auth = useAuth();

const handleLogout = async () => {
  await auth.logout();
};
</script>


<style lang="scss" scoped>
@media (max-width: 767.98px){
  .topbar .nav-link {
    padding-left: 0.625rem !important;
    padding-right: 0.625rem !important;
  }
  .topbar .navbar {
    min-height: 40px;
  }
}
</style>

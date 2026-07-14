<template>
    <div class="sidebar bg-dark-black">
        <div class="topbar text-center border-bottom border-primary bg-dark-black">
          <div class="logo-lg">
            <a href="#" class="nav-link text-light fs-6 px-3 py-2">
              <img v-if="companyLogo" :src="companyLogo" alt="Logo" class="sidebar-logo me-2">
              <h5 class="m-0 d-inline"><b>{{ companyName || 'Admin Panel' }}</b></h5>
            </a>
          </div>
          <div class="logo-sm">
            <a href="#" class="nav-link text-light fs-6 px-auto py-2">
              <img v-if="companyLogo" :src="companyLogo" alt="Logo" class="sidebar-logo-sm">
              <h5 class="m-0" v-else><b>{{ companyShort || 'AP' }}</b></h5>
            </a>
          </div>
        </div><!--/topbar-->
        <div class="accordion" id="accordion">
          <ul class="list-unstyled">
            <li class="active">
              <router-link to="/dashboard" role="button" class="nav-link d-flex align-items-center ps-0 py-0 pe-md-0 collapsed text-light">
                <div class="sidemenu-icon">
                  <i class="bi bi-speedometer2"></i>
                </div>
                <div class="position-relative d-flex align-items-center flex-grow-1">
                  <div class="plus text-capitalize position-absolute">
                    <span>Dashboard</span>
                  </div>
                </div>
              </router-link>
            </li><!--/single-->
            <li>
              <a role="button" class="nav-link ps-0 py-0 d-flex align-items-center pe-md-0 collapsed text-light"
                data-bs-toggle="collapse" data-bs-target="#inventory" data-show="onoff">
                <div class="sidemenu-icon">
                  <i class="bi bi-box-seam-fill"></i>
                </div>
                <div class="sidetoggle d-flex align-items-center flex-grow-1">
                  <div class="plus text-capitalize">
                    <span>inventory</span>
                  </div>
                </div>
              </a>
              <ul class="list-unstyled collapse" id="inventory" data-bs-parent="#accordion">
                <li v-if="isAdmin || isSuperAdmin || isManager"><router-link to="/stock" class="nav-link text-capitalize text-light fz-1 px-3 py-2">stock</router-link></li>
                <li><router-link to="/stock-summary" class="nav-link text-capitalize text-light fz-1 px-3 py-2">stock summary</router-link></li>
                <li v-if="isAdmin || isSuperAdmin"><router-link to="/branches" class="nav-link text-capitalize text-light fz-1 px-3 py-2">branches</router-link></li>
                <ul class="list-unstyled">
                  <li>
                    <a role="button" class="nav-link ps-0 py-0 d-flex align-items-center pe-md-0 collapsed text-light"
                      data-bs-toggle="collapse" data-bs-target="#product" data-show="onoff">
                      <!-- <div class="sidemenu-icon">
                        <i class="bi bi-palette-fill"></i>
                      </div> -->
                      <div class="sidetoggle d-flex align-items-center flex-grow-1 py-2 ps-3">
                        <div class="plus text-capitalize">
                          <span>products</span>
                        </div>
                      </div>
                    </a>
                    <ul class="list-unstyled collapse" id="product" data-bs-parent="#inventory">
                      <li v-if="isAdmin || isManager"><router-link to="/product/update" class="nav-link text-capitalize text-light fz-1 px-3 py-2">add product</router-link></li>
                      <li><router-link to="/products" class="nav-link text-capitalize text-light fz-1 px-3 py-2">view products</router-link></li>
                      <li v-if="isAdmin || isSuperAdmin || isManager"><router-link to="/product-price" class="nav-link text-capitalize text-light fz-1 px-3 py-2">prices</router-link></li>
                      <li v-if="isAdmin || isSuperAdmin || isManager"><router-link to="/products/categories" class="nav-link text-capitalize text-light fz-1 px-3 py-2">categories</router-link></li>
                      <li v-if="isAdmin || isSuperAdmin || isManager"><router-link to="/products/units" class="nav-link text-capitalize text-light fz-1 px-3 py-2">units</router-link></li>
                    </ul>
                  </li>
                </ul>
                <ul class="list-unstyled">
                  <li>
                    <a role="button" class="nav-link ps-0 py-0 d-flex align-items-center pe-md-0 collapsed text-light"
                      data-bs-toggle="collapse" data-bs-target="#damage" data-show="onoff">
                      <!-- <div class="sidemenu-icon">
                        <i class="bi bi-palette-fill"></i>
                      </div> -->
                      <div class="sidetoggle d-flex align-items-center flex-grow-1 py-2 ps-3">
                        <div class="plus text-capitalize">
                          <span>damages</span>
                        </div>
                      </div>
                    </a>
                    <ul class="list-unstyled collapse" id="damage" data-bs-parent="#inventory">
                      <li v-if="isAdmin || isWarehouseStaff || isManager"><router-link to="/damage/create" class="nav-link text-capitalize text-light fz-1 px-3 py-2">add damage</router-link></li>
                      <li><router-link to="/damages" class="nav-link text-capitalize text-light fz-1 px-3 py-2">view damages</router-link></li>
                      <li v-if="isAdmin || isSuperAdmin || isManager"><router-link to="/repairs" class="nav-link text-capitalize text-light fz-1 px-3 py-2">view repairs</router-link></li>
                    </ul>
                  </li>
                </ul>
                <ul class="list-unstyled">
                  <li>
                    <a role="button" class="nav-link ps-0 py-0 d-flex align-items-center pe-md-0 collapsed text-light"
                      data-bs-toggle="collapse" data-bs-target="#barcode" data-show="onoff">
                      <!-- <div class="sidemenu-icon">
                        <i class="bi bi-palette-fill"></i>
                      </div> -->
                      <div class="sidetoggle d-flex align-items-center flex-grow-1 py-2 ps-3">
                        <div class="plus text-capitalize">
                          <span>barcodes</span>
                        </div>
                      </div>
                    </a>
                    <ul class="list-unstyled collapse" id="barcode" data-bs-parent="#inventory">
                      <li v-if="isAdmin || isWarehouseStaff || isManager"><router-link to="/barcodes/generate" class="nav-link text-capitalize text-light fz-1 px-3 py-2">generate barcodes</router-link></li>
                      <li v-if="isAdmin || isWarehouseStaff || isManager || isCashier"><router-link to="/barcodes/generate-single" class="nav-link text-capitalize text-light fz-1 px-3 py-2">generate single barcode</router-link></li>
                      <li><router-link to="/barcodes" class="nav-link text-capitalize text-light fz-1 px-3 py-2">view barcodes</router-link></li>
                    </ul>
                  </li>
                </ul>    
              </ul>
            </li><!--/dropdown-->
            <li v-if="isAdmin || isSuperAdmin || isManager || isWarehouseStaff">
              <a role="button" class="nav-link ps-0 py-0 d-flex align-items-center pe-md-0 collapsed text-light"
                data-bs-toggle="collapse" data-bs-target="#purchases" data-show="onoff">
                <div class="sidemenu-icon">
                  <i class="bi bi-bag-fill"></i>
                </div>
                <div class="sidetoggle d-flex align-items-center flex-grow-1">
                  <div class="plus text-capitalize">
                    <span>purchases</span>
                  </div>
                </div>
              </a>
              <ul class="list-unstyled collapse" id="purchases" data-bs-parent="#accordion">
                <ul class="list-unstyled">
                  <li>
                    <a role="button" class="nav-link ps-0 py-0 d-flex align-items-center pe-md-0 collapsed text-light"
                      data-bs-toggle="collapse" data-bs-target="#purchaseOrder" data-show="onoff">
                      <!-- <div class="sidemenu-icon">
                        <i class="bi bi-palette-fill"></i>
                      </div> -->
                      <div class="sidetoggle d-flex align-items-center flex-grow-1 py-2 ps-3">
                        <div class="plus text-capitalize">
                          <span>purchase orders</span>
                        </div>
                      </div>
                    </a>
                    <ul class="list-unstyled collapse" id="purchaseOrder" data-bs-parent="#purchases">
                      <li v-if="isAdmin || isWarehouseStaff || isManager"><router-link to="/purchase/create" class="nav-link text-capitalize text-light fz-1 px-3 py-2">add purchase</router-link></li>
                      <li v-if="isAdmin || isWarehouseStaff || isSuperAdmin || isManager"><router-link to="/purchases" class="nav-link text-capitalize text-light fz-1 px-3 py-2">view purchases</router-link></li>
                    </ul>
                  </li>
                </ul>
                <ul class="list-unstyled">
                  <li>
                    <a role="button" class="nav-link ps-0 py-0 d-flex align-items-center pe-md-0 collapsed text-light"
                      data-bs-toggle="collapse" data-bs-target="#purchaseReturn" data-show="onoff">
                      <!-- <div class="sidemenu-icon">
                        <i class="bi bi-palette-fill"></i>
                      </div> -->
                      <div class="sidetoggle d-flex align-items-center flex-grow-1 py-2 ps-3">
                        <div class="plus text-capitalize">
                          <span>purchase returns</span>
                        </div>
                      </div>
                    </a>
                    <ul class="list-unstyled collapse" id="purchaseReturn" data-bs-parent="#purchases">
                      <li v-if="isAdmin || isManager"><router-link to="/purchase-return/create" class="nav-link text-capitalize text-light fz-1 px-3 py-2">add purchase return</router-link></li>
                      <li v-if="isAdmin || isWarehouseStaff || isManager || isSuperAdmin"><router-link to="/purchase-returns" class="nav-link text-capitalize text-light fz-1 px-3 py-2">view purchase returns</router-link></li>
                    </ul>
                  </li>
                  <li v-if="isAdmin || isSuperAdmin || isManager"><router-link to="/purchase-credits" class="nav-link text-capitalize text-light fz-1 px-3 py-2">purchase credits</router-link></li>
                </ul>
              </ul>
            </li><!--/dropdown-->
            <li>
              <a role="button" class="nav-link ps-0 py-0 d-flex align-items-center pe-md-0 collapsed text-light"
                data-bs-toggle="collapse" data-bs-target="#sales" data-show="onoff">
                <div class="sidemenu-icon">
                  <i class="bi bi-currency-dollar"></i>
                </div>
                <div class="sidetoggle d-flex align-items-center flex-grow-1">
                  <div class="plus text-capitalize">
                    <span>sales</span>
                  </div>
                </div>
              </a>
              <ul class="list-unstyled collapse" id="sales" data-bs-parent="#accordion">
                <ul class="list-unstyled" v-if="isAdmin || isSuperAdmin || isManager || isCashier">
                  <li>
                    <a role="button" class="nav-link ps-0 py-0 d-flex align-items-center pe-md-0 collapsed text-light"
                      data-bs-toggle="collapse" data-bs-target="#temp" data-show="onoff">
                      <!-- <div class="sidemenu-icon">
                        <i class="bi bi-palette-fill"></i>
                      </div> -->
                      <div class="sidetoggle d-flex align-items-center flex-grow-1 py-2 ps-3">
                        <div class="plus text-capitalize">
                          <span>temps</span>
                        </div>
                      </div>
                    </a>
                    <ul class="list-unstyled collapse" id="temp" data-bs-parent="#sales">
                      <li v-if="isAdmin || isCashier || isManager"><router-link to="/temp/create" class="nav-link text-capitalize text-light fz-1 px-3 py-2">add temp</router-link></li>
                      <li v-if="isAdmin || isCashier || isManager || isSuperAdmin"><router-link to="/temps" class="nav-link text-capitalize text-light fz-1 px-3 py-2">view temps</router-link></li>
                    </ul>
                  </li>
                </ul>
                <ul class="list-unstyled">
                  <li>
                    <a role="button" class="nav-link ps-0 py-0 d-flex align-items-center pe-md-0 collapsed text-light"
                      data-bs-toggle="collapse" data-bs-target="#sale" data-show="onoff">
                      <!-- <div class="sidemenu-icon">
                        <i class="bi bi-palette-fill"></i>
                      </div> -->
                      <div class="sidetoggle d-flex align-items-center flex-grow-1 py-2 ps-3">
                        <div class="plus text-capitalize">
                          <span>sale orders</span>
                        </div>
                      </div>
                    </a>
                    <ul class="list-unstyled collapse" id="sale" data-bs-parent="#sales">
                      <li v-if="isAdmin || isSuperAdmin || isCashier || isManager"><router-link to="/sales" class="nav-link text-capitalize text-light fz-1 px-3 py-2">view sales</router-link></li>
                      <li v-if="isAdmin || isSuperAdmin || isManager || isWarehouseStaff"><router-link to="/e-commerce-orders" class="nav-link text-capitalize text-light fz-1 px-3 py-2">e-commerce orders</router-link></li>
                      <li v-if="isAdmin || isSuperAdmin || isManager || isCashier"><router-link to="/client-credits" class="nav-link text-capitalize text-light fz-1 px-3 py-2">sale credits</router-link></li>
                    </ul>
                  </li>
                </ul>
                <ul class="list-unstyled">
                  <li>
                    <a role="button" class="nav-link ps-0 py-0 d-flex align-items-center pe-md-0 collapsed text-light"
                      data-bs-toggle="collapse" data-bs-target="#saleReturn" data-show="onoff">
                      <!-- <div class="sidemenu-icon">
                        <i class="bi bi-palette-fill"></i>
                      </div> -->
                      <div class="sidetoggle d-flex align-items-center flex-grow-1 py-2 ps-3">
                        <div class="plus text-capitalize">
                          <span>sale returns</span>
                        </div>
                      </div>
                    </a>
                    <ul class="list-unstyled collapse" id="saleReturn" data-bs-parent="#sales">
                      <li v-if="isAdmin || isCashier || isManager"><router-link to="/client-return/create" class="nav-link text-capitalize text-light fz-1 px-3 py-2">add sale return</router-link></li>
                      <li><router-link to="/client-returns" class="nav-link text-capitalize text-light fz-1 px-3 py-2">view sale returns</router-link></li>
                    </ul>
                  </li>
                </ul>
              </ul>
            </li><!--/dropdown-->
            <li v-if="isAdmin || isSuperAdmin || isManager || isCashier">
              <a role="button" class="nav-link ps-0 py-0 d-flex align-items-center pe-md-0 collapsed text-light"
                data-bs-toggle="collapse" data-bs-target="#users" data-show="onoff">
                <div class="sidemenu-icon">
                  <i class="bi bi-people-fill"></i>
                </div>
                <div class="sidetoggle d-flex align-items-center flex-grow-1">
                  <div class="plus text-capitalize">
                    <span>users</span>
                  </div>
                </div>
              </a>
              <ul class="list-unstyled collapse" id="users" data-bs-parent="#accordion">
                <li v-if="isAdmin || isSuperAdmin || isManager || isCashier"><router-link to="/user/update" class="nav-link text-capitalize text-light fz-1 px-3 py-2">add user</router-link></li>
                <li v-if="isAdmin || isSuperAdmin || isManager || isCashier"><router-link to="/users" class="nav-link text-capitalize text-light fz-1 px-3 py-2">view users</router-link></li>
                <li v-if="isAdmin || isSuperAdmin || isManager"><router-link to="/suppliers" class="nav-link text-capitalize text-light fz-1 px-3 py-2">suppliers</router-link></li>
              </ul>
            </li><!--/dropdown-->
            <li>
              <a role="button" class="nav-link ps-0 py-0 d-flex align-items-center pe-md-0 collapsed text-light"
                data-bs-toggle="collapse" data-bs-target="#settings" data-show="onoff">
                <div class="sidemenu-icon">
                  <i class="bi bi-gear-fill"></i>
                </div>
                <div class="sidetoggle d-flex align-items-center flex-grow-1">
                  <div class="plus text-capitalize">
                    <span>settings</span>
                  </div>
                </div>
              </a>
              <ul class="list-unstyled collapse" id="settings" data-bs-parent="#accordion">
                <li v-if="isAdmin"><router-link to="/settings/general" class="nav-link text-capitalize text-light fz-1 px-3 py-2">general</router-link></li>
                <li><a href="#" class="nav-link text-capitalize text-light fz-1 px-3 py-2">reading</a></li>
              </ul>
            </li><!--/dropdown-->
          </ul>
        </div><!--/accordion-->
      </div><!--/sidebar-->
</template>

<script setup>
    import { computed, onMounted } from 'vue';
    import { useCompany } from '../../stores/company';
    import { useAuth } from '../../stores/auth';

    const dash = useCompany();
    const auth = useAuth();

    const companyName = computed(() => dash.companyData?.name || '');
    const companyLogo = computed(() => dash.companyData?.logo ? `/storage/${dash.companyData.logo}` : '');
    const companyShort = computed(() => {
        const name = dash.companyData?.name || '';
        return name ? name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase() : '';
    });

    const userRole = computed(() => auth.user?.role?.name);
    const isAdmin = computed(() => userRole.value === 'admin');
    const isSuperAdmin = computed(() => userRole.value === 'super admin');
    const isManager = computed(() => userRole.value === 'manager'); 
    const isCashier = computed(() => userRole.value === 'cashier');
    const isWarehouseStaff = computed(() => userRole.value === 'warehouse staff');

    onMounted(() => {
        dash.loadCompany();
    });
</script>

<style lang="scss" scoped>
.sidebar-logo {
    height: 24px;
    width: auto;
}
.sidebar-logo-sm {
    height: 20px;
    width: auto;
}
</style>

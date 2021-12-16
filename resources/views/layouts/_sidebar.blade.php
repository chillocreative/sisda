<!-- sidebar menu area start -->
<div class="sidebar-menu">
  <div class="sidebar-header">
    <div class="logo">
      <a href="{{ route('dashboard') }}" style="color: #fff; font-size: 24px;">
        <img src="assets/img/logo.png" alt="logo"/>
        KEADILAN
      </a>
    </div>
  </div>
  <div class="main-menu">
    <div class="menu-inner">
      <nav>
        <ul class="metismenu" id="menu">
          <li class="{{ Route::is('dashboard') ? 'active' : '' }}">
            <a href="{{ route('dashboard') }}">Dashboard</a>
          </li>
          <li class="{{ Route::is('user-master') or Route::is('user-admin') or Route::is('user-user') ? 'active' : '' }}">
            <a href="javascript:void(0)" aria-expanded="true">Users</a>
            <ul>
              <li class="{{ Route::is('user-master') ? 'active' : '' }}"><a href="{{ route('user-master') }}">Master</a></li>
              <li class="{{ Route::is('user-admin') ? 'active' : '' }}"><a href="{{ route('user-admin') }}">Admin</a></li>
              <li class="{{ Route::is('user-user') ? 'active' : '' }}"><a href="{{ route('user-user') }}">User</a></li>
            </ul>
          </li>
          <li>
            <a href="{{ route('logout') }}">Logout</a>
          </li>
        </ul>
      </nav>
    </div>
  </div>
</div>
<!-- sidebar menu area end -->
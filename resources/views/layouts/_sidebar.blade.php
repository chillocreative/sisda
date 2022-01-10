<!-- sidebar menu area start -->
<div class="sidebar-menu">
  <div class="sidebar-header">
    <div class="logo">
      <a href="{{ route('dashboard') }}" style="color: #fff; font-size: 24px;">
        <img src="{{ asset('assets/img/logo.png') }}" alt="logo"/>
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
          @if(Auth::user()->role->name != 'superadmin')
          <li class="{{ (request()->segment(1) == 'mula-culaan') ? 'active' : '' }}">
            <a href="{{ route('mula-culaan.index') }}">Mula Culaan</a>
          <li>
          @endif
          @if(Auth::user()->role->name != 'user')
          <li class="{{ (request()->segment(1) == 'user') ? 'active' : '' }}">
            <a href="javascript:void(0)" aria-expanded="true">Users</a>
            <ul>
              @if(Auth::user()->role->name == 'superadmin')
                <li class="{{ Route::is('user-superadmin') ? 'active' : '' }}"><a href="{{ route('user-superadmin') }}">Superadmin</a></li>
              @endif
              <li class="{{ Route::is('user-admin') ? 'active' : '' }}"><a href="{{ route('user-admin') }}">Admin</a></li>
              <li class="{{ Route::is('user-user') ? 'active' : '' }}"><a href="{{ route('user-user') }}">User</a></li>
            </ul>
          </li>
          @endif
          <li>
            <a href="{{ route('logout') }}">Logout</a>
          </li>
        </ul>
      </nav>
    </div>
  </div>
</div>
<!-- sidebar menu area end -->
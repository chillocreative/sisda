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
          <li class="{{ (request()->segment(1) == 'data-pengundi') ? 'active' : '' }}">
            <a href="{{ route('data-pengundi.index') }}">Data Pengundi</a>
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
          <li class="{{ (request()->segment(1) == 'data-culaan-master') ? 'active' : '' }}">
            <a href="javascript:void(0)" aria-expanded="true">Data Culaan Master</a>
            <ul>
              <li class="{{ (Request()->segment(2) == 'negeri') ? 'active' : '' }}"><a href="{{ route('negeri.index') }}">Negeri</a></li>
              <li class="{{ (Request()->segment(2) == 'parlimen') ? 'active' : '' }}"><a href="{{ route('parlimen.index') }}">Parlimen</a></li>
              <li class="{{ (Request()->segment(2) == 'bandar') ? 'active' : '' }}"><a href="{{ route('bandar.index') }}">Bandar</a></li>
              <li class="{{ (Request()->segment(2) == 'kadun') ? 'active' : '' }}"><a href="{{ route('kadun.index') }}">Kadun</a></li>
              <li class="{{ (Request()->segment(2) == 'mpkk') ? 'active' : '' }}"><a href="{{ route('mpkk.index') }}">MPKK</a></li>
              <li class="{{ (Request()->segment(2) == 'tujuan-sumbangan') ? 'active' : '' }}"><a href="{{ route('tujuan-sumbangan.index') }}">Tujuan Sumbangan</a></li>
              <li class="{{ (Request()->segment(2) == 'jenis-sumbangan') ? 'active' : '' }}"><a href="{{ route('jenis-sumbangan.index') }}">Jenis Sumbangan</a></li>
              <li class="{{ (Request()->segment(2) == 'bantuan-lain') ? 'active' : '' }}"><a href="{{ route('bantuan-lain.index') }}">Bantuan Lain</a></li>
              <li class="{{ (Request()->segment(2) == 'keahlian-parti') ? 'active' : '' }}"><a href="{{ route('keahlian-parti.index') }}">Keahlian Parti</a></li>
              <li class="{{ (Request()->segment(2) == 'kecenderungan-politik') ? 'active' : '' }}"><a href="{{ route('kecenderungan-politik.index') }}">Kecenderungan Politik</a></li>
            </ul>
          </li>
          @endif
          <li class="{{ Route::is('profile') ? 'active' : '' }}">
            <a href="{{ route('profile') }}">My Profile</a>
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
<div class="header-area">
  <div class="row align-items-center">
    <div class="col-4 clearfix">
      <div class="nav-btn mb-3 pull-left">
        <span></span>
        <span></span>
        <span></span>
      </div>
    </div>
    <div class="col-8 text-right">
      <h6>Selamat Datang, {{ Auth::user()->name }}</h6>
    </div>
  </div>
</div>
<div class="page-title-area">
  <div class="row align-items-center">
    <div class="col-sm-6">
      <div class="breadcrumbs-area clearfix py-3">
        <h4 class="page-title pull-left">@yield('breadcrumb_title')</h4>
        <ul class="breadcrumbs pull-left">
          <li><a href="{{ route('dashboard') }}">Home</a></li>
          @yield('breadcrumbs')
        </ul>
      </div>
    </div>
  </div>
</div>
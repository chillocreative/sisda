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
        @if(Route::is('mula-culaan.index') or Route::is('data-pengundi.index'))<h4>/ <a href="{{ route('dashboard') }}"><i class="fa fa-home"></i></a></h4>@endif
        <ul class="breadcrumbs pull-left">
          @if(!Route::is('mula-culaan.index') and !Route::is('data-pengundi.index') and !Route::is('dashboard'))<li><a href="{{ route('dashboard') }}">Home</a></li>@endif
          @yield('breadcrumbs')
        </ul>
      </div>
    </div>
  </div>
</div>
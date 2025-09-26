<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="icon" type="image/png" href="{{ asset('assets/img/icon.png') }}" />

    <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css" integrity="sha512-5A8nwdMOWrSz20fDsjczgUidUBR8liPYU+WymTZP1lmY9G6Oc7HlZv156XqnsgNUzTyMefFTcsFH/tnJE/+xBg==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Datatable -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/jquery.dataTables.min.css">

    <!-- CSS -->
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/font-awesome.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/themify-icons.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/metisMenu.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/owl.carousel.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/slicknav.min.css') }}" />

    <!-- amchart css -->
    <link
      rel="stylesheet"
      href="https://www.amcharts.com/lib/3/plugins/export/export.css"
      type="text/css"
      media="all"
    />

    <!-- others css -->
    <link rel="stylesheet" href="{{ asset('assets/css/typography.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/default-css.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/responsive.css') }}" />

    <!-- modernizr css -->
    <script src="{{ asset('assets/js/vendor/modernizr-2.8.3.min.js') }}"></script>

    @yield('style')

    <title>@yield('title') - Sistem Data Pengundi</title>
  </head>
  <body>
    <div id="preloader">
        <div class="loader"></div>
    </div>
    
    <div class="page-container">
      @include('layouts._sidebar')
      <div class="main-content">
        @include('layouts._header')
        <div class="main-content-inner py-3">
          @if(session('success'))
            <div class="modal fade" id="alertModal" tabindex="-1" role="dialog" aria-labelledby="alertModalLabel" aria-hidden="true">
              <div class="modal-dialog" role="document">
                <div class="modal-content">
                  <div class="modal-body p-3">
                    <div class="text-center">
                      <p class="mb-3 text-success">{{ session('success') }}</p>
                      <button type="button" class="btn btn-primary btn-sm px-4 py-1" data-dismiss="modal">OK</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          @endif
          @if(session('error'))
            <div class="modal fade" id="alertModal" tabindex="-1" role="dialog" aria-labelledby="alertModalLabel" aria-hidden="true">
              <div class="modal-dialog" role="document">
                <div class="modal-content">
                  <div class="modal-body p-3">
                    <div class="text-center">
                      <p class="mb-3 text-danger">{{ session('error') }}</p>
                      <button type="button" class="btn btn-primary btn-sm px-4 py-1" data-dismiss="modal">OK</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          @endif
          @yield('content')
        </div>
      </div>
    </div>

    <!-- JQuery -->
    <script src="{{ asset('assets/js/vendor/jquery-2.2.4.min.js') }}"></script>

    <!-- Alert -->
    <script type="text/javascript">
      $(window).on('load', function() {
        $('#alertModal').modal('show')
      })
    </script>
    
    <!-- Datatable -->
    <script src="http://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>

    <!-- bootstrap 4 js -->
    <script src="{{ asset('assets/js/popper.min.js') }}"></script>
    <script src="{{ asset('assets/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('assets/js/owl.carousel.min.js') }}"></script>
    <script src="{{ asset('assets/js/metisMenu.min.js') }}"></script>
    <script src="{{ asset('assets/js/jquery.slimscroll.min.js') }}"></script>
    <script src="{{ asset('assets/js/jquery.slicknav.min.js') }}"></script>

    <!-- others plugins -->
    <script src="{{ asset('assets/js/plugins.js') }}"></script>
    <script src="{{ asset('assets/js/scripts.js') }}"></script>
    @yield('script')

  </body>
</html>
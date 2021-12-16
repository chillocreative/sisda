<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

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
    <div class="page-container">
      @include('layouts._sidebar')
      <div class="main-content">
        @include('layouts._header')
        <div class="main-content-inner py-3">
          @if(session('success'))
          <div class="row">
            <div class="col-lg-12">
              <div class="alert alert-success alert-dismissible fade show" role="alert">
                <p>{{ session('success') }}</p>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span class="fa fa-times"></span>
                </button>
              </div>
            </div>
          </div>
          @endif
          @if(session('error'))
          <div class="row">
            <div class="col-lg-12">
              <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <p>{{ session('error') }}</p>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span class="fa fa-times"></span>
                </button>
              </div>
            </div>
          </div>
          @endif
          @yield('content')
        </div>
      </div>
    </div>

    
    <script src="{{ asset('assets/js/vendor/jquery-2.2.4.min.js') }}"></script>
    <!-- Datatable -->

    <!-- bootstrap 4 js -->
    <script src="{{ asset('assets/js/popper.min.js') }}"></script>
    <script src="{{ asset('assets/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('assets/js/owl.carousel.min.js') }}"></script>
    <script src="{{ asset('assets/js/metisMenu.min.js') }}"></script>
    <script src="{{ asset('assets/js/jquery.slimscroll.min.js') }}"></script>
    <script src="{{ asset('assets/js/jquery.slicknav.min.js') }}"></script>
    
    
    <script src="cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
    
    

    <!-- start chart js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.2/Chart.min.js"></script>
    <!-- start highcharts js -->
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <!-- start zingchart js -->
    <script src="https://cdn.zingchart.com/zingchart.min.js"></script>
    <script>
      zingchart.MODULESDIR = 'https://cdn.zingchart.com/modules/'
      ZC.LICENSE = [
        '569d52cefae586f634c54f86dc99e6a9',
        'ee6b7db5b51705a13dc2339db3edaf6d',
      ]
    </script>

    
    
    <!-- all line chart activation -->
    <script src="{{ asset('assets/js/line-chart.js') }}"></script>
    <!-- all pie chart -->
    <script src="{{ asset('assets/js/pie-chart.js') }}"></script>
    <!-- others plugins -->
    <script src="{{ asset('assets/js/plugins.js') }}"></script>
    <script src="{{ asset('assets/js/scripts.js') }}"></script>
    @yield('script')

  </body>
</html>
@extends('layouts.app')

@section('title', 'Dashboard')

@section('breadcrumb_title', 'Dashboard')
@section('breadcrumbs')
  <li>Dashboard</li>
@endsection

@section('style')
<link rel="stylesheet" href="{{ asset('assets/css/button-dashboard.css') }}">
@endsection

@section('content')
  @if(Auth::user()->role->name == 'user')
    <div class="row justify-content-center mt-3">
      <div class="col-lg-4">
        <a href="{{ route('mula-culaan.index') }}" class="button-29" role="button" style="width: 100%; height: 100px;">MULA CULAAN</a>
      </div>
      <div class="col-lg-4 mt-3 mt-lg-0">
        <a href="{{ route('data-pengundi.index') }}" class="button-29" role="button" style="width: 100%; height: 100px;">MASUK DATA PENGUNDI</a>
      </div>
    </div>
  @endif
  @if(Auth::user()->role->name == 'superadmin' || Auth::user()->role->name == 'admin')
    <div class="row mt-3">
      <div class="col-lg-4">
        <div class="card">
          <div class="card-body">
            <div class="form-group">
              <div class="row">
                <div class="col-lg-6">
                  <label for="mpkk" class="form-control-label">Pilih MPKK</label>
                  <select class="custom-select" id="mpkk">
                      <option value="" selected>Semua</option>
                      @foreach($mpkk as $m)
                      <option value="{{ $m->name }}" {{ Request::get('mpkk') == $m->name ? 'selected' : '' }}>{{ $m->name }}</option>
                      @endforeach
                  </select>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-4 mt-3 mt-lg-0">
        <div class="card">
          <div class="seo-fact sbg1">
              <div class="p-4 d-flex justify-content-between align-items-center">
                  <div class="seofct-icon"><i class="fa fa-book"></i> Jumlah Culaan</div>
                  <h2>{{ $culaan->count() }}</h2>
              </div>
              <canvas id="culaan-chart" height="50"></canvas>
          </div>
        </div>
      </div>
      {{-- <div class="col-lg-4">
        <div class="card">
          <div class="seo-fact sbg2">
            <div class="p-4 d-flex justify-content-between align-items-center">
              <div class="seofct-icon"><i class="fa fa-user"></i> Jumlah User </div>
              <h2>3,984</h2>
            </div>
            <canvas id="seolinechart2" height="50"></canvas>
          </div>
        </div>
      </div> --}}
    </div>
    <div class="row mt-3 mt-lg-5">
      <div class="col-lg-12">
        <div class="card">
          <div class="card-body text-center">
              <h4 class="header-title">Keahlian Parti</h4>
              <canvas id="keahlian-parti-chart" style="height:233px"></canvas>
          </div>
        </div>
      </div>
      <div class="col-lg-12 mt-3">
        <div class="card h-full">
          <div class="card-body text-center">
              <h4 class="header-title">Kecenderungan Politik</h4>
              <canvas id="kecenderungan-politik-chart" style="height:233px"></canvas>
          </div>
        </div>
      </div>
      <div class="col-lg-12 mt-3">
        <div class="card h-full">
          <div class="card-body text-center">
              <h4 class="header-title">Jenis Sumbangan Yang Disalurkan</h4>
              <canvas id="jenis-sumbangan-chart" style="height:233px"></canvas>
          </div>
        </div>
      </div>
    </div>
    <div class="row mt-3 mt-lg-5">
      <div class="col-lg-12">
        <div class="card h-full">
          <div class="card-body text-center">
              <h4 class="header-title">Bantuan Lain Yang Sedang Diterima</h4>
              <canvas id="bantuan-lain-chart" style="height:233px"></canvas>
          </div>
        </div>
      </div>
      <div class="col-lg-12 mt-3">
        <div class="card h-full">
          <div class="card-body text-center">
              <h4 class="header-title">Tujuan Sumbangan</h4>
              <canvas id="tujuan-sumbangan-chart" style="height:233px"></canvas>
          </div>
        </div>
      </div>
      <div class="col-lg-12 mt-3">
        <div class="card h-full">
          <div class="card-body text-center">
              <h4 class="header-title">Jenis Pekerjaan</h4>
              <canvas id="jenis-pekerjaan-chart" style="height:233px"></canvas>
          </div>
        </div>
      </div>
    </div>
    <div class="row mt-3 mt-lg-5">
      <div class="col-lg-12">
        <div class="card h-full">
          <div class="card-body text-center">
            <h4 class="header-title">Jumlah Pengundi Mengikuti Umur</h4>
            <canvas id="umur-chart" style="height: 233px;"></canvas>
          </div>
        </div>
      </div>
    </div>
    <div class="row mt-3 mt-lg-5">
      <div class="col-lg-12">
        <div class="card h-full">
          <div class="card-body text-center">
              <h4 class="header-title">Jumlah Keahlian Parti Mengikuti Bangsa</h4>
            <canvas id="jumlah-keahlian-parti-chart" style="height: 233px;"></canvas>
          </div>
        </div>
      </div>
    </div>
    <div class="row mt-3 mt-lg-5">
      <div class="col-lg-12">
        <div class="card h-full">
          <div class="card-body text-center">
            <h4 class="header-title">Jumlah Pengundi Mengikuti Julat Pendapatan</h4>
            <canvas id="jumlah-pendapatan-chart" style="height: 233px;"></canvas>
          </div>
        </div>
      </div>
    </div>
  @endif
@endsection

@section('script')
    <script>

      function randomColor(){
        return "#" + Math.floor(Math.random()*16777215).toString(16);
      }
      
      colorCollection = ["#8919FE", "#12C498", "#F8CB3F", "#E36D68", '#003f5c', '#ff6361', '#ffa600', '#37C5F6', '#F637E8', '#00764A', '#9F3700', '#D39999'];

      let position;
      if($(window).width() >= 1000){
        position = 'right'
      }else{
        position = 'top'
      }

      function pieChart(elmId, label, data){
        if ($(`#${elmId}`).length) {
          var color = [];
          for(let i = 0; i < data.length; i++){
            if(colorCollection[i]){
              color[i] = colorCollection[i];
            }else{
              color[i] = randomColor();
            }
          }

          var ctx = document.getElementById(`${elmId}`).getContext('2d');
          var chart = new Chart(ctx, {
              // The type of chart we want to create
              type: 'doughnut',
              // The data for our dataset
              data: {
                  labels: label,
                  datasets: [{
                      backgroundColor: color,
                      borderColor: '#fff',
                      data: data,
                  }]
              },
              // Configuration options go here
              options: {
                  responsive: true,
                  legend: {
                    labels: {
                      usePointStyle: true,
                      pointStyle: 'circle',
                    },
                    position: position,
                  },
                  animation: {
                      easing: "easeInOutBack"
                  }
              }
          });
          
          $(window).resize(function(){
            let position;
            if($(window).width() <= 1000){
              position = 'top'
            }else{
              position = 'right'
            }
            chart.options.legend.position = position
            chart.update()
          })

        }
      }

      $(document).ready(function () {
        $("#mpkk").change(function() {
          var option = $(this).find(':selected');
          url = '{{ request()->segment(count(request()->segments())) }}'
          url  += '?mpkk=' + option.val();
          window.location.href = url;
        });

        //Culaan Chart
        if ($('#culaan-chart').length) {
          var ctx = document.getElementById("culaan-chart").getContext('2d');
          var thisYear = new Date().getFullYear()
          var chart = new Chart(ctx, {
              // The type of chart we want to create
              type: 'line',
              // The data for our dataset
              data: {
                  labels: [`January ${thisYear}`, `February ${thisYear}`, `March ${thisYear}`, `April ${thisYear}`, `May ${thisYear}`, `June ${thisYear}`, `July ${thisYear}`, `August ${thisYear}`, `September ${thisYear}`, `October ${thisYear}`, `November ${thisYear}`, `December ${thisYear}`],
                  datasets: [{
                      label: "Culaan",
                      backgroundColor: "rgba(104, 124, 247, 0.6)",
                      borderColor: '#8596fe',
                      data: @json($culaanMontly),
                  }]
              },
              // Configuration options go here
              options: {
                  legend: {
                      display: false
                  },
                  animation: {
                      easing: "easeInOutBack"
                  },
                  scales: {
                      yAxes: [{
                          display: !1,
                          ticks: {
                              fontColor: "rgba(0,0,0,0.5)",
                              fontStyle: "bold",
                              beginAtZero: !0,
                              maxTicksLimit: 5,
                              padding: 0
                          },
                          gridLines: {
                              drawTicks: !1,
                              display: !1
                          }
                      }],
                      xAxes: [{
                          display: !1,
                          gridLines: {
                              zeroLineColor: "transparent"
                          },
                          ticks: {
                              padding: 0,
                              fontColor: "rgba(0,0,0,0.5)",
                              fontStyle: "bold"
                          }
                      }]
                  },
                  elements: {
                      line: {
                          tension: 0, // disables bezier curves
                      }
                  }
              }
          });
        }

        //Umur Chart
        if($('#umur-chart').length){
          var ctx = document.getElementById("umur-chart").getContext('2d');
          const labels = @json($umurLabel);
          var chart = new Chart(ctx, {
            type: 'bar',
            data: {  
              labels: labels,
              datasets: [{
                label: 'Umur / Jatina',
                data: @json($umurData),
                backgroundColor: "#00adef",
                borderWidth: 1
              }]
            },
            options: {
              legend: {
                  display: true
              },
              animation: {
                  easing: "easeInOutBack"
              },
              scales: {
                yAxes: [{
                    ticks: {
                        beginAtZero:true
                    }
                }]
              }
            }
          })
        }

        //Jumlah Pendapatan Chart
        if($('#jumlah-pendapatan-chart').length){
          var ctx = document.getElementById("jumlah-pendapatan-chart").getContext('2d');
          const labels = @json($jumlahPendapatanLabel);
          var chart = new Chart(ctx, {
            type: 'bar',
            data: {  
              labels: labels,
              datasets: [{
                label: 'Jumlah Pendapatan',
                data: @json($jumlahPendapatanData),
                backgroundColor: "#00adef",
                borderWidth: 1
              }]
            },
            options: {
              legend: {
                  display: true
              },
              animation: {
                  easing: "easeInOutBack"
              },
              scales: {
                yAxes: [{
                    ticks: {
                        beginAtZero:true
                    }
                }]
              }
            }
          })
        }

        //Jumlah keahlian parti
        if($('#jumlah-keahlian-parti-chart').length){
          var ctx = document.getElementById("jumlah-keahlian-parti-chart").getContext('2d');
          const labels = @json($keahlianPartiLabel);
          const label = @json($bangsaLabel);
          const datasetLabels = []
          const data = @json($keahlianPartiBangsaData);
          const color = []
          const datasets = []

          for(i = 0; i < label.length; i++){
            datasetLabels[i] = label[i]
            if(colorCollection[i]){
              color[i] = colorCollection[i];
            }else{
              color[i] = randomColor();
            }
          } 
          
          for(i = 0; i < label.length; i++){
            datasets.push({
              label: datasetLabels[i],
              data: data[i],
              backgroundColor: color[i],
            });
          }

          var chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                legend: {
                  position: position,
                },
                scales: {
                  xAxes: [{
                      stacked: true
                  }],
                  yAxes: [{
                      stacked: true
                  }]
                }
            }
          })
          
          
          $(window).resize(function(){
            let position;
            if($(window).width() <= 1000){
              position = 'top'
            }else{
              position = 'right'
            }
            chart.options.legend.position = position
            chart.update()
          })
        }

        pieChart('keahlian-parti-chart', @json($keahlianPartiLabel), @json($keahlianPartiData))
        pieChart('kecenderungan-politik-chart', @json($kecenderunganPolitikLabel), @json($kecenderunganPolitikData))
        pieChart('jenis-sumbangan-chart', @json($jenisSumbanganLabel), @json($jenisSumbanganData))
        pieChart('bantuan-lain-chart', @json($bantuanLainLabel), @json($bantuanLainData))
        pieChart('tujuan-sumbangan-chart', @json($tujuanSumbanganLabel), @json($tujuanSumbanganData))
        pieChart('jenis-pekerjaan-chart', @json($jenisPekerjaanLabel), @json($jenisPekerjaanData))
      });


    </script>
@endsection
@extends('layouts.app')

@section('title', 'Report Mula Culaan')

@section('style')
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.3/css/responsive.jqueryui.min.css">
@endsection

@section('breadcrumb_title', 'Report Mula Culaan')
@section('breadcrumbs')
  <li><a href="{{ route('report-mula-culaan') }}">Report</a></li>
  <li>Mula Culaan</li>
@endsection


@section('content')
  <div class="col-lg-12 mt-3">
    <div class="card">
      <div class="card-body">
        <div class="row">
          <div class="col-lg-8">
            <form action="{{ route('report-mula-culaan') }}" method="GET">
              <div class="row">
                <div class="col-lg-3">
                  <input type="date" name="from" value="{{ Request::get('from') }}" class="form-control">
                </div>
                <div class="col-lg-3 mt-3 mt-lg-0">
                  <input type="date" name="to" value="{{ Request::get('to') }}" class="form-control">
                </div>
                <div class="col-lg-3 mt-3 mt-lg-0">
                  <button type="submit" class="btn btn-primary btn-rounded btn-block"><i class="fa fa-filter mr-1"></i>Filter</button>
                </div>
              </div>
            </form>
          </div>
          <div class="col-lg-4 mt-3 mt-lg-0 text-right">
            <form action="{{ route('export-excel-mula-culaan') }}" method="GET" class="d-inline">
              <input type="hidden" name="from" value="{{ Request::get('from') }}">
              <input type="hidden" name="to" value="{{ Request::get('to') }}">
              <button class="btn btn-success"><i class="fa fa-file-excel-o mr-1"></i> Export Excel</button>  
            </form>
          </div>
        </div>
        <hr>
        <div class="table-responsive">
          <table class="table table-hover" id="dataTable">
            <thead>
              <tr>
                <th></th>
                <th class="align-middle">#</th>
                <th class="align-middle">User</th>
                <th class="align-middle">Nama</th>
                <th class="align-middle">No Kad</th>
                <th class="align-middle">Umur</th>
                <th class="align-middle">Tel</th>
                <th class="align-middle">Bangsa</th>
                <th class="align-middle">Alamat</th>
                <th class="align-middle">Alamat 2</th>
                <th class="align-middle">Poskod</th>
                <th class="align-middle">Negeri</th>
                <th class="align-middle">Bandar</th>
                <th class="align-middle">Kadun</th>
                <th class="align-middle">MPKK</th>
                <th class="align-middle">Bilangan Isi Rumah</th>
                <th class="align-middle">Pendapatan Isi Rumah</th>
                <th class="align-middle">Pekerjaan</th>
                <th class="align-middle">Pemilik Rumah</th>
                <th class="align-middle">Jenis Sumbangan</th>
                <th class="align-middle">Tujuan Sumbangan</th>
                <th class="align-middle">Bantuan Lain</th>
                <th class="align-middle">Keahlian Parti</th>
                <th class="align-middle">Kecenderungan Politik</th>
                <th class="align-middle">Nota</th>
                <th class="align-middle">Tarikh dan Masa</th>
                <th class="align-middle">Muat Naik Salinan Kad Pengenalan</th>
              </tr>
            </thead>
            <tbody>
                @foreach($mulaCulaan as $m)
                  <tr>
                    <td></td>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $m->user->name }}</td>
                    <td>{{ $m->nama }}</td>
                    <td>{{ $m->no_kad }}</td>
                    <td>{{ $m->umur }}</td>
                    <td>{{ $m->no_telp }}</td>
                    <td>{{ $m->bangsa }}</td>
                    <td>{{ $m->alamat }}</td>
                    <td>{{ $m->alamat_2 }}</td>
                    <td>{{ $m->poskod }}</td>
                    <td>{{ $m->negeri }}</td>
                    <td>{{ $m->bandar }}</td>
                    <td>{{ $m->kadun }}</td>
                    <td>{{ $m->mpkk }}</td>
                    <td>{{ $m->bilangan_isi_rumah }}</td>
                    <td>{{ $m->jumlah_pendapatan_isi_rumah }}</td>
                    <td>{{ $m->pekerjaan }}</td>
                    <td>{{ $m->pemilik_rumah }}</td>
                    <td>{{ $m->jenis_sumbangan }}</td>
                    <td>{{ $m->tujuan_sumbangan }}</td>
                    <td>{{ $m->bantuan_lain }}</td>
                    <td>{{ $m->keahlian_partai }}</td>
                    <td>{{ $m->kecenderungan_politik }}</td>
                    <td>{{ $m->nota }}</td>
                    <td>{{ $m->tarikh_dan_masa }}</td>
                    <td><img src="{{ asset('ic') }}/{{ $m->ic }}" style="max-height: 300px;"></td>
                  </tr>
                @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
@endsection
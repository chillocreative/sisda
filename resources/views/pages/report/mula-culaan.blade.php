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
          <div class="col-lg-4 mt-3 mt-lg-0 text-right d-none d-sm-block">
            <form action="{{ route('export-excel-mula-culaan') }}" method="GET" class="d-inline">
              <input type="hidden" name="from" value="{{ Request::get('from') }}">
              <input type="hidden" name="to" value="{{ Request::get('to') }}">
              <button class="btn btn-success"><i class="fa fa-file-excel-o mr-1"></i> Export Excel</button>  
            </form>
          </div>
        </div>
        <hr>
        <div class="col-lg-12">
          <div class="table-responsive">
            <table class="table table-hover" id="dataTable">
              <thead>
                <tr>
                  <th class="align-middle text-center" style="white-space: nowrap">#</th>
                  @if(Auth::user()->role_id !== 3)<th class="align-middle text-center" style="white-space: nowrap; min-width: 150px">User</th>@endif
                  <th class="align-middle text-center" style="white-space: nowrap; min-width: 150px">Nama</th>
                  <th class="align-middle text-center" style="white-space: nowrap">No Kad</th>
                  <th class="align-middle text-center" style="white-space: nowrap">Umur</th>
                  <th class="align-middle text-center" style="white-space: nowrap">Tel</th>
                  <th class="align-middle text-center" style="white-space: nowrap">Bangsa</th>
                  <th class="align-middle text-center" style="white-space: nowrap; min-width: 200px;">Alamat</th>
                  <th class="align-middle text-center" style="white-space: nowrap">Poskod</th>
                  <th class="align-middle text-center" style="white-space: nowrap">Negeri</th>
                  <th class="align-middle text-center" style="white-space: nowrap">Bandar</th>
                  <th class="align-middle text-center" style="white-space: nowrap">Kadun</th>
                  <th class="align-middle text-center" style="white-space: nowrap">MPKK</th>
                  <th class="align-middle text-center" style="white-space: nowrap">Bilangan Isi Rumah</th>
                  <th class="align-middle text-center" style="white-space: nowrap">Pendapatan Isi Rumah</th>
                  <th class="align-middle text-center" style="white-space: nowrap">Pekerjaan</th>
                  <th class="align-middle text-center" style="white-space: nowrap">Pemilik Rumah</th>
                  <th class="align-middle text-center" style="white-space: nowrap">Jenis Sumbangan</th>
                  <th class="align-middle text-center" style="white-space: nowrap; min-width: 150px">Tujuan Sumbangan</th>
                  <th class="align-middle text-center" style="white-space: nowrap; min-width: 200px">Bantuan Lain</th>
                  <th class="align-middle text-center" style="white-space: nowrap">Keahlian Parti</th>
                  <th class="align-middle text-center" style="white-space: nowrap">Kecenderungan Politik</th>
                  <th class="align-middle text-center" style="white-space: nowrap; min-width: 200px;">Nota</th>
                  <th class="align-middle text-center" style="white-space: nowrap">Tarikh dan Masa</th>
                  <th class="align-middle text-center" style="white-space: nowrap">Muat Naik Salinan Kad Pengenalan</th>
                  <th class="align-middle text-center d-none d-sm-block">Action</th>
                </tr>
              </thead>
              <tbody>
                  @foreach($mulaCulaan as $m)
                    <tr>
                      <td style="vertical-align: middle">{{ $loop->iteration }}</td>
                      @if(Auth::user()->role_id !== 3)<td style="vertical-align: middle">{{ $m->user->name }}</td>@endif
                      <td style="vertical-align: middle">{{ $m->nama }}</td>
                      <td style="vertical-align: middle">{{ $m->no_kad }}</td>
                      <td style="vertical-align: middle">{{ $m->umur }}</td>
                      <td style="vertical-align: middle">{{ $m->no_telp }}</td>
                      <td style="vertical-align: middle">{{ $m->bangsa }}</td>
                      <td style="vertical-align: middle">{{ $m->alamat }}</td>
                      <td style="vertical-align: middle">{{ $m->poskod }}</td>
                      <td style="vertical-align: middle">{{ $m->negeri }}</td>
                      <td style="vertical-align: middle">{{ $m->bandar }}</td>
                      <td style="vertical-align: middle">{{ $m->kadun }}</td>
                      <td style="vertical-align: middle">{{ $m->mpkk }}</td>
                      <td style="vertical-align: middle">{{ $m->bilangan_isi_rumah }}</td>
                      <td style="vertical-align: middle">{{ $m->jumlah_pendapatan_isi_rumah }}</td>
                      <td style="vertical-align: middle">{{ $m->pekerjaan }}</td>
                      <td style="vertical-align: middle">{{ $m->pemilik_rumah }}</td>
                      <td style="vertical-align: middle">{{ $m->jenis_sumbangan }}</td>
                      <td style="vertical-align: middle">{{ $m->tujuan_sumbangan }}</td>
                      <td style="vertical-align: middle">{{ $m->bantuan_lain }}</td>
                      <td style="vertical-align: middle">{{ $m->keahlian_partai }}</td>
                      <td style="vertical-align: middle">{{ $m->kecenderungan_politik }}</td>
                      <td style="vertical-align: middle">{{ $m->nota }}</td>
                      <td style="vertical-align: middle">{{ $m->tarikh_dan_masa }}</td>
                      <td style="vertical-align: middle"><a href="{{ $m->ic_url }}" target="_blank"><img src="{{ asset('ic') }}/{{ $m->ic }}" style="max-height: 150px;"></a></td>
                      <td style="vertical-align: middle">
                        <div class="d-flex">
                          <a href="{{ asset('ic') }}/{{ $m->ic }}" class="btn btn-sm btn-success d-none d-sm-block" download><i class="fa fa-download"></i> IC</a>
                          <a href="{{ route('mula-culaan.edit', $m->id) }}" class="btn btn-sm ml-2 btn-warning"><i class="fa fa-pencil"></i></a>
                          <form action="{{ route('report-mula-culaan.destroy', $m->id) }}" method="post" class="ml-2">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Anda ingin memadam data?')"><i class="fa fa-trash"></i></button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
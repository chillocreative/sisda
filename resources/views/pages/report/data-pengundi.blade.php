@extends('layouts.app')

@section('title', 'Report Data Pengundi')

@section('breadcrumb_title', 'Report Data Pengundi')
@section('breadcrumbs')
  <li><a href="{{ route('report-data-pengundi') }}">Report</a></li>
  <li>Data Pengundi</li>
@endsection


@section('content')
  <div class="col-lg-12 mt-3">
    <div class="card">
      <div class="card-body">
        <div class="row">
          <div class="col-lg-8">
            <form action="{{ route('report-data-pengundi') }}" method="GET">
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
            <form action="{{ route('export-excel-data-pengundi') }}" method="GET" class="d-inline">
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
                  <th class="align-middle text-center" style="white-space: nowrap">No Tel</th>
                  <th class="align-middle text-center" style="white-space: nowrap">Bangsa</th>
                  <th class="align-middle text-center" style="white-space: nowrap">Hubungan</th>
                  <th class="align-middle text-center" style="white-space: nowrap; min-width: 200px">Alamat</th>
                  <th class="align-middle text-center" style="white-space: nowrap">Poskod</th>
                  <th class="align-middle text-center" style="white-space: nowrap">Negeri</th>
                  <th class="align-middle text-center" style="white-space: nowrap">Bandar</th>
                  <th class="align-middle text-center" style="white-space: nowrap">Parlimen</th>
                  <th class="align-middle text-center" style="white-space: nowrap">Kadun</th>
                  <th class="align-middle text-center" style="white-space: nowrap">Keahlian Parti</th>
                  <th class="align-middle text-center" style="white-space: nowrap">Kecenderungan Politik</th>
                  <th class="align-middle text-center" style="white-space: nowrap">Action</th>
                </tr>
              </thead>
              <tbody>
                  @foreach($dataPengundi as $d)
                    <tr>
                      <td class="align-middle">{{ $loop->iteration }}</td>
                      @if(Auth::user()->role_id !== 3)<td class="align-middle">{{ $d->user->name }}</td>@endif
                      <td class="align-middle">{{ $d->name }}</td>
                      <td class="align-middle">{{ $d->no_kad }}</td>
                      <td class="align-middle">{{ $d->umur }}</td>
                      <td class="align-middle">{{ $d->phone }}</td>
                      <td class="align-middle">{{ $d->bangsa }}</td>
                      <td class="align-middle">{{ $d->hubungan }}</td>
                      <td class="align-middle">{{ $d->alamat }}</td>
                      <td class="align-middle">{{ $d->poskod }}</td>
                      <td class="align-middle">{{ $d->negeri }}</td>
                      <td class="align-middle">{{ $d->bandar }}</td>
                      <td class="align-middle">{{ $d->parlimen }}</td>
                      <td class="align-middle">{{ $d->kadun }}</td>
                      <td class="align-middle">{{ $d->keahlian_partai }}</td>
                      <td class="align-middle">{{ $d->kecenderungan_politik }}</td>
                      <td class="align-middle">
                        <div class="d-flex">
                          <a href="{{ route('data-pengundi.edit', $d->id) }}" class="btn btn-sm btn-warning"><i class="fa fa-pencil"></i></a>
                          <form action="{{ route('report-data-pengundi.destroy', $d->id) }}" method="post" class="ml-2">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger ml-2" onclick="return confirm('Anda yakin ingin menghapus data ini ?');"><i class="fa fa-trash"></i></button>
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
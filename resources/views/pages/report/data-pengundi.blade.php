@extends('layouts.app')

@section('title', 'Report Data Pengundi')

@section('breadcrumb_title', 'Report Data Pengundi')
@section('breadcrumbs')
  <li><a href="{{ route('report-mula-culaan') }}">Report</a></li>
  <li>Data Pengundi</li>
@endsection


@section('content')
  <div class="col-lg-12 mt-3">
    <div class="card">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover" id="dataTable">
            <thead>
              <tr>
                <th class="align-middle">#</th>
                <th class="align-middle">User</th>
                <th class="align-middle">Nama</th>
                <th class="align-middle">No Kad</th>
                <th class="align-middle">Umur</th>
                <th class="align-middle">No Tel</th>
                <th class="align-middle">Bangsa</th>
                <th class="align-middle">Alamat</th>
                <th class="align-middle">Alamat 2</th>
                <th class="align-middle">Poskod</th>
                <th class="align-middle">Negeri</th>
                <th class="align-middle">Bandar</th>
                <th class="align-middle">Parlimen</th>
                <th class="align-middle">Kadun</th>
                <th class="align-middle">Keahlian Parti</th>
                <th class="align-middle">Kecenderungan Politik</th>
              </tr>
            </thead>
            <tbody>
                @foreach($dataPengundi as $d)
                  <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $d->user->name }}</td>
                    <td>{{ $d->nama }}</td>
                    <td>{{ $d->no_kad }}</td>
                    <td>{{ $d->umur }}</td>
                    <td>{{ $d->phone }}</td>
                    <td>{{ $d->bangsa }}</td>
                    <td>{{ $d->alamat }}</td>
                    <td>{{ $d->alamat_2 }}</td>
                    <td>{{ $d->poskod }}</td>
                    <td>{{ $d->negeri }}</td>
                    <td>{{ $d->bandar }}</td>
                    <td>{{ $d->parlimen }}</td>
                    <td>{{ $d->kadun }}</td>
                    <td>{{ $d->keahlian_partai }}</td>
                    <td>{{ $d->kecenderungan_politik }}</td>
                  </tr>
                @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
@endsection
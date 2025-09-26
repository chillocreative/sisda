@extends('pages.dashboard.app')

@section('content')
  <div class="row mt-3">
    <div class="col-lg-4">
      <a href="{{ route('mula-culaan.index') }}" class="button-29" role="button" style="width: 100%; height: 100px;">MULA CULAAN</a>
    </div>
    <div class="col-lg-4 mt-3 mt-lg-0">
      <a href="{{ route('data-pengundi.index') }}" class="button-29" role="button" style="width: 100%; height: 100px;">MASUK DATA PENGUNDI</a>
    </div>
  </div>
  <hr class="my-5">
  <div class="row">
    <div class="col-lg-12">
      <div class="card">
        <div class="card-body">
          <div class="button-29">CARIAN DATA</div>
          <input type="text" id="carian" placeholder="Masukkan No Kad Pengenalan" class="form-control mt-3">
          <div class="table-group d-none mt-4" id="table-group">
            <h4>Mula Culaan</h4>
            <div class="table-responsive mt-3">
              <table class="table" id="table-mula-culaan">
                <thead>
                  <tr>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">No</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">Nama</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">No Kad Pengenalan</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">Umur</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">No Tel</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">Bangsa</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">Alamat</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">Poskod</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">Negeri</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">Bandar</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">KADUN</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">MPKK</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">Bilangan Isi Rumah</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">Pendapatan Isi Rumah</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">Pekerjaan</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">Pemilik Rumah</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">Jenis Sumbangan</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">Tujuan Sumbangan</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">Bantuan Lain</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">Keahlian Parti</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">Kecenderungan Politik</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">Nota</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">Tarikh & Masa</th>
                    <th class="text-center"h style="vertical-align: middle">Muat Naik Salinan Kad Pengenalan</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
            <h4 class="mt-4">Data Pengundi</h4>
            <div class="table-responsive mt-3">
              <table class="table" id="table-pengundi">
                <thead>
                  <tr>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">No</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">Nama</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">No Kad Pengenalan</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">Umur</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">No Tel</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">Bangsa</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">Hubungan</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">Alamat</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">Poskod</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">Negeri</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">Bandar</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">Parlimen</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">KADUN</th>
                    <th class="text-center" style="vertical-align: middle; white-space: nowrap">Keahlian Parti</th>
                    <th class="text-center"h style="vertical-align: middle">Kecenderungan Politik</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

@section('script')
  @include('pages.dashboard._search_script')
@endsection
@extends('layouts.app')

@section('title', 'Mula Culaan')

@section('content')
  <div class="row">
    <div class="col-lg-12">
      <h1>Mula Culaan</h1>
    </div>
  </div>
  <div class="row mt-3">
    <div class="col-lg-12">
      <div class="card">
        <div class="card-body">
          @if($errors->any())
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul>
              @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          @endif
          <form action="{{ route('mula-culaan.store') }}" method="POST">
          @csrf
            <div class="row">
              <div class="col-lg-6">
                <div class="form-group">
                  <label for="name" class="form-control-label">Nama</label>
                  <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                </div>
                <div class="form-group">
                  <label for="no_kad" class="form-control-label">No Kad Pengenalan</label>
                  <input type="text" name="no_kad" id="no_kad" class="form-control @error('no_kad') is-invalid @enderror" value="{{ old('no_kad') }}" required>
                </div>
                <div class="form-group">
                  <label for="alamat" class="form-control-label">
                    Alamat
                    <button type="button" class="btn-tooltip fa fa-info-circle text-dark ml-2" data-toggle="tooltip" data-placement="right" title="Alamat tempat tinggal yang terkini."></button>  
                  </label>
                  <textarea name="alamat" id="alamat" cols="30" rows="5" class="form-control">{{ old('alamat') }}</textarea>
                </div>
                <div class="form-group">
                  <label for="kadun" class="form-control-label">Kadun</label>
                  <select name="kadun" id="kadun" class="form-control py-0" required>
                    <option value="" disabled selected>Pilih Kadun</option>
                    @foreach($kadun as $k)
                      <option value="{{ $k->id }}" data-id="{{ $k->id }}">{{ $k->name }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group">
                  <label for="mpkk" class="form-control-label">
                    MPKK
                    <button type="button" class="btn-tooltip fa fa-info-circle text-dark ml-2" data-toggle="tooltip" data-placement="right" title="Majlis Pengurus Komuniti Kampung (MPKK)."></button>  
                  </label>
                  <select name="mpkk" id="mpkk" class="form-control py-0" required disabled>
                    <option value="" disabled selected>Pilih MPKK</option>
                  </select>
                </div>
                <div class="form-group">
                  <label for="bilangan-isi-rumah" class="form-control-label">
                    Bilangan Isi Rumah
                    <button type="button" class="btn-tooltip fa fa-info-circle text-dark ml-2" data-toggle="tooltip" data-placement="right" title="Individu atau sekumpulan orang (sama ada mempunyai pertalian atau tidak) yang tinggal di dalam satu rumah dan membuat perbelanjaan bagi kegunaan harian seperti makanan dan sebagainya untuk kegunaan bersama."></button>  
                  </label>
                  <input type="number" name="bilangan_isi_rumah" id="bilangan-isi-rumah" class="form-control @error('bilangan_isi_rumah') is-invalid @enderror" value="{{ old('bilangan_isi_rumah') }}">
                </div>
                <div class="form-group">
                  <label for="jumlah-pendapatan" class="form-control-label">
                    Jumlah Pendapatan Isi Rumah
                    <button type="button" class="btn-tooltip fa fa-info-circle text-dark ml-2" data-toggle="tooltip" data-placement="right" title="Pendapatan keseluruhan isi rumah, sama ada secara tunai atau sebagainya dan boleh dirujuk sebagai pendapatan kasar."></button>  
                  </label>
                  <input type="number" name="jumlah_pendapatan_isi_rumah" id="jumlah-pendapatan" class="form-control @error('jumlah_pendapatan_isi_rumah') is-invalid @enderror" value="{{ old('jumlah_pendapatan_isi_rumah') }}">
                </div>
              </div>
              <div class="col-lg-6">
                <div class="form-group">
                  <label for="tujuan-sumbangan" class="form-control-label">Tujuan Sumbangan Disalurkan</label>
                  <select name="tujuan_sumbangan" id="tujuan-sumbangan" class="form-control py-0" required>
                    <option value="" selected disabled>Pilih Tujuan Sumbangan</option>
                    @foreach($tujuanSumbangan as $t)
                      <option value="{{ $t->name }}">{{ $t->name }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group">
                  <label for="jenis-sumbangan" class="form-control-label">Jenis Sumbangan Yang Disalurkan</label>
                  @foreach($jenisSumbangan as $s)
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="jenis_sumbangan[]" value="{{ $s->name }}" id="jenisSumbanganId{{ $s->id }}">
                      <label class="form-check-label" for="jenisSumbanganId{{ $s->id }}">
                        {{ $s->name }}
                      </label>
                    </div>
                  @endforeach
                </div>
                <div class="form-group">
                  <label for="bantuan-lain" class="form-control-label">Bantuan Lain Yang Sedang Diterima</label>
                  @foreach($bantuanLain as $b)
                    <div class="form-check">
                      <input class="form-check-input" name="bantuan_lain[]" type="checkbox" value="{{ $b->name }}" id="bantuanLainId{{ $b->id }}">
                      <label class="form-check-label" for="bantuanLainId{{ $b->id }}">
                        {{ $b->name }}
                      </label>
                    </div>
                  @endforeach
                  <label for="bantuan-lain-other" class="mt-2">
                    <div class="d-flex align-items-center">
                      Other
                      <input type="text" name="bantuan_lain[]" id="bantuan-lain-other" class="form-control form-control-sm ml-2">
                    </div>
                  </label>
                </div>
                <div class="form-group">
                  <label for="keahlian-partai" class="form-control-label">
                    Keahlian Parti Penerima Sumbangan
                    <button type="button" class="btn-tooltip fa fa-info-circle text-dark ml-2" data-toggle="tooltip" data-placement="right" title="Tooltip on keahlian partai"></button>  
                  </label>
                  @foreach($keahlianPartai as $k)
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="keahlian_partai[]" value="{{ $k->name }}" id="keahlianPartaiId{{ $k->id }}">
                      <label class="form-check-label" for="keahlianPartaiId{{ $k->id }}">
                        {{ $k->name }}
                      </label>
                    </div>
                  @endforeach
                  <label for="keahlian-partai-other" class="mt-2">
                    <div class="d-flex align-items-center">
                      Other
                      <input type="text" name="keahlian_partai[]" id="keahlian-partai-other" class="form-control form-control-sm ml-2">
                    </div>
                  </label>
                </div>
                <div class="form-group">
                  <label for="kecenderungan-partai" class="form-control-label">Kecenderungan Politik</label>
                  @foreach($kecenderunganPolitik as $k)
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="kecenderungan_politik[]" value="{{ $k->name }}" id="kecenderunganPolitikId{{ $k->id }}">
                      <label class="form-check-label" for="kecenderunganPolitikId{{ $k->id }}">
                        {{ $k->name }}
                      </label>
                    </div>
                  @endforeach
                </div>
                <div class="form-group">
                  <label for="nota" class="form-control-label">
                    Nota
                    <button type="button" class="btn-tooltip fa fa-info-circle text-dark ml-2" data-toggle="tooltip" data-placement="right" title="Untuk rujukan bagi tindakan Pusat Khidmat ADUN KEADILAN / CABANG."></button>
                  </label>
                  <input type="text" name="nota" id="nota" class="form-control @error('nota') is-invalid @enderror"  value="{{ old('nota') }}">
                </div>
                <div class="form-group">
                  <label for="tarikh-dan-masa" class="form-control-label">
                    Tarikh dan Masa
                    <button type="button" class="btn-tooltip fa fa-info-circle text-dark ml-2" data-toggle="tooltip" data-placement="right" title="Tarikh dan masa serahan bantuan."></button>
                  </label>
                  <input type="datetime-local" name="tarikh_dan_masa" id="tarikh-dan-masa" class="form-control" required>
                </div>
                <div class="form-group">
                  <button type="submit" class="btn btn-primary btn-block">Submit</button>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection

@section('style')
  <style>
    .form-control-label{
      /* font-size: 16px; */
      color: #222;
    }
    .form-group{
      margin-top: 30px !important;
    }
    .btn-tooltip{
      background-color: transparent;
      border: 0;
    }
  </style>
@endsection

@section('script')
  <script>
    $(document).ready(function(){
      $('[data-toggle="tooltip"]').tooltip()

      $('#kadun').on('change', function(){
        $('#mpkk').removeAttr('disabled');
        $.ajax({
          url: '{{ route('get-mpkk-specific') }}',
          method: 'POST',
          data: {
              _token: '{{ csrf_token() }}',
              id: $(this).val(),
          },
          success: function (response) {
              $('#mpkk').empty().append(`<option value="" disabled selected>Pilih MPKK</option>`);

              $.each(response, function (index, data) {
                  $('#mpkk').append(new Option(data.name, data.name))
              })
          },
          error: err => {
              console.log(err);
          }
        })
      })
    });
  </script>
@endsection
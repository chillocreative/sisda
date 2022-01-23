@extends('layouts.app')

@section('title', 'Data Pengundi')

@section('breadcrumb_title', 'Data Pengundi')
@section('breadcrumbs')
  <li>Data Pengundi</li>
@endsection

@section('content')
  {{-- @if($errors->any())
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
  @endif --}}
  <div class="row mt-3">
    <div class="col-lg-12">
      <div class="card">
        <div class="card-body">
          <form action="{{ route('mula-culaan.store') }}" method="POST" enctype="multipart/form-data">
          @csrf
            <div class="row">
              <div class="col-lg-6">
                <div class="form-group">
                  <label for="name" class="form-control-label">Nama<span class="text-danger"> *</span></label>
                  <input type="text" name="name" id="name" class="form-control text-uppercase @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                  @error('name') <small class="text-danger">{{ $message }}</small>@enderror
                </div>
                <div class="row">
                  <div class="col-lg-6 form-group">
                    <label for="no_kad" class="form-control-label">No Kad Pengenalan<span class="text-danger"> *</span></label>
                    <input type="number" name="no_kad" id="no_kad" class="form-control text-uppercase @error('no_kad') is-invalid @enderror" required>
                    @error('no_kad') <small class="text-danger">{{ $message }}</small>@enderror
                  </div>
                  <div class="col-lg-6 form-group d-none" id="form-umur">
                    <label for="umur" class="form-control-label">Umur</label>
                    <div class="input-group">
                      <input name="umur" id="umur" class="form-control" value="" readonly>
                      <div class="input-group-prepend">
                        <div class="input-group-text">Tahun</div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="form-group">
                  <label for="no_telp" class="form-control-label">No Tel<span class="text-danger"> *</span></label>
                  <input type="text" name="no_telp" id="no_telp" class="form-control text-uppercase @error('no_telp') is-invalid @enderror" value="{{ old('no_tel') }}" required>
                  @error('no_telp') <small class="text-danger">{{ $message }}</small>@enderror
                </div>
                <div class="form-group">
                  <label for="bangsa" class="form-control-label">Bangsa<span class="text-danger"> *</span></label>
                  <select name="bangsa" id="bangsa" class="form-control py-1" required>
                    <option value="" disabled selected>Pilih Bangsa</option>
                    <option value="Melayu">Melayu</option>
                    <option value="Cina">Cina</option>
                    <option value="India">India</option>
                    <option value="Bumiputra">Bumiputra</option>
                    <option value="Lain-Lain">Lain-Lain</option>
                  </select>
                  @error('bangsa') <small class="text-danger">{{ $message }}</small>@enderror
                </div>
                <div class="form-group">
                  <label for="alamat" class="form-control-label">
                    Alamat<span class="text-danger"> *</span>
                    <button type="button" class="btn-tooltip fa fa-question-circle text-dark ml-2" data-toggle="tooltip" data-placement="right" title="Alamat tempat tinggal yang terkini."></button>  
                  </label>
                  <textarea name="alamat" id="alamat" rows="2" class="form-control text-uppercase" required>{{ old('alamat') }}</textarea>
                  @error('alamat') <small class="text-danger">{{ $message }}</small>@enderror
                  <textarea name="alamat_2" id="alamat_2" rows="2" class="form-control text-uppercase mt-3">{{ old('alamat_2') }}</textarea>
                </div>
                <div class="row">
                  <div class="col-lg-4 form-group">
                    <label for="poskod" class="form-control-label">Poskod<span class="text-danger"> *</span></label>
                    <input type="number" name="poskod" id="poskod" class="form-control @error('no_telp') is-invalid @enderror" value="{{ old('poskod') }}" maxlength="5" required>
                    @error('poskod') <small class="text-danger">{{ $message }}</small>@enderror
                  </div>
                  <div class="col-md-4 form-group">
                    <label for="negeri" class="form-control-label">Negeri<span class="text-danger"> *</span></label>
                    <select name="negeri" id="negeri" class="form-control py-0" required>
                      <option value="" disabled selected>Pilih Negeri</option>
                      @foreach($negeri as $n)
                        <option value="{{ $n->id }}">{{ $n->name }}</option>
                      @endforeach
                    </select>
                    @error('negeri') <small class="text-danger">{{ $message }}</small>@enderror
                  </div>
                  <div class="col-md-4 form-group">
                    <label for="bandar" class="form-control-label">
                      Bandar<span class="text-danger"> *</span>
                    </label>
                    <select name="bandar" id="bandar" class="form-control py-0" disabled required>
                      <option value="" disabled selected>Pilih Bandar</option>
                    </select>
                    @error('bandar') <small class="text-danger">{{ $message }}</small>@enderror
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6 form-group">
                    <label for="kadun" class="form-control-label">Kadun<span class="text-danger"> *</span></label>
                    <select name="kadun" id="kadun" class="form-control py-0" required>
                      <option value="" disabled selected>Pilih Kadun</option>
                      @foreach($kadun as $k)
                        <option value="{{ $k->id }}">{{ $k->name }}</option>
                      @endforeach
                    </select>
                    @error('kadun') <small class="text-danger">{{ $message }}</small>@enderror
                  </div>
                </div>
              </div>
              <div class="col-lg-6">
                <div class="form-group">
                  <label for="keahlian-partai" class="form-control-label">
                    Keahlian Parti Penerima Sumbangan<span class="text-danger"> *</span>
                    <button type="button" class="btn-tooltip fa fa-question-circle text-dark ml-2" data-toggle="tooltip" data-placement="right" title="Tooltip on keahlian partai"></button>  
                  </label>
                  {{-- <select id="keahlian-partai" name="keahlian_partai[]" class="form-control select2-multiple" multiple="multiple" data-placeholder="Pilih Keahlian Parti">
                    @foreach($keahlianPartai as $k)
                      <option value="{{ $k->name }}" class="text-upp">{{ Str::upper($k->name) }}</option>
                    @endforeach
                  </select> --}}
                  <select id="keahlian-partai" name="keahlian_partai" class="form-control py-1">
                    <option value="" selected disabled>Pilih Keahlian Parti</option>
                    @foreach($keahlianPartai as $k)
                      <option value="{{ $k->name }}">{{ Str::upper($k->name) }}</option>
                    @endforeach
                  </select>
                  @error('keahlian_partai') <small class="text-danger">{{ $message }}</small>@enderror
                </div>
                <div class="form-group">
                  <label for="kecenderungan-politik" class="form-control-label">Kecenderungan Politik<span class="text-danger"> *</span></label>
                  {{-- <select id="kecenderungan-politik" name="kecenderungan_politik[]" class="form-control select2-multiple" multiple="multiple" data-placeholder="Pilih Kecenderungan Politik">
                    @foreach($kecenderunganPolitik as $k)
                      <option value="{{ $k->name }}" class="text-upp">{{ Str::upper($k->name) }}</option>
                    @endforeach
                  </select> --}}
                  <select id="kecenderungan-politik" name="kecenderungan_politik" class="form-control py-1" required>
                    <option value="" selected disabled>Pilih Kecenderungan Politik</option>
                    @foreach($kecenderunganPolitik as $k)
                      <option value="{{ $k->name }}">{{ Str::upper($k->name) }}</option>
                    @endforeach
                  </select>
                  @error('kecenderungan_politik') <small class="text-danger">{{ $message }}</small>@enderror
                </div>
                <div class="form-group">
                  <button type="submit" class="btn btn-primary btn-block btn-rounded">Submit</button>
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
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script>
    $(document).ready(function(){
      $('[data-toggle="tooltip"]').tooltip()
      $('.select2-multiple').select2();

      $('#tujuan-sumbangan').on('change', function () {
          console.log(this)
            if(this.value == 'lain-lain'){
              console.log('success')
               $('#lain').removeClass('d-none');
            }else{
               $('#lain').addClass('d-none');
            }
         })

      $('#no_kad').on('keyup', function(){
        $('#form-umur').removeClass('d-none')
        let thisYear = new Date().getFullYear();
        let no_kad = $(this).val()
        let getYear = no_kad.substring(0, 2)
        year = parseInt(19 + getYear)
        if((thisYear - year) > 100){
          year = parseInt(20 + getYear)
        }
        year = thisYear - year
        $('#umur').val(year)
      })

      $('#negeri').on('change', function(){
        $('#bandar').removeAttr('disabled');
        $.ajax({
          url: '{{ route('get-bandar-specific') }}',
          method: 'POST',
          data: {
              _token: '{{ csrf_token() }}',
              id: $(this).val(),
          },
          success: function (response) {
              $('#bandar').empty().append(`<option value="" disabled selected>Pilih Bandar</option>`);

              $.each(response, function (index, data) {
                  $('#bandar').append(new Option(data.name, data.name))
              })
          },
          error: err => {
              console.log(err);
          }
        })
      });

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
@extends('layouts.app')

@section('title', 'Mula Culaan')

@section('breadcrumb_title', 'Mula Culaan')

@section('content')
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
                    <input type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1');" maxlength="12" name="no_kad" id="no_kad" class="form-control text-uppercase @error('no_kad') is-invalid @enderror" required>
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
                  <input type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1');" name="no_telp" id="no_telp" class="form-control text-uppercase @error('no_telp') is-invalid @enderror" value="{{ old('no_tel') }}" required>
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
                    <option value="lain">Lain-Lain</option>
                  </select>
                  @error('bangsa') <small class="text-danger">{{ $message }}</small>@enderror
                  <input id="bangsa_custom" class="form-control mt-3 d-none" name="bangsa_custom">
                </div>
                <div class="form-group">
                  <label for="alamat" class="form-control-label">
                    Alamat<span class="text-danger"> *</span>
                    <button type="button" class="btn-tooltip fa fa-question-circle text-dark ml-2" data-toggle="tooltip" data-placement="right" title="Alamat tempat tinggal yang terkini."></button>  
                  </label>
                  <textarea name="alamat" id="alamat" rows="2" class="form-control text-uppercase" required>{{ old('alamat') }}</textarea>
                  @error('alamat') <small class="text-danger">{{ $message }}</small>@enderror
                </div>
                <div class="row">
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
                  <div class="col-lg-4 form-group">
                    <label for="poskod" class="form-control-label">Poskod<span class="text-danger"> *</span></label>
                    <input type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1');" maxlength="5" name="poskod" id="poskod" class="form-control @error('no_telp') is-invalid @enderror" value="{{ old('poskod') }}" maxlength="5" required>
                    @error('poskod') <small class="text-danger">{{ $message }}</small>@enderror
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6 form-group">
                    <label for="kadun" class="form-control-label">KADUN<span class="text-danger"> *</span></label>
                    <select name="kadun" id="kadun" class="form-control py-0" required>
                      <option value="" disabled selected>Pilih KADUN</option>
                      @foreach($kadun as $k)
                        <option value="{{ $k->id }}" class="text-uppercase">{{ $k->name }}</option>
                      @endforeach
                    </select>
                    @error('kadun') <small class="text-danger">{{ $message }}</small>@enderror
                  </div>
                  <div class="col-md-6 form-group">
                    <label for="mpkk" class="form-control-label">
                      MPKK<span class="text-danger"> *</span>
                    </label>
                    <select name="mpkk" id="mpkk" class="form-control py-0" disabled required>
                      <option value="" disabled selected>Pilih MPKK</option>
                    </select>
                    @error('mpkk') <small class="text-danger">{{ $message }}</small>@enderror
                  </div>
                </div>
                <div class="form-group">
                  <label for="bilangan-isi-rumah" class="form-control-label">
                    Bilangan Isi Rumah <span class="text-danger"> *</span><br><i><p>(Semua individu yang tinggal dalam rumah yang sama.)</p></i>
                  </label>
                    <select id="bilangan-isi-rumah" name="bilangan_isi_rumah" class="form-control py-0" required>
                    <option value="" selected disabled>Pilih Bilangan Isi Rumah</option>
                    @for($i = 1; $i <= 20; $i++)
                      <option value="{{ $i }}">{{ $i }}</option>
                    @endfor
                  </select>
                  {{-- <input type="number" name="bilangan_isi_rumah" id="bilangan-isi-rumah" class="form-control @error('bilangan_isi_rumah') is-invalid @enderror" value="{{ old('bilangan_isi_rumah') }}"> --}}
                  @error('bilangan_isi_rumah') <small class="text-danger">{{ $message }}</small>@enderror
                </div>
                <div class="form-group">
                  <label for="jumlah-pendapatan" class="form-control-label">
                    Jumlah Pendapatan Isi Rumah<span class="text-danger"> *</span>
                    <button type="button" class="btn-tooltip fa fa-question-circle text-dark ml-2" data-toggle="tooltip" data-placement="right" title="Pendapatan keseluruhan isi rumah, sama ada secara tunai atau sebagainya dan boleh dirujuk sebagai pendapatan kasar."></button>  
                  </label>
                {{-- <select name="jumlah_pendapatan_isi_rumah" id="jumlah-pendapatan" class="form-control py-0">
                    <option value="" selected disabled>Pilih Pendapatan Isi Rumah</option>
                    <option value="RM0 - RM1,500">RM0 - RM1,500</option>
                    <option value="RM1,500 - RM2,500">RM1,500 - RM2,500</option>
                    <option value="RM2,500 - RM5,000">RM2,500 - RM5,000</option>
                    <option value="RM5,000 - RM10,000">RM5,000 - RM10,000</option>
                    <option value="RM10,000 - RM15,000">RM10,000 - RM15,000</option>
                    <option value="RM15,000 - RM20,000">RM15,000 - RM20,000</option>
                    <option value="RM20,000 ke atas">RM20,000 ke atas</option>
                  </select> --}}
                  <input type="number" name="jumlah_pendapatan_isi_rumah" id="jumlah-pendapatan" class="form-control @error('jumlah_pendapatan_isi_rumah') is-invalid @enderror" value="{{ old('jumlah_pendapatan_isi_rumah') }}" required>
                  @error('jumlah_pendapatan_isi_rumah') <small class="text-danger">{{ $message }}</small>@enderror
                </div>
              </div>
              <div class="col-lg-6">
                <div class="form-group">
                  <label for="pekerjaan" class="form-control-label">Kategori Pekerjaan<span class="text-danger"> *</span></label>
                  <select name="pekerjaan" id="pekerjaan" class="form-control py-0" required>
                    <option value="" disabled selected>Pilih Pekerjaan</option>
                    <option value="Bekerja Sendiri">Bekerja Sendiri</option>
                    <option value="Swasta">Swasta</option>
                    <option value="Kerajaan">Kerajaan</option>
                    <option value="Tidak bekerja">Tidak bekerja</option>
                    <option value="Pesara (Swasta)">Pesara Swasta</option>
                    <option value="Pesara (Kerajaan)">Pesara Kerajaan</option>
                    <option value="lain">Lain-Lain</option>
                  </select>
                  @error('pekerjaan') <small class="text-danger">{{ $message }}</small>@enderror
                  <input type="text" class="form-control d-none mt-3" name="pekerjaan_custom" id="pekerjaan_custom">
                </div>
                <div class="form-group">
                  <label for="pemilik_rumah" class="form-control-label">Pemilik Rumah<span class="text-danger"> *</span></label>
                  <select name="pemilik_rumah" id="pemilik_rumah" class="form-control py-0" required>
                    <option value="" disabled selected>Pilih Pemilik Rumah</option>
                    <option value="Rumah Sendiri">Rumah Sendiri</option>
                    <option value="Rumah Sewa">Rumah Sewa</option>
                    <option value="Rumah Kuarters">Rumah Kuarters</option>
                  </select>
                  @error('pemilik_rumah') <small class="text-danger">{{ $message }}</small>@enderror
                </div>
                <div class="form-group">
                  <label for="tujuan-sumbangan" class="form-control-label">Tujuan Sumbangan <span class="text-danger"> *</span></label>
                  <select name="tujuan_sumbangan" id="tujuan-sumbangan" class="form-control py-0" required>
                    <option value="" selected disabled>Pilih Tujuan Sumbangan</option>
                    @foreach($tujuanSumbangan as $t)
                      <option value="{{ $t->name }}">{{ $t->name }}</option>
                    @endforeach
                    <option value="lain">Lain-Lain</option>
                  </select>
                  @error('tujuan_sumbangan') <small class="text-danger">{{ $message }}</small>@enderror
                  <input id="tujuan_sumbangan_custom" class="form-control mt-3 d-none" name="tujuan_sumbangan_custom">
                </div>
                <div class="form-group">
                  <label for="jenis-sumbangan" class="form-control-label">Jenis Sumbangan <span class="text-danger"> *</span></label>
                  {{-- <select id="jenis-sumbangan" name="jenis_sumbangan[]" class="form-control select2-multiple" multiple="multiple" data-placeholder="Pilih Jenis Sumbangan">
                    @foreach($jenisSumbangan as $s)
                      <option value="{{ $s->name }}" class="text-upp">{{ Str::upper($s->name) }}</option>
                    @endforeach
                  </select> --}}
                  {{-- <select id="jenis-sumbangan" name="jenis_sumbangan" class="form-control py-1">
                    <option value="" selected disabled>Pilih Jenis Sumbangan</option>
                    @foreach($jenisSumbangan as $s)
                      <option value="{{ $s->name }}">{{ Str::upper($s->name) }}</option>
                    @endforeach
                  </select> --}}
                  @foreach($jenisSumbangan as $j)
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" value="{{ $j->name }}" id="jenisSumbangan{{ $j->id }}" name="jenis_sumbangan[]">
                      <label class="form-check-label" for="jenisSumbangan{{ $j->id }}">
                        {{ Str::upper($j->name) }}
                      </label>
                    </div>
                  @endforeach
                  <div class="row mt-2">
                    <div class="col-lg-6">
                      <input type="text" name="jenis_sumbangan[]" class="form-control form-control-sm">
                    </div>
                  </div>
                  @error('jenis_sumbangan') <small class="text-danger">{{ $message }}</small>@enderror
                </div>
                <div class="form-group">
                  <label for="bantuan-lain" class="form-control-label">Bantuan Lain Yang Sedang Diterima<span class="text-danger"> *</span></label>
                  {{-- <select id="bantuan-lain" name="bantuan_lain[]" class="form-control select2-multiple" multiple="multiple" data-placeholder="Pilih Bantuan Lain">
                    @foreach($bantuanLain as $b)
                      <option value="{{ $b->name }}" class="text-upp">{{ Str::upper($b->name) }}</option>
                    @endforeach
                  </select> --}}
                  {{-- <select id="bantuan-lain" name="bantuan_lain" class="form-control py-1">
                    <option value="" selected disabled>Pilih Bantuan Lain</option>
                    @foreach($bantuanLain as $b)
                      <option value="{{ $b->name }}">{{ Str::upper($b->name) }}</option>
                    @endforeach
                  </select> --}}
                  @foreach($bantuanLain as $b)
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" value="{{ $b->name }}" id="bantuanLain{{ $b->id }}" name="bantuan_lain[]">
                      <label class="form-check-label" for="bantuanLain{{ $b->id }}" class="text-uppercase">
                        {{ Str::upper($b->name) }}
                      </label>
                    </div>
                  @endforeach
                  <div class="row mt-2">
                    <div class="col-lg-6">
                      <input type="text" name="bantuan_lain[]" class="form-control form-control-sm">
                    </div>
                  </div>
                  @error('bantuan_lain') <small class="text-danger">{{ $message }}</small>@enderror
                </div>
                <div class="form-group">
                  <label for="keahlian-partai" class="form-control-label">
                    Keanggotaan Parti Penerima Sumbangan<span class="text-danger"> *</span>
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
                  <label for="nota" class="form-control-label">
                    Nota
                    <button type="button" class="btn-tooltip fa fa-question-circle text-dark ml-2" data-toggle="tooltip" data-placement="right" title="Untuk rujukan bagi tindakan Pusat Khidmat ADUN KEADILAN / CABANG."></button>
                  </label>
                  <input type="text" name="nota" id="nota" class="form-control @error('nota') is-invalid @enderror"  value="{{ old('nota') }}">
                </div>
                <div class="form-group">
                  <label for="tarikh-dan-masa" class="form-control-label">
                    Tarikh dan Masa<span class="text-danger"> *</span>
                    
                  </label>
                  <input type="datetime-local" name="tarikh_dan_masa" id="tarikh-dan-masa" class="form-control" required>
                  @error('tarikh_dan_masa') <small class="text-danger">{{ $message }}</small>@enderror
                </div>
                <div class="form-group">
                  <label for="gambar_ic" class="form-control-label">Muat Naik Salinan Kad Pengenalan<span class="text-danger"> *</span></label>
                  <input type="file" name="gambar_ic" id="gambar_ic" class="form-control-file" accept=".jpg, .jpeg, .png, .pdf" required>
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

@section('script')
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script>
    $(document).ready(function(){
      $('[data-toggle="tooltip"]').tooltip()
      $('.select2-multiple').select2();

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
        $('#bandar').prop('disabled', true);
        $('#bandar').empty().append(`<option value="" disabled selected>Sila Tunggu ...</option>`)
        
        $.ajax({
          url: '{{ route('get-bandar-specific') }}',
          method: 'POST',
          data: {
              _token: '{{ csrf_token() }}',
              id: $(this).val(),
          },
          success: function (response) {
              if(response.length > 0){
                $('#bandar').removeAttr('disabled');
                $('#bandar').empty().append(`<option value="" disabled selected>Pilih Bandar</option>`);
  
                $.each(response, function (index, data) {
                    $('#bandar').append(new Option(data.name, data.name))
                })
              }else{
                $('#bandar').empty().append(`<option value="" disabled selected>Bandar tak jumpa, tolong undi Negeri lain</option>`);
              }
          },
          error: err => {
              console.log(err);
          }
        })
      });

      $('#kadun').on('change', function(){
        $('#mpkk').prop('disabled', true)
        $('#mpkk').empty().append(`<option value="" disabled selected>Sila Tunggu ...</option>`)

        $.ajax({
          url: '{{ route('get-mpkk-specific') }}',
          method: 'POST',
          data: {
              _token: '{{ csrf_token() }}',
              id: $(this).val(),
          },
          success: function (response) {
              if(response.length > 0){
                $('#mpkk').prop('disabled', false)
                $('#mpkk').empty().append(`<option value="" disabled selected>Pilih MPKK</option>`);
  
                $.each(response, function (index, data) {
                    $('#mpkk').append(new Option(data.name, data.name))
                })
              }else{
                $('#mpkk').empty().append(`<option value="" disabled selected>MPKK tak jumpa, tolong undi KADUN lain</option>`)
              }
          },
          error: err => {
              console.log(err);
          }
        })
      })

      $('#tujuan-sumbangan').on('change', function () {
        const tujuan_sumbangan_custom = $('#tujuan_sumbangan_custom')

        if(this.value === 'lain'){
          tujuan_sumbangan_custom.removeClass('d-none');
          tujuan_sumbangan_custom.prop('required', true)
        }else{
          tujuan_sumbangan_custom.addClass('d-none');
          tujuan_sumbangan_custom.prop('required', false)
        }
      })

      $('#bangsa').on('change', function () {
        const bangsa_custom = $('#bangsa_custom')

        if(this.value === 'lain'){
          bangsa_custom.removeClass('d-none');
          bangsa_custom.prop('required', true)
        }else{
          bangsa_custom.addClass('d-none');
          bangsa_custom.prop('required', false)
        }
      })

      $('#pekerjaan').on('change', function() {
        const pekerjaan_custom = $('#pekerjaan_custom')

        if(this.value === 'lain'){
          pekerjaan_custom.removeClass('d-none')
          pekerjaan_custom.prop('required', true)
        }else{
          pekerjaan_custom.addClass('d-none')
          pekerjaan_custom.prop('required', false)
        }
      })
    });
  </script>
@endsection
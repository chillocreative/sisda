@extends('layouts.app')

@section('title', 'Data Pengundi')

@section('breadcrumb_title') Data Pengundi @endsection
@section('breadcrumbs')
  <li><a href="{{ route('report-data-pengundi') }}">Data Pengundi</a></li>
  <li>Edit</li>
@endsection

@section('content')
    <div class="row mt-3">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('data-pengundi.update', $data->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="section-form">
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="name" class="form-control-label">Nama<span class="text-danger">
                                                *</span></label>
                                        <input value={{ $data->name }} type="text" name="name" id="name"
                                            class="form-control text-uppercase @error('name') is-invalid @enderror" required>
                                        @error('name') <small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                    <div class="row">
                                        <div class="col-lg-6 form-group">
                                            <label for="no_kad" class="form-control-label">No Kad Pengenalan<span
                                                    class="text-danger"> *</span></label>
                                            <input value={{ $data->no_kad }} type="text" maxlength="12" name="no_kad" id="no_kad"
                                                class="form-control text-uppercase @error('no_kad') is-invalid @enderror no_kad"
                                                oninput="nextHomeSectionAdd(this)" onchange="nextSection()" required>
                                            @error('no_kad') <small
                                                class="text-danger">{{ $message }}</small>@enderror
                                        </div>
                                        <div class="col-lg-6 form-group form-umur" id="form-umur">
                                            <label for="umur" class="form-control-label">Umur</label>
                                            <div class="input-group">
                                                <input value={{ $data->umur }} name="umur" id="umur" class="form-control umur" readonly>
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">Tahun</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="phone" class="form-control-label">No Tel<span class="text-danger">
                                                *</span></label>
                                        <input value={{ $data->phone }} type="number" name="phone" pattern="/^-?\d+\.?\d*$/" onKeyPress="if(this.value.length==13) return false;" id="phone"
                                            class="form-control text-uppercase @error('phone') is-invalid @enderror" required>
                                        @error('phone') <small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                    <div class="form-group">
                                        <label for="bangsa" class="form-control-label">Bangsa<span class="text-danger">
                                                *</span></label>
                                        <select name="bangsa" id="bangsa" class="form-control py-1" required>
                                            <option value="" disabled>Pilih Bangsa</option>
                                            <option value="Melayu">Melayu</option>
                                            <option value="Cina" {{ $data->bangsa === 'Cina' ? 'selected' : ''}}>Cina</option>
                                            <option value="India" {{ $data->bangsa === 'India' ? 'selected' : ''}}>India</option>
                                            <option value="Bumiputra" {{ $data->bangsa === 'Bumiputra' ? 'selected' : ''}}>Bumiputra</option>
                                            <option value="Lain-Lain" {{ $data->bangsa === 'Lain-Lain' ? 'selected' : ''}}>Lain-Lain</option>
                                        </select>
                                        @error('bangsa') <small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                    @if($data->hubungan)
                                        <div class="form-group">
                                            <label for="hubungan" class="form-control-label">Hubungan<span class="text-danger">*</span></label>
                                            <select name="hubungan" id="hubungan" class="form-control py-0" required>
                                                <option value="" disabled>Pilih Hubungan</option>
                                                <option value="lain" selected>Lain-Lain</option>
                                                @foreach($hubungan as $h)
                                                    <option value="{{ $h->name }}" {{ $h->name === $data->hubungan ? 'selected' : '' }}>{{ $h->name }}</option>
                                                @endforeach
                                            </select>
                                            <input type="text" name="hubungan_custom" id="hubungan-custom" class="form-control d-none mt-3" value={{ $data->hubungan }}>
                                        </div>
                                    @endif
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="alamat" class="form-control-label">
                                            Alamat<span class="text-danger"> *</span>
                                            <button type="button" class="btn-tooltip fa fa-question-circle text-dark ml-2"
                                                data-toggle="tooltip" data-placement="right"
                                                title="Alamat tempat tinggal yang terkini."></button>
                                        </label>
                                        <textarea name="alamat" id="alamat" rows="2" class="form-control text-uppercase"
                                            required>{{ $data->alamat }}</textarea>
                                        @error('alamat') <small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                    <div class="row">
                                        <div class="col-lg-4 form-group">
                                            <label for="poskod" class="form-control-label">Poskod<span
                                                    class="text-danger"> *</span></label>
                                            <input type="number" name="poskod" id="poskod" pattern="/^-?\d+\.?\d*$/" onKeyPress="if(this.value.length==5) return false;"
                                                class="form-control @error('poskod') is-invalid @enderror"
                                                 value={{ $data->poskod }} required>
                                            @error('poskod') <small
                                                class="text-danger">{{ $message }}</small>@enderror
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label for="negeri" class="form-control-label">Negeri<span
                                                    class="text-danger"> *</span></label>
                                            <select name="negeri" id="negeri" class="form-control py-0 negeri"
                                                required>
                                                <option value="" disabled selected>Pilih Negeri</option>
                                                @foreach ($negeri as $n)
                                                    <option value="{{ $n->id }}" {{ $data->negeri === $n->name ? 'selected' : '' }}>{{ $n->name }}</option>
                                                @endforeach
                                            </select>
                                            @error('negeri') <small
                                                class="text-danger">{{ $message }}</small>@enderror
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label for="bandar" class="form-control-label">
                                                Bandar<span class="text-danger"> *</span>
                                            </label>
                                            <select name="bandar" id="bandar" class="form-control py-0 bandar"
                                               required>
                                                <option value="" disabled>Pilih Bandar</option>
                                                @foreach ($bandar as $b)
                                                  <option value="{{ $b->name }}" {{ $b->name === $data->bandar ? 'selected' : '' }}>{{ $b->name }}</option>
                                                @endforeach
                                            </select>
                                            @error('bandar') <small
                                                class="text-danger">{{ $message }}</small>@enderror
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 form-group">
                                            <label for="parlimen" class="form-control-label">Parlimen<span
                                                    class="text-danger"> *</span></label>
                                            <select name="parlimen" id="parlimen" class="form-control py-0 parlimen"
                                                required>
                                                <option value="" disabled>Pilih Parlimen</option>
                                                @foreach ($parlimen as $p)
                                                  <option value="{{ $p->id }}" {{ $p->name === $data->parlimen ? 'selected' : '' }}>{{ $p->name }}</option>
                                                @endforeach
                                            </select>
                                            @error('kadun') <small
                                                class="text-danger">{{ $message }}</small>@enderror
                                        </div>
                                        <div class="col-md-6 form-group">
                                            <label for="kadun" class="form-control-label">KADUN<span class="text-danger">
                                                    *</span></label>
                                            <select name="kadun" id="kadun" class="form-control py-0 kadun"
                                               required>
                                                <option value="" disabled>Pilih KADUN</option>
                                                @foreach ($kadun as $k)
                                                  <option class="text-uppercase" value="{{ strtoupper($k->name) }}" {{ strtoupper($k->name) === $data->kadun ? 'selected' : '' }}>{{ strtoupper($k->name) }}</option>
                                                @endforeach
                                            </select>
                                            @error('kadun') <small
                                                class="text-danger">{{ $message }}</small>@enderror
                                        </div>
                                    </div>
                                    <div class="old-validate {{ $data->umur > 17 ? '' : 'd-none' }}">
                                        <div class="form-group">
                                            <label for="keahlian-partai" class="form-control-label">
                                                Keahlian Parti<span class="text-danger"> *</span>
                                                <button type="button"
                                                    class="btn-tooltip fa fa-question-circle text-dark ml-2"
                                                    data-toggle="tooltip" data-placement="right"
                                                    title="Tooltip on keahlian partai"></button>
                                            </label>
                                            <select id="keahlian_partai" name="keahlian_partai"
                                                class="form-control keahlian-partai py-1" {{ $data->umur > 17 ? 'required' : '' }}>
                                                <option value="" selected disabled>Pilih Keahlian Parti</option>
                                                @foreach ($keahlianPartai as $k)
                                                    <option value="{{ $k->name }}" {{ $k->name === $data->keahlian_partai ? 'selected' : '' }}>{{ Str::upper($k->name) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('keahlian_partai') <small
                                                class="text-danger">{{ $message }}</small>@enderror
                                        </div>
                                        <div class="form-group">
                                            <label for="kecenderungan-politik" class="form-control-label">Kecenderungan
                                                Politik<span class="text-danger"> *</span></label>
                                            <select id="kecenderungan_politik" name="kecenderungan_politik"
                                                class="form-control kecenderungan-politik py-1" {{ $data->umur > 17 ? 'required' : '' }}>
                                                <option value="" selected disabled>Pilih Kecenderungan Politik</option>
                                                @foreach ($kecenderunganPolitik as $k)
                                                    <option value="{{ $k->name }}" {{ $k->name === $data->kecenderungan_politik ? 'selected' : '' }}>{{ Str::upper($k->name) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('kecenderungan_politik') <small
                                                class="text-danger">{{ $message }}</small>@enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <hr>
                        </div>
                        <div class="form-group">
                            <div class="row">
                                <div class="col-lg-4">
                                    <button type="submit" class="btn btn-primary btn-block btn-rounded">Update</button>
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
    <script type="text/javascript" defer>
        const hubunganSelect = $('#hubungan option:selected').val()
        
        if(hubunganSelect === 'lain'){
            const custom = $('#hubungan-custom')
            custom.removeClass('d-none')
        }
    </script>
    <script type="text/javascript">
        $(document).ready(function() {
            $('#hubungan').on('change', function() {
                const custom = $('#hubungan-custom')

                if(this.value === 'lain'){
                   custom.prop('required', true)
                   custom.removeClass('d-none')
                }else{
                   custom.prop('required', false)
                   custom.addClass('d-none')
                }
            })
        })
    </script>
    <script type="text/javascript">
        function nextHomeSectionAdd(qr) {
            qr.value = qr.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1');
            $(qr).on('keyup', function() {
                let formUmurElm = $('.form-umur')
                let umurElm = $('.umur')
                let oldValidateElm = $('.old-validate')
                let keahlianPartaiElm = $('.keahlian-partai')
                let kecenderunganPolitikElm = $('.kecenderungan-politik')
                formUmurElm = $(formUmurElm)
                umurElm = $(umurElm)
                oldValidateElm = $(oldValidateElm)
                keahlianPartaiElm = $(keahlianPartaiElm)
                kecenderunganPolitikElm = $(kecenderunganPolitikElm)

                formUmurElm.removeClass('d-none')

                let thisYear = new Date().getFullYear();
                let no_kad = $(this).val()
                let getYear = no_kad.substring(0, 2)
                year = parseInt(19 + getYear)
                if ((thisYear - year) > 100) {
                    year = parseInt(20 + getYear)
                }
                year = thisYear - year

                umurElm.val(year)

                if (year > 17) {
                    oldValidateElm.removeClass('d-none');
                    keahlianPartaiElm.prop('required', true);
                    kecenderunganPolitikElm.prop('required', true);
                } else {
                    oldValidateElm.addClass('d-none');
                    keahlianPartaiElm.prop('required', false);
                    kecenderunganPolitikElm.prop('required', false);
                }
            })

            console.log('sukses')
        }

        function negeriOnChange() {
            $('.negeri').on('change', function() {
                let bandarElm, parlimenElm, kadunElm;

                bandarElm = $('.bandar')
                parlimenElm = $('.parlimen')
                kadunElm = $('.kadun')

                bandarElm = $(bandarElm)
                parlimenElm = $(parlimenElm)
                kadunElm = $(kadunElm)
                
                bandarElm.prop('disabled', true)
                bandarElm.empty().append(
                    `<option value="" disabled selected>Sila Tunggu ...</option>`);

                parlimenElm.prop('disabled', true)
                parlimenElm.empty().append(
                    `<option value="" disabled selected>Sila Tunggu ...</option>`);

                kadunElm.prop('disabled', true)
                kadunElm.empty().append(
                    `<option value="" disabled selected>Pilih Parlimen dulu</option>`);

                $.ajax({
                    url: '{{ route('get-bandar-specific') }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        id: $(this).val(),
                    },
                    success: function(response) {
                        if(response.length > 0){
                            bandarElm.removeAttr('disabled')
                            bandarElm.empty().append(
                                `<option value="" disabled selected>Pilih Bandar</option>`);
                                
                            $.each(response, function(index, data) {
                                bandarElm.append(new Option(data.name, data.name))
                            })
                        }else{
                            bandarElm.empty().append(
                                `<option value="" disabled selected>Bandar tak jumpa, tolong undi negeri lain</option>`);
                        }
                    },
                    error: err => {
                        console.log(err);
                    }
                })

                $.ajax({
                    url: '{{ route('get-parlimen-specific') }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        id: $(this).val(),
                    },
                    success: function(response) {
                        if(response.length > 0){
                            parlimenElm.removeAttr('disabled')
                            parlimenElm.empty().append(
                                `<option value="" disabled selected>Pilih Parlimen</option>`);
                            $.each(response, function(index, data) {
                                parlimenElm.append(new Option(data.name, data.id))
                            })
                        }else{
                            parlimenElm.empty().append(
                                `<option value="" disabled selected>Parlimen tak jumpa, tolong undi negeri lain</option>`);
                        }
                    },
                    error: err => {
                        console.log(err);
                    }
                })
            });
        }

        function parlimenOnChange() {
            $('.parlimen').on('change', function() {
                let id = $(this).data('id')
                let kadunElm;

                kadunElm = $('.kadun')
                kadunElm = $(kadunElm)
                
                kadunElm.prop('disabled', true);
                kadunElm.empty().append(
                    `<option value="" disabled selected>Sila Tunggu ...</option>`);

                $.ajax({
                    url: '{{ route('get-kadun-specific') }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        id: $(this).val(),
                    },
                    success: function(response) {
                        if(response.length > 0){
                            kadunElm.removeAttr('disabled');
                            kadunElm.empty().append(
                                `<option value="" disabled selected>Pilih KADUN</option>`);
                            $.each(response, function(index, data) {
                                kadunElm.append(new Option(data.name.toUpperCase(), data.name.toUpperCase()))
                            })
                        }else{
                            kadunElm.empty().append(
                                `<option value="" disabled selected>Kadun tak jumpa, tolong undi parlimen lain</option>`);
                        }
                    },
                    error: err => {
                        console.log(err);
                    }
                })
            });
        }
    </script>
    <script>
        $(document).ready(function() {

            $('.no_kad').on('keyup', function() {
                let id = $(this).data('id')
                let formUmurElm = $('.form-umur')
                let umurElm = $('.umur')
                let oldValidateElm = $('.old-validate');
                let keahlianPartaiElm = $('.keahlian-partai')
                let kecenderunganPolitikElm = $('.kecenderungan-politik')
                formUmurElm = $(formUmurElm)
                umurElm = $(umurElm)
                oldValidateElm = $(oldValidateElm)
                keahlianPartaiElm = $(keahlianPartaiElm)
                kecenderunganPolitikElm = $(kecenderunganPolitikElm)

                formUmurElm.removeClass('d-none')

                let thisYear = new Date().getFullYear();
                let no_kad = $(this).val()
                let getYear = no_kad.substring(0, 2)
                year = parseInt(19 + getYear)
                if ((thisYear - year) > 100) {
                    year = parseInt(20 + getYear)
                }
                year = thisYear - year

                umurElm.val(year)

                if (year > 17) {
                    oldValidateElm.removeClass('d-none');
                    keahlianPartaiElm.prop('required', true);
                    kecenderunganPolitikElm.prop('required', true);
                } else {
                    oldValidateElm.addClass('d-none');
                    keahlianPartaiElm.prop('required', false);
                    kecenderunganPolitikElm.prop('required', false);
                }
            })

            negeriOnChange()
            parlimenOnChange()
        });
    </script>
@endsection
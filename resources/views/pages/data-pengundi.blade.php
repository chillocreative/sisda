@extends('layouts.app')

@section('title', 'Data Pengundi')

@section('breadcrumb_title', 'Data Pengundi')
@section('breadcrumbs')
    <li>Data Pengundi</li>
@endsection

@section('content')
    <div class="row mt-3">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('data-pengundi.store') }}" method="POST">
                        @csrf
                        <div class="section-form">
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="name" class="form-control-label">Nama<span class="text-danger">
                                                *</span></label>
                                        <input type="text" name="name[]" id="name"
                                            class="form-control text-uppercase @error('name') is-invalid @enderror"
                                            value="{{ old('name') }}" required>
                                        @error('name') <small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                    <div class="row">
                                        <div class="col-lg-6 form-group">
                                            <label for="no_kad" class="form-control-label">No Kad Pengenalan<span
                                                    class="text-danger"> *</span></label>
                                            <input type="number" name="no_kad[]" pattern="/^-?\d+\.?\d*$/" onKeyPress="if(this.value.length==12) return false;" id="no_kad"
                                                class="form-control text-uppercase @error('no_kad') is-invalid @enderror no_kad"
                                                data-id="0" oninput="nextHomeSectionAdd(this)" onchange="nextSection()" required>
                                            @error('no_kad') <small
                                                class="text-danger">{{ $message }}</small>@enderror
                                        </div>
                                        <div class="col-lg-6 form-group form-umur d-none" id="form-umur" data-id="0">
                                            <label for="umur" class="form-control-label">Umur</label>
                                            <div class="input-group">
                                                <input name="umur[]" id="umur" class="form-control umur" data-id="0"
                                                    value="" readonly>
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">Tahun</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="phone" class="form-control-label">No Tel<span class="text-danger">
                                                *</span></label>
                                        <input type="number" name="phone[]" pattern="/^-?\d+\.?\d*$/" onKeyPress="if(this.value.length==13) return false;" id="phone"
                                            class="form-control text-uppercase @error('phone') is-invalid @enderror"
                                            value="{{ old('phone') }}" required>
                                        @error('phone') <small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                    <div class="form-group">
                                        <label for="bangsa" class="form-control-label">Bangsa<span class="text-danger">
                                                *</span></label>
                                        <select name="bangsa[]" id="bangsa" class="form-control py-1" required>
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
                                        <label for="hubungan" class="form-control-label">Hubungan<span class="text-danger">*</span></label>
                                        <select name="hubungan[]" id="hubungan" class="form-control py-0" required>
                                            <option value="" selected disabled>Pilih Hubungan</option>
                                            @foreach($hubungan as $h)
                                                <option value="{{ $h->name }}">{{ $h->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="alamat" class="form-control-label">
                                            Alamat<span class="text-danger"> *</span>
                                            <button type="button" class="btn-tooltip fa fa-question-circle text-dark ml-2"
                                                data-toggle="tooltip" data-placement="right"
                                                title="Alamat tempat tinggal yang terkini."></button>
                                        </label>
                                        <textarea name="alamat[]" id="alamat" rows="2" class="form-control text-uppercase"
                                            required>{{ old('alamat') }}</textarea>
                                        @error('alamat') <small class="text-danger">{{ $message }}</small>@enderror
                                        <textarea name="alamat2[]" id="alamat2" rows="2"
                                            class="form-control text-uppercase mt-3">{{ old('alamat2') }}</textarea>
                                    </div>
                                    <div class="row">
                                        <div class="col-lg-4 form-group">
                                            <label for="poskod" class="form-control-label">Poskod<span
                                                    class="text-danger"> *</span></label>
                                            <input type="number" name="poskod[]" id="poskod" pattern="/^-?\d+\.?\d*$/" onKeyPress="if(this.value.length==5) return false;"
                                                class="form-control @error('poskod') is-invalid @enderror"
                                                value="{{ old('poskod') }}" required>
                                            @error('poskod') <small
                                                class="text-danger">{{ $message }}</small>@enderror
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label for="negeri" class="form-control-label">Negeri<span
                                                    class="text-danger"> *</span></label>
                                            <select name="negeri[]" id="negeri" class="form-control py-0 negeri" data-id="0"
                                                required>
                                                <option value="" disabled selected>Pilih Negeri</option>
                                                @foreach ($negeri as $n)
                                                    <option value="{{ $n->id }}">{{ $n->name }}</option>
                                                @endforeach
                                            </select>
                                            @error('negeri') <small
                                                class="text-danger">{{ $message }}</small>@enderror
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label for="bandar" class="form-control-label">
                                                Bandar<span class="text-danger"> *</span>
                                            </label>
                                            <select name="bandar[]" id="bandar" class="form-control py-0 bandar" data-id="0"
                                                disabled required>
                                                <option value="" disabled selected>Pilih Bandar</option>
                                            </select>
                                            @error('bandar') <small
                                                class="text-danger">{{ $message }}</small>@enderror
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 form-group">
                                            <label for="parlimen" class="form-control-label">Parlimen<span
                                                    class="text-danger"> *</span></label>
                                            <select name="parlimen[]" id="parlimen" class="form-control py-0 parlimen"
                                                data-id="0" disabled required>
                                                <option value="" disabled selected>Pilih Parlimen</option>
                                            </select>
                                            @error('kadun') <small
                                                class="text-danger">{{ $message }}</small>@enderror
                                        </div>
                                        <div class="col-md-6 form-group">
                                            <label for="kadun" class="form-control-label">Kadun<span class="text-danger">
                                                    *</span></label>
                                            <select name="kadun[]" id="kadun" class="form-control py-0 kadun" data-id="0"
                                                disabled required>
                                                <option value="" disabled selected>Pilih Kadun</option>
                                            </select>
                                            @error('kadun') <small
                                                class="text-danger">{{ $message }}</small>@enderror
                                        </div>
                                    </div>
                                    <div class="old-validate d-none" data-id="0">
                                        <div class="form-group">
                                            <label for="keahlian-partai" class="form-control-label">
                                                Keahlian Parti<span class="text-danger"> *</span>
                                                <button type="button"
                                                    class="btn-tooltip fa fa-question-circle text-dark ml-2"
                                                    data-toggle="tooltip" data-placement="right"
                                                    title="Tooltip on keahlian partai"></button>
                                            </label>
                                            <select id="keahlian_partai" name="keahlian_partai[]"
                                                class="form-control keahlian-partai py-1" data-id="0">
                                                <option value="-" selected readonly>Pilih Keahlian Parti</option>
                                                @foreach ($keahlianPartai as $k)
                                                    <option value="{{ $k->name }}">{{ Str::upper($k->name) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('keahlian_partai') <small
                                                class="text-danger">{{ $message }}</small>@enderror
                                        </div>
                                        <div class="form-group">
                                            <label for="kecenderungan-politik" class="form-control-label">Kecenderungan
                                                Politik<span class="text-danger"> *</span></label>
                                            <select id="kecenderungan_politik" name="kecenderungan_politik[]"
                                                class="form-control kecenderungan-politik py-1" data-id="0">
                                                <option value="-" selected readonly>Pilih Kecenderungan Politik</option>
                                                @foreach ($kecenderunganPolitik as $k)
                                                    <option value="{{ $k->name }}">{{ Str::upper($k->name) }}
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
                                    <a href="javascript:void(0);" id="addNewSection"
                                        class="btn btn-primary btn-block btn-rounded"><i class="fa fa-plus"></i> Tambah
                                        Isi Rumah</a>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-block btn-rounded">Submit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('style')
    <style>
        .form-control-label {
            /* font-size: 16px; */
            color: #222;
        }

        .form-group {
            margin-top: 30px !important;
        }

        .btn-tooltip {
            background-color: transparent;
            border: 0;
        }

    </style>
@endsection

@section('script')
    <script type="text/javascript">
        $(document).ready(function() {
            const maxField = 10;
            let addButton = $('#addNewSection');
            let wrapper = $('.section-form');
            let x = 1;
            $(addButton).click(function() {
                if (x < maxField) {
                    let fieldHTML = `<div class="form-group add"><div class="row">
                        <div class="col-lg-6"><div class="form-group"><label for="name" class="form-control-label">Nama<span class="text-danger">*</span></label><input type="text" name="name[]" id="name" class="form-control text-uppercase @error('name') is-invalid @enderror" value="{{ old('name') }}" required> @error('name') <small class="text-danger">{{ $message }}</small>@enderror</div><div class="row"><div class="col-lg-6 form-group"><label for="no_kad" class="form-control-label">No Kad Pengenalan<span class="text-danger">*</span></label><input type="number" name="no_kad[]" pattern="/^-?\d+\.?\d*$/" onKeyPress="if(this.value.length==12) return false;" id="no_kad" onchange="nextSection()" oninput="nextHomeSectionAdd(this)" class="form-control text-uppercase @error('no_kad') is-invalid @enderror no_kad" data-id="${x}"required> @error('no_kad') <small class="text-danger">{{ $message }}</small>@enderror</div><div class="col-lg-6 form-group form-umur d-none" id="form-umur" data-id="${x}"><label for="umur" class="form-control-label">Umur</label><div class="input-group"><input name="umur[]" id="umur" class="form-control umur" data-id="${x}" value="" readonly="readonly"><div class="input-group-prepend"><div class="input-group-text">Tahun</div></div></div></div></div><div class="form-group"><label for="phone" class="form-control-label">No Tel<span class="text-danger">*</span></label><input type="number" name="phone[]" pattern="/^-?\d+\.?\d*$/" onKeyPress="if(this.value.length==13) return false;" id="phone" class="form-control text-uppercase @error('phone') is-invalid @enderror" value="{{ old('phone') }}" required> @error('phone') <small class="text-danger">{{ $message }}</small>@enderror</div><div class="form-group"><label for="bangsa" class="form-control-label">Bangsa<span class="text-danger">*</span></label><select name="bangsa[]" id="bangsa" class="form-control py-1" required><option value="" disabled="disabled" selected="selected">Pilih Bangsa</option><option value="Melayu">Melayu</option><option value="Cina">Cina</option><option value="India">India</option><option value="Bumiputra">Bumiputra</option><option value="Lain-Lain">Lain-Lain</option></select>@error('bangsa') <small class="text-danger">{{ $message }}</small>@enderror</div><div class="form-group"><label for="hubungan" class="form-control-label">Hubungan<span class="text-danger">*</span></label><select name="hubungan[]" id="hubungan" class="form-control py-0" required><option value="" selected disabled>Pilih Hubungan</option>@foreach($hubungan as $h)<option value="{{ $h->name }}">{{ $h->name }}</option>@endforeach</select></div></div><div class="col-lg-6"><div class="form-group"><label for="alamat" class="form-control-label">Alamat<span class="text-danger">*</span> <button type="button" class="btn-tooltip fa fa-question-circle text-dark ml-2" data-toggle="tooltip" data-placement="right" title="Alamat tempat tinggal yang terkini."></button></label><textarea name="alamat[]" id="alamat" rows="2" class="form-control text-uppercase" required>{{ old('alamat') }}</textarea>@error('alamat') <small class="text-danger">{{ $message }}</small>@enderror<textarea name="alamat2[]" id="alamat2" rows="2" class="form-control text-uppercase mt-3">{{ old('alamat2') }}</textarea></div><div class="row"><div class="col-lg-4 form-group"><label for="poskod" class="form-control-label">Poskod<span class="text-danger">*</span></label><input type="text" name="poskod[]" pattern="/^-?\d+\.?\d*$/" onKeyPress="if(this.value.length==5) return false;" id="poskod" maxlength="5" class="form-control @error('poskod') is-invalid @enderror" value="{{ old('poskod') }}" required> @error('poskod') <small class="text-danger">{{ $message }}</small>@enderror</div><div class="col-md-4 form-group"><label for="negeri" class="form-control-label">Negeri<span class="text-danger">*</span></label><select name="negeri[]" id="negeri" class="form-control py-0 negeri" data-id="${x}" required><option value="" disabled="disabled" selected="selected">Pilih Negeri</option>@foreach ($negeri as $n)<option value="{{ $n->id }}">{{ $n->name }}</option>@endforeach</select>@error('negeri') <small class="text-danger">{{ $message }}</small>@enderror</div><div class="col-md-4 form-group"><label for="bandar" class="form-control-label">Bandar<span class="text-danger">*</span></label><select name="bandar[]" id="bandar" class="form-control py-0 bandar" data-id="${x}" disabled="disabled" required><option value="" disabled="disabled" selected="selected">Pilih Bandar</option></select>@error('bandar') <small class="text-danger">{{ $message }}</small>@enderror</div></div><div class="row"><div class="col-md-6 form-group"><label for="parlimen" class="form-control-label">Parlimen<span class="text-danger">*</span></label><select name="parlimen[]" id="parlimen" class="form-control py-0 parlimen" data-id="${x}" disabled="disabled" required><option value="" disabled="disabled" selected="selected">Pilih Parlimen</option></select>@error('kadun') <small class="text-danger">{{ $message }}</small>@enderror</div><div class="col-md-6 form-group"><label for="kadun" class="form-control-label">Kadun<span class="text-danger">*</span></label><select name="kadun[]" id="kadun" class="form-control py-0 kadun" data-id="${x}" disabled="disabled" required><option value="" disabled="disabled" selected="selected">Pilih Kadun</option></select>@error('kadun') <small class="text-danger">{{ $message }}</small>@enderror</div></div><div class="old-validate d-none" data-id="${x}"><div class="form-group"><label for="keahlian-partai" class="form-control-label">Keahlian Parti<span class="text-danger">*</span> <button type="button" class="btn-tooltip fa fa-question-circle text-dark ml-2" data-toggle="tooltip" data-placement="right" title="Tooltip on keahlian partai"></button></label><select id="keahlian_partai" name="keahlian_partai[]" class="form-control keahlian-partai py-1" data-id="${x}"><option value="-" selected="selected" readonly="readonly">Pilih Keahlian Parti</option>@foreach ($keahlianPartai as $k)<option value="{{ $k->name }}">{{ Str::upper($k->name) }}</option>@endforeach</select>@error('keahlian_partai') <small class="text-danger">{{ $message }}</small>@enderror</div><div class="form-group"><label for="kecenderungan-politik" class="form-control-label">Kecenderungan Politik<span class="text-danger">*</span></label><select id="kecenderungan_politik" name="kecenderungan_politik[]" class="form-control kecenderungan-politik py-1" data-id="${x}"><option value="-" selected="selected" readonly="readonly">Pilih Kecenderungan Politik</option>@foreach ($kecenderunganPolitik as $k)<option value="{{ $k->name }}">{{ Str::upper($k->name) }}</option>@endforeach</select>@error('kecenderungan_politik') <small class="text-danger">{{ $message }}</small>@enderror</div></div></div>
                        <div class="col-md-2"><a href="javascript:void(0);" class="remove_button btn btn-danger"><i class="fa fa-trash"></i></a></div>
                    </div></div>`;
                    $(wrapper).append(fieldHTML);

                    negeriOnChange()
                    parlimenOnChange()

                    x++;
                }
            });
            $(wrapper).on('click', '.remove_button', function(e) {
                if (confirm('Anda Yakin?')) {
                    e.preventDefault();
                    $(this).parent('').parent('').remove();
                    x--;
                } else {
                    return false;
                }
            });
        });

        function nextSection() {
            // console.log($(".no_kad"));
        }

        function nextHomeSectionAdd(qr) {
            $(qr).on('keyup', function() {
                let id = $(this).data('id')
                let formUmurElm = $('.form-umur')[id]
                let umurElm = $('.umur')[id]
                let oldValidateElm = $('.old-validate')[id];
                let keahlianPartaiElm = $('.keahlian-partai')[id]
                let kecenderunganPolitikElm = $('.kecenderungan-politik')[id]
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
                let id = $(this).data('id')
                let bandarElm, parlimenElm, kadunElm;

                bandarElm = $('.bandar')[id]
                parlimenElm = $('.parlimen')[id]
                kadunElm = $('.kadun')[id]

                bandarElm = $(bandarElm)
                parlimenElm = $(parlimenElm)
                kadunElm = $(kadunElm)

                bandarElm.removeAttr('disabled')
                parlimenElm.removeAttr('disabled')
                $.ajax({
                    url: '{{ route('get-bandar-specific') }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        id: $(this).val(),
                    },
                    success: function(response) {
                        bandarElm.empty().append(
                            `<option value="" disabled selected>Pilih Bandar</option>`);
                        parlimenElm.empty().append(
                            `<option value="" disabled selected>Pilih Parlimen</option>`);
                        kadunElm.empty().append(
                            `<option value="" disabled selected>Pilih Kadun</option>`);

                        $.each(response, function(index, data) {
                            bandarElm.append(new Option(data.name, data.name))
                        })
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
                        parlimenElm.empty().append(
                            `<option value="" disabled selected>Pilih Parlimen</option>`);

                        $.each(response, function(index, data) {
                            parlimenElm.append(new Option(data.name, data.id))
                        })
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

                kadunElm = $('.kadun')[id]
                kadunElm = $(kadunElm)

                kadunElm.removeAttr('disabled');
                $.ajax({
                    url: '{{ route('get-kadun-specific') }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        id: $(this).val(),
                    },
                    success: function(response) {
                        kadunElm.empty().append(
                            `<option value="" disabled selected>Pilih Kadun</option>`);

                        $.each(response, function(index, data) {
                            kadunElm.append(new Option(data.name, data.name))
                        })
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
                let formUmurElm = $('.form-umur')[id]
                let umurElm = $('.umur')[id]
                let oldValidateElm = $('.old-validate')[id];
                let keahlianPartaiElm = $('.keahlian-partai')[id]
                let kecenderunganPolitikElm = $('.kecenderungan-politik')[id]
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

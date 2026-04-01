@extends('layouts.app')

@section('title', 'Data Pengundi')

@section('breadcrumb_title', 'Data Pengundi')

@section('content')
    <div class="row mt-3">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    @if($draft->count() > 0)
                        @foreach($draft as $item)
                        <div class="d-flex align-items-center justify-content-between">
                            <h2>#{{ $loop->iteration }}</h2>
                            <form action="{{ route('data-pengundi.destroy', $item->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin memadamkan ?')"><i class="fa fa-trash"></i></button>
                            </form>
                        </div>
                            @php
                                $negeri_id = App\Models\Negeri::where('name', $item->negeri)->first()->id;
                                $parlimen_id = App\Models\Parlimen::where('name', $item->parlimen)->first()->id;

                                $bandar = App\Models\Bandar::where('negeri_id', $negeri_id)->get();
                                $parlimen = App\Models\Parlimen::where('negeri_id', $negeri_id)->get();
                                $kadun = App\Models\Kadun::where('parlimen_id', $parlimen_id)->get();
                            @endphp
                            <form action="{{ route('data-pengundi.update', $item->id) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label for="name" class="form-control-label">Nama<span class="text-danger"> *</span></label>
                                            <input type="text" name="name" id="name" class="form-control text-uppercase @error('name') is-invalid @enderror" value="{{ $item->name }}" required>
                                            @error('name') <small class="text-danger">{{ $message }}</small>@enderror
                                        </div>
                                        <div class="row">
                                            <div class="col-lg-6 form-group">
                                                <label for="no_kad" class="form-control-label">No Kad Pengenalan<span class="text-danger"> *</span></label>
                                                <input type="text" maxlength="12" name="no_kad" id="no_kad" class="form-control text-uppercase @error('no_kad') is-invalid @enderror no_kad" data-id="{{ $loop->iteration - 1 }}" oninput="nextHomeSectionAdd(this)" onchange="nextSection()" value={{ $item->no_kad }} required>
                                                @error('no_kad') <small class="text-danger">{{ $message }}</small>@enderror
                                            </div>
                                            <div class="col-lg-6 form-group form-umur" id="form-umur" data-id="{{ $loop->iteration - 1 }}">
                                                <label for="umur" class="form-control-label">Umur</label>
                                                <div class="input-group">
                                                    <input name="umur" id="umur" class="form-control umur" data-id="{{ $loop->iteration - 1 }}" value="{{ $item->umur }}" readonly>
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">Tahun</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="phone" class="form-control-label">No Tel<span class="text-danger"> *</span></label>
                                            <input type="number" name="phone" pattern="/^-?\d+\.?\d*$/" onKeyPress="if(this.value.length==13) return false;" id="phone" class="form-control text-uppercase @error('phone') is-invalid @enderror" value="{{ $item->phone }}" required>
                                            @error('phone') <small class="text-danger">{{ $message }}</small>@enderror
                                        </div>
                                        <div class="form-group">
                                            <label for="bangsa" class="form-control-label">Bangsa<span class="text-danger"> *</span></label>
                                            <select name="bangsa" id="bangsa" class="form-control py-1" required>
                                                <option value="" disabled>Pilih Bangsa</option>
                                                <option value="Melayu" {{ $item->bangsa === 'Melayu' ? 'selected' : '' }}>Melayu</option>
                                                <option value="Cina" {{ $item->bangsa === 'Cina' ? 'selected' : '' }}>Cina</option>
                                                <option value="India" {{ $item->bangsa === 'India' ? 'selected' : '' }}>India</option>
                                                <option value="Bumiputra" {{ $item->bangsa === 'Bumiputra' ? 'selected' : '' }}>Bumiputra</option>
                                                <option value="Lain-Lain" {{ $item->bangsa === 'Lain-Lain' ? 'selected' : '' }}>Lain-Lain</option>
                                            </select>
                                            @error('bangsa') <small class="text-danger">{{ $message }}</small>@enderror
                                        </div>
                                        @if($loop->iteration !== 1)
                                            <div class="form-group">
                                                <label for="hubungan" class="form-control-label">Hubungan<span class="text-danger">*</span></label>
                                                <select name="hubungan" id="hubungan" class="form-control py-0" data-id="{{ $loop->iteration - 1 }}" onchange="changeHubungan(this)" required>
                                                    <option value="" disabled>Pilih Hubungan</option>
                                                    <option value="lain" selected>Lain-Lain</option>
                                                    @foreach($hubungan as $h)
                                                        <option value="{{ $h->name }}" {{ $item->hubungan === $h->name ? 'selected' : '' }}>{{ $h->name }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="text" name="hubungan_custom" value="{{ $item->hubungan }}" class="hubungan-custom form-control d-none mt-3" data-id="{{ $loop->iteration - 1 }}">
                                            </div>
                                        @endif
                                    </div>
                                    <div class="col-lg-6">
                                        <h6 class="font-weight-bold mb-3">Maklumat Alamat</h6>
                                        <div class="form-group">
                                            <label for="alamat" class="form-control-label">
                                                Alamat<span class="text-danger"> *</span>
                                                <button type="button" class="btn-tooltip fa fa-question-circle text-dark ml-2" data-toggle="tooltip" data-placement="right" title="Alamat tempat tinggal yang terkini."></button>
                                            </label>
                                            <textarea name="alamat" id="alamat" rows="2" class="form-control text-uppercase" required>{{ $item->alamat }}</textarea>
                                            @error('alamat') <small class="text-danger">{{ $message }}</small>@enderror
                                        </div>
                                        <div class="row">
                                            <div class="col-lg-4 form-group">
                                                <label for="poskod" class="form-control-label">Poskod<span class="text-danger"> *</span></label>
                                                <input type="number" name="poskod" id="poskod" pattern="/^-?\d+\.?\d*$/" onKeyPress="if(this.value.length==5) return false;" class="form-control @error('poskod') is-invalid @enderror" value="{{ $item->poskod }}" required>
                                                @error('poskod') <small class="text-danger">{{ $message }}</small>@enderror
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label for="negeri" class="form-control-label">Negeri<span class="text-danger"> *</span></label>
                                                <select name="negeri" id="negeri" class="form-control py-0 negeri" data-id="{{ $loop->iteration - 1 }}" required>
                                                    <option value="" disabled>Pilih Negeri</option>
                                                    @foreach ($negeri as $n)
                                                        <option value="{{ $n->id }}" {{ $item->negeri === $n->name ? 'selected' : '' }}>{{ $n->name }}</option>
                                                    @endforeach
                                                </select>
                                                @error('negeri') <small class="text-danger">{{ $message }}</small>@enderror
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label for="bandar" class="form-control-label">Bandar<span class="text-danger"> *</span></label>
                                                <select name="bandar" id="bandar" class="form-control py-0 bandar" data-id="{{ $loop->iteration - 1 }}" required>
                                                    <option value="" disabled>Pilih Bandar</option>
                                                    @foreach($bandar as $b)
                                                        <option value="{{ $b->name }}" {{ $b->name === $item->bandar ? 'selected' : '' }}>{{ $b->name }}</option>
                                                    @endforeach
                                                </select>
                                                @error('bandar') <small class="text-danger">{{ $message }}</small>@enderror
                                            </div>
                                        </div>

                                        <h6 class="font-weight-bold mb-3 mt-2">Maklumat Kawasan Mengundi</h6>
                                        <div class="row">
                                            <div class="col-md-6 form-group">
                                                <label for="parlimen" class="form-control-label">Parlimen<span class="text-danger"> *</span></label>
                                                <select name="parlimen" id="parlimen" class="form-control py-0 parlimen" data-id="{{ $loop->iteration - 1 }}" required>
                                                    <option value="" disabled>Pilih Parlimen</option>
                                                    @foreach($parlimen as $p)
                                                        <option value="{{ $p->id }}" {{ $p->name === $item->parlimen ? 'selected' : '' }}>{{ $p->name }}</option>
                                                    @endforeach
                                                </select>
                                                @error('parlimen') <small class="text-danger">{{ $message }}</small>@enderror
                                            </div>
                                            <div class="col-md-6 form-group">
                                                <label for="kadun" class="form-control-label">KADUN<span class="text-danger"> *</span></label>
                                                <select name="kadun" id="kadun" class="form-control py-0 kadun" data-id="{{ $loop->iteration - 1 }}" required>
                                                    <option value="" disabled>Pilih KADUN</option>
                                                    @foreach($kadun as $k)
                                                        <option value="{{ $k->name }}" {{ $k->name === $item->kadun ? 'selected' : '' }}>{{ $k->name }}</option>
                                                    @endforeach
                                                </select>
                                                @error('kadun') <small class="text-danger">{{ $message }}</small>@enderror
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4 form-group">
                                                <label for="mpkk" class="form-control-label">MPKK</label>
                                                <select name="mpkk" id="mpkk" class="form-control py-0 mpkk" data-id="{{ $loop->iteration - 1 }}" disabled>
                                                    <option value="" disabled selected>Pilih MPKK</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label for="daerah_mengundi" class="form-control-label">Daerah Mengundi</label>
                                                <select name="daerah_mengundi" id="daerah_mengundi" class="form-control py-0" disabled>
                                                    <option value="" disabled selected>Pilih Daerah Mengundi</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label for="lokaliti" class="form-control-label">Lokaliti</label>
                                                <select name="lokaliti" id="lokaliti" class="form-control py-0" disabled>
                                                    <option value="" disabled selected>Pilih Lokaliti</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="old-validate" data-id="{{ $loop->iteration - 1 }}">
                                            @if($item->keahlian_partai)
                                                <div class="form-group">
                                                    <label for="keahlian-partai" class="form-control-label">
                                                        Keahlian Parti<span class="text-danger"> *</span>
                                                        <button type="button" class="btn-tooltip fa fa-question-circle text-dark ml-2" data-toggle="tooltip" data-placement="right" title="Tooltip on keahlian partai"></button>
                                                    </label>
                                                    <select id="keahlian_partai" name="keahlian_partai" class="form-control keahlian-partai py-1" data-id="{{ $loop->iteration - 1 }}" required>
                                                        <option value="" selected disabled>Pilih Keahlian Parti</option>
                                                        @foreach ($keahlianPartai as $k)
                                                            <option value="{{ $k->name }}" {{ $k->name === $item->keahlian_partai ? 'selected' : '' }}>{{ Str::upper($k->name) }}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('keahlian_partai') <small class="text-danger">{{ $message }}</small>@enderror
                                                </div>
                                            @endif
                                            @if($item->kecenderungan_politik)
                                                <div class="form-group">
                                                    <label for="kecenderungan-politik" class="form-control-label">Kecenderungan Politik<span class="text-danger"> *</span></label>
                                                    <select id="kecenderungan_politik" name="kecenderungan_politik" class="form-control kecenderungan-politik py-1" data-id="{{ $loop->iteration - 1 }}" required>
                                                        <option value="" selected disabled>Pilih Kecenderungan Politik</option>
                                                        @foreach ($kecenderunganPolitik as $k)
                                                            <option value="{{ $k->name }}" {{ $k->name === $item->kecenderungan_politik ? 'selected' : '' }}>{{ Str::upper($k->name) }}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('kecenderungan_politik') <small class="text-danger">{{ $message }}</small>@enderror
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="row">
                                        <div class="col-lg-3">
                                            <button class="btn btn-primary btn-sm btn-rounded ml-2">Simpan Perubahan</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                            <hr>
                        @endforeach
                    @endif
                    <form action="{{ route('data-pengundi.store') }}" method="POST" id="form-submit">
                        @csrf
                        <div class="section-form">
                            <h2>#{{ $draft->count() > 0 ? $draft->count() + 1 : '1' }}</h2>
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
                                            <input type="text" maxlength="12" name="no_kad" id="no_kad" class="form-control text-uppercase @error('no_kad') is-invalid @enderror no_kad" data-id="{{ $draft->count() > 0 ? $draft->count() : 0 }}" oninput="nextHomeSectionAdd(this)" onchange="nextSection()" required>
                                            @error('no_kad') <small class="text-danger">{{ $message }}</small>@enderror
                                        </div>
                                        <div class="col-lg-6 form-group form-umur d-none" id="form-umur" data-id="{{ $draft->count() > 0 ? $draft->count() : 0 }}">
                                            <label for="umur" class="form-control-label">Umur</label>
                                            <div class="input-group">
                                                <input name="umur" id="umur" class="form-control umur" data-id="{{ $draft->count() > 0 ? $draft->count() : 0 }}" value="" readonly>
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">Tahun</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="phone" class="form-control-label">No Tel<span class="text-danger"> *</span></label>
                                        <input type="number" name="phone" pattern="/^-?\d+\.?\d*$/" onKeyPress="if(this.value.length==13) return false;" id="phone" class="form-control text-uppercase @error('phone') is-invalid @enderror" value="{{ old('phone') }}" required>
                                        @error('phone') <small class="text-danger">{{ $message }}</small>@enderror
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
                                    @if($draft->count() > 0)
                                        <div class="form-group">
                                            <label for="hubungan" class="form-control-label">Hubungan<span class="text-danger">*</span></label>
                                            <select name="hubungan" id="hubungan" class="form-control py-0" data-id="{{ $draft->count() > 0 ? $draft->count() : 0 }}" onchange="changeHubungan(this)" required>
                                                <option value="" selected disabled>Pilih Hubungan</option>
                                                @foreach($hubungan as $h)
                                                    <option value="{{ $h->name }}">{{ $h->name }}</option>
                                                @endforeach
                                                <option value="lain">Lain-Lain</option>
                                            </select>
                                            <input type="text" name="hubungan_custom" value="" class="hubungan-custom form-control d-none mt-3" data-id="{{ $draft->count() > 0 ? $draft->count() : 0 }}">
                                        </div>
                                    @endif
                                </div>
                                <div class="col-lg-6">
                                    <h6 class="font-weight-bold mb-3">Maklumat Alamat</h6>
                                    <div class="form-group">
                                        <label for="alamat" class="form-control-label">
                                            Alamat<span class="text-danger"> *</span>
                                            <button type="button" class="btn-tooltip fa fa-question-circle text-dark ml-2" data-toggle="tooltip" data-placement="right" title="Alamat tempat tinggal yang terkini."></button>
                                        </label>
                                        <textarea name="alamat" id="alamat" rows="2" class="form-control text-uppercase" required>{{ old('alamat') }}</textarea>
                                        @error('alamat') <small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                    <div class="row">
                                        <div class="col-lg-4 form-group">
                                            <label for="poskod" class="form-control-label">Poskod<span class="text-danger"> *</span></label>
                                            <input type="number" name="poskod" id="poskod" pattern="/^-?\d+\.?\d*$/" onKeyPress="if(this.value.length==5) return false;" class="form-control @error('poskod') is-invalid @enderror" value="{{ old('poskod') }}" required>
                                            @error('poskod') <small class="text-danger">{{ $message }}</small>@enderror
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label for="negeri" class="form-control-label">Negeri<span class="text-danger"> *</span></label>
                                            <select name="negeri" id="negeri" class="form-control py-0 negeri" data-id="{{ $draft->count() > 0 ? $draft->count() : 0 }}" required>
                                                <option value="" disabled selected>Pilih Negeri</option>
                                                @foreach ($negeri as $n)
                                                    <option value="{{ $n->id }}">{{ $n->name }}</option>
                                                @endforeach
                                            </select>
                                            @error('negeri') <small class="text-danger">{{ $message }}</small>@enderror
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label for="bandar" class="form-control-label">Bandar<span class="text-danger"> *</span></label>
                                            <select name="bandar" id="bandar" class="form-control py-0 bandar" data-id="{{ $draft->count() > 0 ? $draft->count() : 0 }}" disabled required>
                                                <option value="" disabled selected>Pilih Negeri dulu</option>
                                            </select>
                                            @error('bandar') <small class="text-danger">{{ $message }}</small>@enderror
                                        </div>
                                    </div>

                                    <h6 class="font-weight-bold mb-3 mt-2">Maklumat Kawasan Mengundi</h6>
                                    <div class="row">
                                        <div class="col-md-6 form-group">
                                            <label for="parlimen" class="form-control-label">Parlimen<span class="text-danger"> *</span></label>
                                            <select name="parlimen" id="parlimen" class="form-control py-0 parlimen" data-id="{{ $draft->count() > 0 ? $draft->count() : 0 }}" disabled required>
                                                <option value="" disabled selected>Pilih Negeri dulu</option>
                                            </select>
                                            @error('parlimen') <small class="text-danger">{{ $message }}</small>@enderror
                                        </div>
                                        <div class="col-md-6 form-group">
                                            <label for="kadun" class="form-control-label">KADUN<span class="text-danger"> *</span></label>
                                            <select name="kadun" id="kadun" class="form-control py-0 kadun" data-id="{{ $draft->count() > 0 ? $draft->count() : 0 }}" disabled required>
                                                <option value="" disabled selected>Pilih Parlimen dulu</option>
                                            </select>
                                            @error('kadun') <small class="text-danger">{{ $message }}</small>@enderror
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4 form-group">
                                            <label for="mpkk" class="form-control-label">MPKK</label>
                                            <select name="mpkk" id="mpkk" class="form-control py-0 mpkk" data-id="{{ $draft->count() > 0 ? $draft->count() : 0 }}" disabled>
                                                <option value="" disabled selected>Pilih KADUN dulu</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label for="daerah_mengundi" class="form-control-label">Daerah Mengundi</label>
                                            <select name="daerah_mengundi" id="daerah_mengundi" class="form-control py-0 daerah-mengundi" data-id="{{ $draft->count() > 0 ? $draft->count() : 0 }}" disabled>
                                                <option value="" disabled selected>Pilih MPKK dulu</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label for="lokaliti" class="form-control-label">Lokaliti</label>
                                            <select name="lokaliti" id="lokaliti" class="form-control py-0 lokaliti" data-id="{{ $draft->count() > 0 ? $draft->count() : 0 }}" disabled>
                                                <option value="" disabled selected>Pilih Daerah Mengundi dulu</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="old-validate d-none" data-id="{{ $draft->count() > 0 ? $draft->count() : 0 }}">
                                        <div class="form-group">
                                            <label for="keahlian-partai" class="form-control-label">
                                                Keahlian Parti<span class="text-danger"> *</span>
                                                <button type="button" class="btn-tooltip fa fa-question-circle text-dark ml-2" data-toggle="tooltip" data-placement="right" title="Tooltip on keahlian partai"></button>
                                            </label>
                                            <select id="keahlian_partai" name="keahlian_partai" class="form-control keahlian-partai py-1" data-id="{{ $draft->count() > 0 ? $draft->count() : 0 }}">
                                                <option value="" selected disabled>Pilih Keahlian Parti</option>
                                                @foreach ($keahlianPartai as $k)
                                                    <option value="{{ $k->name }}">{{ Str::upper($k->name) }}</option>
                                                @endforeach
                                            </select>
                                            @error('keahlian_partai') <small class="text-danger">{{ $message }}</small>@enderror
                                        </div>
                                        <div class="form-group">
                                            <label for="kecenderungan-politik" class="form-control-label">Kecenderungan Politik<span class="text-danger"> *</span></label>
                                            <select id="kecenderungan_politik" name="kecenderungan_politik" class="form-control kecenderungan-politik py-1" data-id="{{ $draft->count() > 0 ? $draft->count() : 0 }}">
                                                <option value="" selected disabled>Pilih Kecenderungan Politik</option>
                                                @foreach ($kecenderunganPolitik as $k)
                                                    <option value="{{ $k->name }}">{{ Str::upper($k->name) }}</option>
                                                @endforeach
                                            </select>
                                            @error('kecenderungan_politik') <small class="text-danger">{{ $message }}</small>@enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <hr>
                        </div>
                        <div class="form-group">
                            <div class="row">
                                <div class="col-lg-4">
                                    <button class="btn btn-primary btn-block btn-rounded" name="submit_type" value="draft"><i class="fa fa-plus"></i> Tambah Isi Rumah</button>
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-primary btn-block btn-rounded" name="submit_type" value="submit" id="submit">Submit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script type="text/javascript">
        $(document).ready(function() {
            const maxField = 10;
            let addButton = $('#addNewSection');
            let wrapper = $('.section-form');
            let x = '{{ $draft->count() > 0 ? $draft->count() + 1 : 1 }}';

            $(addButton).click(function() {
                if (x < maxField) {
                    let fieldHTML = `<div class="form-group add"><h2>#${x + 1}</h2><div class="row">
                        <div class="col-lg-6"><div class="form-group"><label for="name" class="form-control-label">Nama<span class="text-danger">*</span></label><input type="text" name="name[]" id="name" class="form-control text-uppercase @error('name') is-invalid @enderror" value="{{ old('name') }}" required> @error('name') <small class="text-danger">{{ $message }}</small>@enderror</div><div class="row"><div class="col-lg-6 form-group"><label for="no_kad" class="form-control-label">No Kad Pengenalan<span class="text-danger">*</span></label><input type="text" onKeyPress="if(this.value.length==12) return false;" name="no_kad[]" id="no_kad" onchange="nextSection()" oninput="nextHomeSectionAdd(this)" class="form-control text-uppercase @error('no_kad') is-invalid @enderror no_kad" data-id="${x}"required> @error('no_kad') <small class="text-danger">{{ $message }}</small>@enderror</div><div class="col-lg-6 form-group form-umur d-none" id="form-umur" data-id="${x}"><label for="umur" class="form-control-label">Umur</label><div class="input-group"><input name="umur[]" id="umur" class="form-control umur" data-id="${x}" value="" readonly="readonly"><div class="input-group-prepend"><div class="input-group-text">Tahun</div></div></div></div></div><div class="form-group"><label for="phone" class="form-control-label">No Tel<span class="text-danger">*</span></label><input type="number" name="phone[]" pattern="/^-?\d+\.?\d*$/" onKeyPress="if(this.value.length==13) return false;" id="phone" class="form-control text-uppercase @error('phone') is-invalid @enderror" value="{{ old('phone') }}" required> @error('phone') <small class="text-danger">{{ $message }}</small>@enderror</div><div class="form-group"><label for="bangsa" class="form-control-label">Bangsa<span class="text-danger">*</span></label><select name="bangsa[]" id="bangsa" class="form-control py-1" required><option value="" disabled="disabled" selected="selected">Pilih Bangsa</option><option value="Melayu">Melayu</option><option value="Cina">Cina</option><option value="India">India</option><option value="Bumiputra">Bumiputra</option><option value="Lain-Lain">Lain-Lain</option></select>@error('bangsa') <small class="text-danger">{{ $message }}</small>@enderror</div><div class="form-group"><label for="hubungan" class="form-control-label">Hubungan<span class="text-danger">*</span></label><select name="hubungan[]" id="hubungan" class="form-control py-0" required><option value="" selected disabled>Pilih Hubungan</option>@foreach($hubungan as $h)<option value="{{ $h->name }}">{{ $h->name }}</option>@endforeach</select></div></div><div class="col-lg-6"><h6 class="font-weight-bold mb-3">Maklumat Alamat</h6><div class="form-group"><label for="alamat" class="form-control-label">Alamat<span class="text-danger">*</span> <button type="button" class="btn-tooltip fa fa-question-circle text-dark ml-2" data-toggle="tooltip" data-placement="right" title="Alamat tempat tinggal yang terkini."></button></label><textarea name="alamat[]" id="alamat" rows="2" class="form-control text-uppercase" required>{{ old('alamat') }}</textarea>@error('alamat') <small class="text-danger">{{ $message }}</small>@enderror</div><div class="row"><div class="col-lg-4 form-group"><label for="poskod" class="form-control-label">Poskod<span class="text-danger">*</span></label><input type="number" name="poskod[]" pattern="/^-?\d+\.?\d*$/" onKeyPress="if(this.value.length==5) return false;" id="poskod" maxlength="5" class="form-control @error('poskod') is-invalid @enderror" value="{{ old('poskod') }}" required> @error('poskod') <small class="text-danger">{{ $message }}</small>@enderror</div><div class="col-md-4 form-group"><label for="negeri" class="form-control-label">Negeri<span class="text-danger">*</span></label><select name="negeri[]" id="negeri" class="form-control py-0 negeri" data-id="${x}" required><option value="" disabled="disabled" selected="selected">Pilih Negeri</option>@foreach ($negeri as $n)<option value="{{ $n->id }}">{{ $n->name }}</option>@endforeach</select>@error('negeri') <small class="text-danger">{{ $message }}</small>@enderror</div><div class="col-md-4 form-group"><label for="bandar" class="form-control-label">Bandar<span class="text-danger">*</span></label><select name="bandar[]" id="bandar" class="form-control py-0 bandar" data-id="${x}" disabled="disabled" required><option value="" disabled="disabled" selected="selected">Pilih Negeri dulu</option></select>@error('bandar') <small class="text-danger">{{ $message }}</small>@enderror</div></div><h6 class="font-weight-bold mb-3 mt-2">Maklumat Kawasan Mengundi</h6><div class="row"><div class="col-md-6 form-group"><label for="parlimen" class="form-control-label">Parlimen<span class="text-danger">*</span></label><select name="parlimen[]" id="parlimen" class="form-control py-0 parlimen" data-id="${x}" disabled="disabled" required><option value="" disabled="disabled" selected="selected">Pilih Negeri dulu</option></select>@error('parlimen') <small class="text-danger">{{ $message }}</small>@enderror</div><div class="col-md-6 form-group"><label for="kadun" class="form-control-label">KADUN<span class="text-danger">*</span></label><select name="kadun[]" id="kadun" class="form-control py-0 kadun" data-id="${x}" disabled="disabled" required><option value="" disabled="disabled" selected="selected">Pilih Parlimen dulu</option></select>@error('kadun') <small class="text-danger">{{ $message }}</small>@enderror</div></div><div class="row"><div class="col-md-4 form-group"><label for="mpkk" class="form-control-label">MPKK</label><select name="mpkk[]" id="mpkk" class="form-control py-0 mpkk" data-id="${x}" disabled="disabled"><option value="" disabled="disabled" selected="selected">Pilih KADUN dulu</option></select></div><div class="col-md-4 form-group"><label for="daerah_mengundi" class="form-control-label">Daerah Mengundi</label><select name="daerah_mengundi[]" id="daerah_mengundi" class="form-control py-0 daerah-mengundi" data-id="${x}" disabled="disabled"><option value="" disabled="disabled" selected="selected">Pilih MPKK dulu</option></select></div><div class="col-md-4 form-group"><label for="lokaliti" class="form-control-label">Lokaliti</label><select name="lokaliti[]" id="lokaliti" class="form-control py-0 lokaliti" data-id="${x}" disabled="disabled"><option value="" disabled="disabled" selected="selected">Pilih Daerah Mengundi dulu</option></select></div></div><div class="old-validate d-none" data-id="${x}"><div class="form-group"><label for="keahlian-partai" class="form-control-label">Keahlian Parti<span class="text-danger">*</span> <button type="button" class="btn-tooltip fa fa-question-circle text-dark ml-2" data-toggle="tooltip" data-placement="right" title="Tooltip on keahlian partai"></button></label><select id="keahlian_partai" name="keahlian_partai[]" class="form-control keahlian-partai py-1" data-id="${x}"><option value="-" selected="selected" readonly="readonly">Pilih Keahlian Parti</option>@foreach ($keahlianPartai as $k)<option value="{{ $k->name }}">{{ Str::upper($k->name) }}</option>@endforeach</select>@error('keahlian_partai') <small class="text-danger">{{ $message }}</small>@enderror</div><div class="form-group"><label for="kecenderungan-politik" class="form-control-label">Kecenderungan Politik<span class="text-danger">*</span></label><select id="kecenderungan_politik" name="kecenderungan_politik[]" class="form-control kecenderungan-politik py-1" data-id="${x}"><option value="-" selected="selected" readonly="readonly">Pilih Kecenderungan Politik</option>@foreach ($kecenderunganPolitik as $k)<option value="{{ $k->name }}">{{ Str::upper($k->name) }}</option>@endforeach</select>@error('kecenderungan_politik') <small class="text-danger">{{ $message }}</small>@enderror</div></div></div>
                        <div class="col-md-2"><a href="javascript:void(0);" class="remove_button btn btn-danger"><i class="fa fa-trash"></i></a></div>
                    </div></div><hr>`;
                    $(wrapper).append(fieldHTML);

                    negeriOnChange()
                    parlimenOnChange()
                    kadunOnChange()

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
        }

        function changeHubungan(e) {
            $(e).on('click', function () {
                const id = $(this).data('id')
                let custom = $('.hubungan-custom')[id - 1]
                custom = $(custom)

                if(this.value === 'lain'){
                   custom.prop('required', true)
                   custom.removeClass('d-none')
                }else{
                   custom.prop('required', false)
                   custom.addClass('d-none')
                }
            })
        }

        function nextHomeSectionAdd(qr) {
            qr.value = qr.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1');
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
        }

        function negeriOnChange() {
            $('.negeri').on('change', function() {
                let id = $(this).data('id')
                let bandarElm, parlimenElm, kadunElm, mpkkElm;

                bandarElm = $('.bandar')[id]
                parlimenElm = $('.parlimen')[id]
                kadunElm = $('.kadun')[id]
                mpkkElm = $('.mpkk')[id]

                bandarElm = $(bandarElm)
                parlimenElm = $(parlimenElm)
                kadunElm = $(kadunElm)
                mpkkElm = $(mpkkElm)

                bandarElm.prop('disabled', true)
                bandarElm.empty().append(`<option value="" disabled selected>Sila Tunggu ...</option>`);

                parlimenElm.prop('disabled', true)
                parlimenElm.empty().append(`<option value="" disabled selected>Sila Tunggu ...</option>`);

                kadunElm.prop('disabled', true)
                kadunElm.empty().append(`<option value="" disabled selected>Pilih Parlimen dulu</option>`);

                mpkkElm.prop('disabled', true)
                mpkkElm.empty().append(`<option value="" disabled selected>Pilih KADUN dulu</option>`);

                $.ajax({
                    url: '{{ route('get-bandar-specific') }}',
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}', id: $(this).val() },
                    success: function(response) {
                        if(response.length > 0){
                            bandarElm.removeAttr('disabled')
                            bandarElm.empty().append(`<option value="" disabled selected>Pilih Bandar</option>`);
                            $.each(response, function(index, data) {
                                bandarElm.append(new Option(data.name, data.name))
                            })
                        }else{
                            bandarElm.empty().append(`<option value="" disabled selected>Bandar tak jumpa</option>`);
                        }
                    },
                    error: err => console.log(err)
                })

                $.ajax({
                    url: '{{ route('get-parlimen-specific') }}',
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}', id: $(this).val() },
                    success: function(response) {
                        if(response.length > 0){
                            parlimenElm.removeAttr('disabled')
                            parlimenElm.empty().append(`<option value="" disabled selected>Pilih Parlimen</option>`);
                            $.each(response, function(index, data) {
                                parlimenElm.append(new Option(data.name, data.id))
                            })
                        }else{
                            parlimenElm.empty().append(`<option value="" disabled selected>Parlimen tak jumpa</option>`);
                        }
                    },
                    error: err => console.log(err)
                })
            });
        }

        function parlimenOnChange() {
            $('.parlimen').on('change', function() {
                let id = $(this).data('id')
                let kadunElm = $('.kadun')[id]
                let mpkkElm = $('.mpkk')[id]
                kadunElm = $(kadunElm)
                mpkkElm = $(mpkkElm)

                kadunElm.prop('disabled', true);
                kadunElm.empty().append(`<option value="" disabled selected>Sila Tunggu ...</option>`);

                mpkkElm.prop('disabled', true);
                mpkkElm.empty().append(`<option value="" disabled selected>Pilih KADUN dulu</option>`);

                $.ajax({
                    url: '{{ route('get-kadun-specific') }}',
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}', id: $(this).val() },
                    success: function(response) {
                        if(response.length > 0){
                            kadunElm.removeAttr('disabled');
                            kadunElm.empty().append(`<option value="" disabled selected>Pilih KADUN</option>`);
                            $.each(response, function(index, data) {
                                kadunElm.append(new Option(data.name.toUpperCase(), data.name.toUpperCase()))
                            })
                        }else{
                            kadunElm.empty().append(`<option value="" disabled selected>KADUN tak jumpa</option>`);
                        }
                    },
                    error: err => console.log(err)
                })
            });
        }

        function kadunOnChange() {
            $('.kadun').on('change', function() {
                let id = $(this).data('id')
                let mpkkElm = $('.mpkk')[id]
                mpkkElm = $(mpkkElm)

                mpkkElm.prop('disabled', true);
                mpkkElm.empty().append(`<option value="" disabled selected>Sila Tunggu ...</option>`);

                $.ajax({
                    url: '{{ route('get-mpkk-specific') }}',
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}', id: $(this).val() },
                    success: function(response) {
                        if(response.length > 0){
                            mpkkElm.removeAttr('disabled');
                            mpkkElm.empty().append(`<option value="" disabled selected>Pilih MPKK</option>`);
                            $.each(response, function(index, data) {
                                mpkkElm.append(new Option(data.name, data.name))
                            })
                        }else{
                            mpkkElm.empty().append(`<option value="" disabled selected>MPKK tak jumpa</option>`);
                        }
                    },
                    error: err => console.log(err)
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
            kadunOnChange()
        });
    </script>
@endsection

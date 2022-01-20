@extends('layouts.app')

@section('title', 'Edit Kecenderungan Politik')

@section('breadcrumb_title', 'Edit Kecenderungan Politik')
@section('breadcrumbs')
  <li><a href="{{ route('kecenderungan-politik.index') }}">Kecenderungan Politik</a></li>
  <li>Edit</li>
@endsection

@section('content')
  <a href="{{ route('kecenderungan-politik.index') }}" class="btn btn-sm btn-secondary btn-rounded"><i class="fa fa-arrow-left"></i> <span class="ml-2">Kembali</span></a>
  <div class="row mt-3">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-body">
          <form action="{{ route('kecenderungan-politik.update', $kecenderunganPolitik->id) }}" method="post">
          @csrf
          @method('PUT')
            <div class="form-group mt-3">
              <label for="name" class="form-control-label">Nama</label>
              <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ $kecenderunganPolitik->name }}">
              @error('name') <small class="text-danger">{{ $message }}</small> @enderror
            </div>
            <div class="form-group mt-3">
              <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-rounded btn-block">Update</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
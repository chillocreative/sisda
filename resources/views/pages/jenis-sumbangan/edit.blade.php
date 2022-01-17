@extends('layouts.app')

@section('title', 'Edit Jenis Sumbangan')

@section('content')
  
  <div class="row">
    <div class="col-lg-12">
      <h1>Edit Jenis Sumbangan</h1>
    </div>
  </div>

  <div class="row mt-3">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-body">
          <form action="{{ route('jenis-sumbangan.update', $jenisSumbangan->id) }}" method="post">
          @csrf
          @method('PUT')
            <div class="form-group mt-3">
              <label for="name" class="form-control-label">Nama</label>
              <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ $jenisSumbangan->name }}">
              @error('name') <small class="text-danger">{{ $message }}</small> @enderror
            </div>
            <div class="form-group mt-3">
              <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary rounded-pill btn-block">Update</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
@extends('layouts.app')

@section('title', 'Edit Bandar')

@section('breadcrumb_title', 'Edit Bandar')
@section('breadcrumbs')
  <li><a href="{{ route('bandar.index') }}">Bandar</a></li>
  <li>Edit</li>
@endsection

@section('content')
  <a href="{{ route('bandar.index') }}" class="btn btn-sm btn-secondary rounded-pill"><i class="fa fa-arrow-left"></i> <span class="ml-2">Kembali</span></a>
  <div class="row mt-3">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-body">
          <form action="{{ route('bandar.update', $bandar->id) }}" method="post">
          @csrf
          @method('PUT')
            <div class="form-group mt-3">
              <label for="negeri" class="form-control-label">Negeri</label>
              <select name="negeri_id" id="negeri" class="form-control py-1">
                @foreach($negeri as $n)
                  <option value="{{ $n->id }}" {{ $n->id == $bandar->negeri_id ? 'selected' : '' }}>{{ $n->name }}</option>
                @endforeach
              </select>
              @error('negeri_id') <small class="text-danger">{{ $message }}</small> @enderror
            </div>
            <div class="form-group mt-3">
              <label for="name" class="form-control-label">Nama</label>
              <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ $bandar->name }}">
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
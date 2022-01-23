@extends('layouts.app')

@section('title', 'Edit Parlimen')

@section('breadcrumb_title', 'Edit Parlimen')
@section('breadcrumbs')
  <li><a href="{{ route('parlimen.index') }}">Parlimen</a></li>
  <li>Edit</li>
@endsection

@section('content')
  <a href="{{ route('parlimen.index') }}" class="btn btn-sm btn-secondary btn-rounded"><i class="fa fa-arrow-left"></i> <span class="ml-2">Kembali</span></a>
  <div class="row mt-3">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-body">
          <form action="{{ route('parlimen.update', $parlimen->id) }}" method="post">
          @csrf
          @method('PUT')
            <div class="form-group mt-3">
              <label for="negeri" class="form-control-label">Kadun</label>
              <select name="negeri_id" id="negeri" class="form-control py-1">
                @foreach($negeri as $n)
                  <option value="{{ $n->id }}" {{ $n->id == $parlimen->negeri_id ? 'selected' : '' }}>{{ $n->name }}</option>
                @endforeach
              </select>
              @error('negeri_id') <small class="text-danger">{{ $message }}</small> @enderror
            </div>
            <div class="form-group mt-3">
              <label for="code" class="form-control-label">Kod</label>
              <input type="text" name="code" id="code" class="form-control @error('code') is-invalid @enderror" value="{{ $parlimen->code }}">
              @error('code') <small class="text-danger">{{ $message }}</small> @enderror
            </div>
            <div class="form-group mt-3">
              <label for="name" class="form-control-label">Name</label>
              <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ $parlimen->name }}">
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
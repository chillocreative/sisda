@extends('layouts.app')

@section('title', 'My Profile')

@section('breadcrumb_title', 'My Profile')
@section('breadcrumbs')
  <li>My Profile</li>
@endsection

@section('content')
  <div class="row mt-3">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-body">
          <form action="{{ route('profile') }}" method="post">
          @csrf
              <div class="form-group mt-3">
                <label for="no_kad" class="form-control-label">No Kad Pengenalan</label>
                <input class="form-control" value="{{ $user->no_kad }}" disabled> 
              </div>
              <div class="form-group mt-3">
                <label for="name" class="form-control-label">Nama</label>
                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ $user->name }}">
                @error('name') <small class="text-danger">{{ $message }}</small>@enderror
              </div>
              <div class="form-group mt-3">
                <label for="phone" class="form-control-label">No Telefon</label>
                <input type="number" name="phone" id="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ $user->phone }}">
                @error('phone') <small class="text-danger">{{ $message }}</small>@enderror
              </div>
              <div class="form-group mt-3">
                <label for="email" class="form-control-label">Email Address</label>
                <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ $user->email }}">
                @error('email') <small class="text-danger">{{ $message }}</small>@enderror
              </div>
              <div class="form-group mt-3">
                <div class="d-grid gap-2">
                  <input type="submit" value="Update" class="btn btn-primary btn-rounded btn-block">
                </div>
              </div>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-4 mt-3 mt-lg-0">
      <div class="card">
        <div class="card-body">
          <form action="{{ route('profile.update-password') }}" method="post">
          @csrf
          @method('PUT')
              <div class="form-group mt-3">
                <label for="password_lama" class="form-control-label">Password Lama</label>
                <input type="password" name="password_lama" id="password_lama" class="form-control @error('password_lama') is-invalid @enderror">
                @error('password_lama') <small class="text-danger">{{ $message }}</small>@enderror
              </div>
              <div class="form-group mt-3">
                <label for="password" class="form-control-label">Password Baru</label>
                <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror">
                @error('password') <small class="text-danger">{{ $message }}</small>@enderror
              </div>
              <div class="form-group mt-3">
                <label for="password_confirmation" class="form-control-label">Password Confirmation</label>
                <input type="password" name="password_confirmation" id="password_confirmation" class="form-control @error('password_confirmation') is-invalid @enderror">
                @error('password_confirmation') <small class="text-danger">{{ $message }}</small>@enderror
              </div>
              <div class="form-group mt-3">
                <div class="d-grid gap-2">
                  <input type="submit" value="Update Password" class="btn btn-primary btn-rounded btn-block">
                </div>
              </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
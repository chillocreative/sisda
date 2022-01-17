@extends('layouts.app')

@section('title', 'Edit User')

@section('breadcrumb_title', 'Edit User')
@section('breadcrumbs')
  <li><a href="{{ route('user-user') }}">User</a></li>
  <li>Edit</li>
@endsection

@section('content')
  <div class="row mt-3">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-body">
          <form action="{{ route('user.update', $user->id) }}" method="post">
          @csrf
          @method('PUT')
              <div class="form-group mt-3">
                <label for="name" class="form-control-label">Nama</label>
                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ $user->name }}">
                @error('name') <small class="text-danger">{{ $message }}</small>@enderror
              </div>
              <div class="form-group mt-3">
                <label for="no_kad" class="form-control-label">No Kad Pengenalan</label>
                <input class="form-control" value="{{ $user->no_kad }}" disabled>
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
              @if(Auth::user()->role->name == 'superadmin')
                <div class="form-group mt-3">
                  <label for="role_id" class="form-control-label">Role</label>
                  <select name="role_id" id="role_id" class="form-control py-1">
                    @foreach($role as $r)
                      <option value="{{ $r->id }}" {{ $user->role->name == $r->name ? 'selected' : '' }}>{{ ucfirst($r->name) }}</option>
                    @endforeach
                  </select>
                </div>
              @endif
              <div class="form-group mt-3">
                <div class="d-grid gap-2">
                  <input type="submit" value="Update" class="btn btn-primary rounded-pill btn-block">
                </div>
              </div>
          </form>
        </div>
      </div>
    </div>
    @if($user->role->name == 'user')
    <div class="col-lg-4 mt-3 mt-lg-0">
      <div class="card">
        <div class="card-body">
          <form action="{{ route('user.reset-password', $user->id) }}" method="post">
          @csrf
          @method('PUT')
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
                  <input type="submit" value="Reset Password" class="btn btn-primary rounded-pill btn-block">
                </div>
              </div>
          </form>
        </div>
      </div>
    </div>
    @endif
  </div>
@endsection
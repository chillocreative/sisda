@extends('layouts.app')

@section('title') {{ $title }} @endsection

@section('style')
  <link rel="stylesheet/less" type="text/css" href="{{ asset('assets/less/switch.less') }}">
@endsection

@section('breadcrumb_title') {{ $title }} @endsection
@section('breadcrumbs')
  <li><a href="javascript:void(0)">User</a></li>
  <li>{{ $title }}</li>
@endsection

@section('content')
  <div class="row mt-3">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-body">
          <form action="{{ route('user-store') }}" method="post">
          @csrf
              <div class="form-group mt-3">
                <label for="name" class="form-control-label">Nama</label>
                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}">
                @error('name') <small class="text-danger">{{ $message }}</small>@enderror
              </div>
              <div class="form-group mt-3">
                <label for="no_kad" class="form-control-label">No Kad Pengenalan</label>
                <input type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1');" onKeyPress="if(this.value.length==12) return false;" name="no_kad" id="no_kad" class="form-control @error('no_kad') is-invalid @enderror" value="{{ old('no_kad') }}">
                @error('no_kad') <small class="text-danger">{{ $message }}</small>@enderror
              </div>
              <div class="form-group mt-3">
                <label for="phone" class="form-control-label">No Telefon</label>
                <input type="number" name="phone" id="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}">
                @error('phone') <small class="text-danger">{{ $message }}</small>@enderror
              </div>
              <div class="form-group mt-3">
                <label for="email" class="form-control-label">Email Address</label>
                <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
                @error('email') <small class="text-danger">{{ $message }}</small>@enderror
              </div>
              <div class="form-group mt-3">
                <label for="password" class="form-control-label">Password</label>
                <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror">
                @error('password') <small class="text-danger">{{ $message }}</small>@enderror
              </div>
              <div class="form-group mt-3">
                <label for="password_confirmation" class="form-control-label">Password Corfirmation</label>
                <input type="password" name="password_confirmation" id="password_confirmation" class="form-control @error('password_confirmation') is-invalid @enderror">
                @error('password_confirmation') <small class="text-danger">{{ $message }}</small>@enderror
              </div>
              <div class="form-group mt-3">
                <input type="text" name="role_id" value="{{ $role->id }}" class="d-none">
                <div class="d-grid gap-2">
                  <input type="submit" value="Create" class="btn btn-primary btn-rounded btn-block">
                </div>
              </div>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-8 mt-3 mt-lg-0">
      <div class="card">
          <div class="card-body">
              <div class="table-responsive">
                  <table class="table table-stripped table-hover" id="dataTable" class="text-center">
                      <thead class="text-capitalize">
                        <tr>
                            <th class="align-middle">Bil.</th>
                            <th class="align-middle">Nama</th>
                            <th class="align-middle">No Kad Pengenalan</th>
                            <th class="align-middle">No Telefon</th>
                            <th class="align-middle">Email Address</th>
                            <th class="align-middle text-center">Approved</th>
                            @if(Auth::user()->role->name == 'superadmin' && (Route::is('user-user') || Route::is('user-admin')))
                              <th class="align-middle">Action</th>
                            @elseif(Auth::user()->role->name == 'admin' && Route::is('user-user'))
                              <th class="align-middle">Action</th>
                            @endif
                        </tr>
                      </thead>
                      <tbody>
                        @foreach($users as $user)
                        <tr>
                            <td class="align-middle">{{ $loop->iteration }}</td>
                            <td class="align-middle">{{ $user->name }}</td>
                            <td class="align-middle">{{ $user->no_kad }}</td>
                            <td class="align-middle">{{ $user->phone }}</td>
                            <td class="align-middle">{{ $user->email }}</td>
                            <td class="align-middle text-center">
                              <button type="button" class="btn btn-switch approved {{ $user->approved ? 'focus active' : '' }}" data-id="{{ $user->id }}" data-toggle="button" aria-pressed="{{ $user->approved ? 'true' : 'false' }}" autocomplete="off">
                                <div class="handle"></div>
                              </button>
                            </td>
                            @if(Auth::user()->role->name == 'superadmin' && (Route::is('user-admin') || Route::is('user-user')))
                              <td class="align-middle">
                                <a href="{{ route('user.edit', $user->id) }}" class="btn btn-warning btn-sm fa fa-edit"></a>
                                <form action="{{ route('user-destroy', $user->id) }}" method="post" class="d-inline">
                                  @csrf
                                  @method('DELETE')
                                  <button type="submit" class="btn btn-danger btn-sm fa fa-trash" onclick="return confirm('Are you sure you want to delete this user ?')"></button>
                                </form>
                              </td>
                            @elseif(Auth::user()->role->name == 'admin' && Route::is('user-user'))
                              <td class="align-middle">
                                <a href="{{ route('user.edit', $user->id) }}" class="btn btn-warning btn-sm fa fa-edit"></a>
                                <form action="{{ route('user-destroy', $user->id) }}" method="post" class="d-inline">
                                @csrf
                                @method('DELETE')
                                  <button type="submit" class="btn btn-danger btn-sm fa fa-trash" onclick="return confirm('Are you sure you want to delete this user ?')"></button>
                                </form>
                              </td>
                            @endif
                        </tr>
                        @endforeach
                      </tbody>
                  </table>
              </div>
          </div>      
      </div>
    </div>
  </div>
@endsection

@section('script')
  <script src="https://cdn.jsdelivr.net/npm/less@4.1.1" ></script>
  <script>
    $(document).ready(function(){
      $('.approved').on('click', function(){
        url = '{{ route('approved', ':id') }}'
        url = url.replace(':id', this.dataset.id)
        $.ajax({
          url: url,
          type: 'PUT',
          data: {
            _token: '{{ csrf_token() }}',
          }
        })
      })
    })
  </script>
@endsection
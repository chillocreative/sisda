@extends('layouts.app')

@section('title') {{ $title }} @endsection

@section('content')
  <div class="row">
    <div class="col-lg-12">
      <h1>{{ $title }}</h1>
    </div>
  </div>

  <div class="row mt-3">
    <div class="col-12">
      <div class="card">
        <div class="card-body">
          <form action="{{ route('user-store') }}" method="post" class="text-center">
          @csrf
            <div class="row">
              <div class="col-md-4 form-group">
                <label for="name" class="form-control-label">Nama</label>
                <input type="text" name="name" id="name" class="form-control" required>
              </div>
              <div class="col-md-4 form-group">
                <label for="no_kad" class="form-control-label">No Kad Pengenalan</label>
                <input type="number" name="no_kad" id="no_kad" class="form-control" required>
              </div>
              <div class="col-md-4 form-group">
                <label for="phone" class="form-control-label">No Telefon</label>
                <input type="number" name="phone" id="phone" class="form-control" required>
              </div>
            </div>
            <div class="row">
              <div class="col-md-4 form-group">
                <label for="email" class="form-control-label">Email Address</label>
                <input type="email" name="email" id="email" class="form-control" required>
              </div>
              <div class="col-md-4 form-group">
                <label for="password" class="form-control-label">Password</label>
                <input type="password" name="password" id="password" class="form-control" required>
              </div>
              <div class="col-md-4 form-group">
                <label for="phone" class="form-control-label d-block text-light">`</label>
                <input type="submit" value="Create" class="btn btn-info btn-block">
              </div>
            </div>
            <input type="text" name="role_id" value="{{ $role->id }}" class="d-none">
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="row mt-3">
    <div class="col-12">
      <div class="card">
          <div class="card-body">
              <div class="table-responsive">
                  <table class="table table-stripped table-hover" id="dataTable" class="text-center">
                      <thead class="text-capitalize bg-info">
                        <tr>
                            <th>Bil.</th>
                            <th>Nama</th>
                            <th>No Kad Pengenalan</th>
                            <th>No Telefon</th>
                            <th>Email Address</th>
                            <th>Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        @foreach($role->users as $user)
                        <tr>
                            <td class="align-middle">{{ $loop->iteration }}</td>
                            <td class="align-middle">{{ $user->name }}</td>
                            <td class="align-middle">{{ $user->no_kad }}</td>
                            <td class="align-middle">{{ $user->phone }}</td>
                            <td class="align-middle">{{ $user->email }}</td>
                            <td class="align-middle">
                              <a href="" class="btn btn-info btn-sm">Update</a>
                              <form action="{{ route('user-destroy', $user->id) }}" method="post" class="d-inline">
                              @csrf
                              @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this user ?')">Delete</button>
                              </form>
                            </td>
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
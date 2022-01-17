@extends('layouts.app')

@section('title', 'Jenis Sumbangan')

@section('content')
  <div class="row">
    <div class="col-lg-12">
      <h1>Jenis Sumbangan</h1>
    </div>
  </div>

  <div class="row mt-3">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-body">
          <form action="{{ route('jenis-sumbangan.store') }}" method="post">
          @csrf
            <div class="form-group mt-3">
              <label for="name" class="form-control-label">Nama</label>
              <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}">
              @error('name') <small class="text-danger">{{ $message }}</small> @enderror
            </div>
            <div class="form-group mt-3">
              <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary rounded-pill btn-block">Create</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card">
        <div class="card-body">
          <div class="table-responsive">
            <table class="table" id="dataTable">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Name</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                @foreach($jenisSumbangan as $j)
                  <tr>
                    <td style="vertical-align: middle">{{ $loop->iteration }}</td>
                    <td style="vertical-align: middle">{{ $j->name }}</td>
                    <td style="vertical-align: middle">
                      <a href="{{ route('jenis-sumbangan.edit', $j->id) }}" class="btn btn-warning btn-sm fa fa-edit"></a>
                      <form action="{{ route('jenis-sumbangan.destroy', $j->id) }}" method="post" class="d-inline">
                      @csrf
                      @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm fa fa-trash" onclick="return confirm('')"></button>
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
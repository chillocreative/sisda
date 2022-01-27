@extends('layouts.app')

@section('title', 'Keahlian Parti')

@section('breadcrumb_title', 'Keahlian Parti')
@section('breadcrumbs')
  <li>Keahlian Parti</li>
@endsection

@section('content')
  <div class="row mt-3">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-body">
          <form action="{{ route('keahlian-parti.store') }}" method="post">
          @csrf
            <div class="form-group mt-3">
              <label for="name" class="form-control-label">Nama</label>
              <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}">
              @error('name') <small class="text-danger">{{ $message }}</small> @enderror
            </div>
            <div class="form-group mt-3">
              <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-rounded btn-block">Create</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-6 mt-3 mt-lg-0">
      <div class="card">
        <div class="card-body">
          <div class="table-responsive">
            <table class="table" id="dataTable">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Nama</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                @foreach($keahlianPartai as $k)
                  <tr>
                    <td style="vertical-align: middle">{{ $loop->iteration }}</td>
                    <td style="vertical-align: middle">{{ $k->name }}</td>
                    <td style="vertical-align: middle">
                      <a href="{{ route('keahlian-parti.edit', $k->id) }}" class="btn btn-warning btn-sm fa fa-edit"></a>
                      <form action="{{ route('keahlian-parti.destroy', $k->id) }}" method="post" class="d-inline">
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
@extends('layouts.app')

@section('title', 'Kadun')

@section('breadcrumb_title', 'Kadun')
@section('breadcrumbs')
  <li>Kadun</li>
@endsection

@section('content')
  <div class="row mt-3">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-body">
          <form action="{{ route('kadun.store') }}" method="post">
          @csrf
            <div class="form-group mt-3">
              <label for="parlimen_id" class="form-control-label">Parlimen</label>
              <select name="parlimen_id" id="parlimen_id" class="form-control py-0">
                <option value="" selected disabled>Pilih Parlimen</option>
                @foreach($parlimen as $p)
                  <option value="{{ $p->id }}">{{ $p->name }}</option>
                @endforeach
              </select>
              @error('parlimen_id') <small class="text-danger">{{ $message }}</small> @enderror
            </div>
            <div class="form-group mt-3">
              <label for="code" class="form-control-label">Kod</label>
              <input type="text" name="code" id="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code') }}">
              @error('code') <small class="text-danger">{{ $message }}</small> @enderror
            </div>
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
                  <th>Kod</th>
                  <th>Parlimen</th>
                  <th>Nama</th>
                  <th>Jumlah MPKK</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                @foreach($kadun as $k)
                  <tr>
                    <td style="vertical-align: middle">{{ $loop->iteration }}</td>
                    <td style="vertical-align: middle">{{ $k->code }}</td>
                    <td style="vertical-align: middle">{{ $k->parlimen->name }}</td>
                    <td style="vertical-align: middle">{{ $k->name }}</td>
                    <td style="vertical-align: middle">{{ $k->mpkk->count() }}</td>
                    <td>
                      <a href="{{ route('kadun.edit', $k->id) }}" class="btn btn-warning btn-sm fa fa-edit"></a>
                      <form action="{{ route('kadun.destroy', $k->id) }}" method="post" class="d-inline">
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
@extends('layouts.app')

@section('title', 'MPKK')

@section('content')
  <div class="row">
    <div class="col-lg-12">
      <h1>MPKK</h1>
    </div>
  </div>

  <div class="row mt-3">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-body">
          <form action="{{ route('mpkk.store') }}" method="post">
          @csrf
            <div class="form-group">
              <label for="kadun_id" class="form-control-label">Kadun</label>
              <select name="kadun_id" id="kadun_id" class="form-control py-0">
                <option value="" selected disabled>Pilih Kadun</option>
                @foreach($kadun as $k)
                  <option value="{{ $k->id }}">{{ $k->code }} - {{ $k->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group">
              <label for="name" class="form-control-label">Nama</label>
              <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}">
              @error('name') <small class="text-danger">{{ $message }}</small> @enderror
            </div>
            <div class="form-group">
              <button type="submit" class="btn btn-primary btn-block">Tambah</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card">
        <div class="card-body">
          <div class="table-responsive">
            <table class="table data-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Kadun</th>
                  <th>Name</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                @foreach($mpkk as $m)
                  <tr>
                    <td style="vertical-align: middle">{{ $loop->iteration }}</td>
                    <td style="vertical-align: middle">{{ $m->kadun->name }}</td>
                    <td style="vertical-align: middle">{{ $m->name }}</td>
                    <td style="vertical-align: middle">
                      <form action="{{ route('mpkk.destroy', $m->id) }}" method="post">
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

@section('style')
  <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/jquery.dataTables.min.css">
@endsection

@section('script')
  <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
  <script>
    $(document).ready(function(){
      $('.data-table').DataTable();  
    })
  </script>
@endsection
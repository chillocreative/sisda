@extends('layouts.app')

@section('title', 'Dashboard')

@section('style')
  <style>
    .card .card-body h1{
        font-size: 70px;
    }
  </style>
@endsection

@section('content')
  <div class="row">
    <div class="col-lg-12">
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <h2>Selamat Datang {{ ucfirst(Auth::user()->name) }}</h2>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span class="fa fa-times"></span>
        </button>
      </div>
    </div>
  </div>

  <div class="row mt-3">
    @foreach($roles as $role)
    <div class="col-lg-4 {{ $loop->iteration >= 2 ? 'mt-3 mt-lg-0' : '' }}">
      <div class="card text-center">
        <div class="card-body">
          <h1>{{ $role->users->count() }}</h1>
        </div>
        <div class="card-footer">
          <h5>Bilangan {{ ucfirst($role->name) }}</h5>
        </div>
      </div>
    </div>
    @endforeach
  </div>

  <div class="row mt-3">
    <div class="col-lg-4">
      <div class="card text-center">
        <div class="card-body">
          <h1>55</h1>
        </div>
        <div class="card-footer">
          <h5>Jumlah Parlimen</h5>
        </div>
      </div>
    </div>
    <div class="col-lg-4 mt-3 mt-lg-0">
      <div class="card text-center">
        <div class="card-body">
          <h1>55</h1>
        </div>
        <div class="card-footer">
          <h5>Jumlah DUN</h5>
        </div>
      </div>
    </div>
    <div class="col-lg-4 mt-3 mt-lg-0">
      <div class="card text-center">
        <div class="card-body">
          <h1>55</h1>
        </div>
        <div class="card-footer">
          <h5>Jumlah Culaan</h5>
        </div>
      </div>
    </div>
  </div>

  <div class="row mt-3">
    <div class="col-lg-4">
      <div class="card text-center">
        <div class="card-body">
          <h1>55</h1>
        </div>
        <div class="card-footer">
          <h5>Jumlah Bantuan</h5>
        </div>
      </div>
    </div>
    <div class="col-lg-4 mt-3 mt-lg-0">
      <div class="card text-center">
        <div class="card-body">
          <h1>55</h1>
        </div>
        <div class="card-footer">
          <h5>Jumlah Kes Dibuka</h5>
        </div>
      </div>
    </div>
    <div class="col-lg-4 mt-3 mt-lg-0">
      <div class="card text-center">
        <div class="card-body">
          <h1>55</h1>
        </div>
        <div class="card-footer">
          <h5>Jumlah Kes Ditutup</h5>
        </div>
      </div>
    </div>
  </div>
@endsection

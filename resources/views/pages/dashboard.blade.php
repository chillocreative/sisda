@extends('layouts.app')

@section('title', 'Dashboard')

@section('breadcrumb_title', 'Dashboard')
@section('breadcrumbs')
  <li>Dashboard</li>
@endsection

@section('style')
  <style>
    .card .card-body h1{
        font-size: 60px;
    }

    .card{
      border-radius: 10px;
    }
    .card.shadow{
      box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
    }

    .card button{
      border-radius: 10px;
      background-color: rgba(255, 255, 255, 0.5);
    }
  </style>
@endsection

@section('content')
  @if(Auth::user()->role->name == 'superadmin' || Auth::user()->role->name == 'admin')
  <div class="row mt-3">
    @foreach($roles as $role)
    <div class="col-lg-4 {{ $loop->iteration >= 2 ? 'mt-3 mt-lg-0' : '' }}">
      <div class="card shadow" style="background: linear-gradient(to right, #7f7fd5, #86a8e7, #91eae4);">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-start">
            <button class="btn p-4 mr-4">
              <i class="fa fa-user fa-4x"></i>
            </button>
            <div>
              <h1>{{ $role->users->count() }}</h1>
              <h5>Bilangan {{ ucfirst($role->name) }}</h5>
            </div>
          </div>
        </div>
      </div>
    </div>
    @endforeach
  </div>

  <div class="row mt-3 mt-lg-5">
    <div class="col-lg-4">
      <div class="card shadow">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-start">
            <button class="btn p-4 mr-4">
              <i class="fa fa-list-alt fa-4x"></i>
            </button>
            <div>
              <h1>55</h1>
              <h5>Jumlah Parlimen</h5>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-4 mt-3 mt-lg-0">
      <div class="card shadow">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-start">
            <button class="btn p-4 mr-4">
              <i class="fa fa-list-alt fa-4x"></i>
            </button>
            <div>
              <h1>55</h1>
              <h5>Jumlah DUN</h5>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-4 mt-3 mt-lg-0">
      <div class="card shadow" style="background: linear-gradient(to right, #22c1c3, #fdbb2d)">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-start">
            <button class="btn p-4 mr-4">
              <i class="fa fa-list-alt fa-4x"></i>
            </button>
            <div>
              <h1>{{ $culaan->count() }}</h1>
              <h5>Jumlah Culaan</h5>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row mt-3 mt-lg-5">
    <div class="col-lg-4">
      <div class="card shadow">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-start">
            <button class="btn p-4 mr-4">
              <i class="fa fa-list-alt fa-4x"></i>
            </button>
            <div>
              <h1>55</h1>
              <h5>Jumlah Bantuan</h5>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-4 mt-3 mt-lg-0">
      <div class="card shadow">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-start">
            <button class="btn p-4 mr-4">
              <i class="fa fa-list-alt fa-4x"></i>
            </button>
            <div>
              <h1>55</h1>
              <h5>Jumlah Kes Dibuka</h5>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-4 mt-3 mt-lg-0">
      <div class="card shadow">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-start">
            <button class="btn p-4 mr-4">
              <i class="fa fa-list-alt fa-4x"></i>
            </button>
            <div>
              <h1>55</h1>
              <h5>Jumlah Kes Ditutup</h5>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  @endif
@endsection
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

    .button-29 {
      align-items: center;
      appearance: none;
      background-color: #00adef;
      /* background-image: radial-gradient(100% 100% at 100% 0, #5adaff 0, #5468ff 100%); */
      border: 0;
      border-radius: 6px;
      box-shadow: rgba(45, 35, 66, .4) 0 2px 4px,rgba(45, 35, 66, .3) 0 7px 13px -3px,rgba(58, 65, 111, .5) 0 -3px 0 inset;
      box-sizing: border-box;
      color: #fff;
      cursor: pointer;
      display: inline-flex;
      height: 48px;
      justify-content: center;
      line-height: 1;
      list-style: none;
      overflow: hidden;
      padding-left: 16px;
      padding-right: 16px;
      position: relative;
      text-align: left;
      text-decoration: none;
      transition: box-shadow .15s,transform .15s;
      user-select: none;
      -webkit-user-select: none;
      touch-action: manipulation;
      white-space: nowrap;
      will-change: box-shadow,transform;
      font-size: 18px;
    }


    .button-29:hover {
      box-shadow: rgba(45, 35, 66, .4) 0 4px 8px, rgba(45, 35, 66, .3) 0 7px 13px -3px, #007bef 0 -3px 0 inset;
      transform: translateY(-2px);
      color: #fff;
    }

    .button-29:active {
      box-shadow: #007bef 0 3px 7px inset;
      transform: translateY(2px);
    }
  </style>
@endsection

@section('content')
  @if(Auth::user()->role->name == 'user')
  <div class="row justify-content-center mt-3">
    <div class="col-lg-4">
      <a href="{{ route('mula-culaan.index') }}" class="button-29" role="button" style="width: 100%; height: 100px;">MULA CULAAN</a>
    </div>
    <div class="col-lg-4">
      <a href="#" class="button-29" role="button" style="width: 100%; height: 100px;">MASUK DATA PENGUNDI</a>
    </div>
  </div>
  @endif
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
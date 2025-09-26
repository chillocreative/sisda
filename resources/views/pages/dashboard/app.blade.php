@extends('layouts.app')

@section('title', 'Dashboard')

@section('breadcrumb_title', 'Dashboard')

@section('style')
  <link rel="stylesheet" href="{{ asset('assets/css/button-dashboard.css') }}">
@endsection

@section('content')
  @yield('content')
@endsection

@section('script')
  @yield('script')
@endsection
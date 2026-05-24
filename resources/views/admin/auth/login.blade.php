@extends('adminlte::auth.login')

@section('title', 'Admin Login')
@section('auth_header', 'Maintainer Login')

@section('auth_footer')
    <p class="text-center text-muted mb-0">Only maintainer accounts can access this panel.</p>
@stop

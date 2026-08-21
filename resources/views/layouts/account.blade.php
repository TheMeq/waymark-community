@extends('layouts.public')

@section('site-header')
    <x-public.site-header :site="$site" />
@endsection

@section('site-footer')
    <x-public.site-footer :site="$site" />
@endsection

@extends('installer.layout')

@section('title', $step->label())

@section('content')
    <h2 class="h5">{{ $step->label() }}</h2>
    <p class="text-secondary small mb-0">{{ __('この段は準備中です。') }}</p>
@endsection

@extends('layouts.public')

@section('title', 'Services')

@section('content')
@include('partials.services-section', ['servicesByCategory' => $servicesByCategory, 'activeCategory' => $activeCategory])
@endsection

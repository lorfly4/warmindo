@extends('layouts.app')

@section('title', 'Tambah Bahan Baku - ' . config('app.name'))
@section('header', 'Tambah Bahan Baku')

@section('content')
    <div class="mb-5 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Tambah Bahan Baku</h1>
        <a href="{{ route('admin.ingredients.index') }}" class="btn btn-secondary">Kembali</a>
    </div>

    <form method="POST" action="{{ route('admin.ingredients.store') }}" class="card p-6">
        @csrf
        @include('admin.ingredients.form')
    </form>
@endsection
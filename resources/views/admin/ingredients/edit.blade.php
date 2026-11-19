@extends('layouts.app')

@section('title', 'Edit Bahan Baku - ' . config('app.name'))
@section('header', 'Edit Bahan Baku')

@section('content')
    <div class="mb-5 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Edit Bahan Baku</h1>
        <a href="{{ route('admin.ingredients.index') }}" class="btn btn-secondary">Kembali</a>
    </div>

    <form method="POST" action="{{ route('admin.ingredients.update', $ingredient) }}" class="card p-6">
        @csrf
        @method('PUT')
        @include('admin.ingredients.form')
    </form>
@endsection
@extends('layouts.admin')

@section('title', 'Cédula cerrada - SGLD')
@section('header', 'Cédula en papel')

@section('content')
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-3xl">
    <h2 class="text-xl font-bold text-white mb-3">{{ $partido->local?->nombre }} vs. {{ $partido->visitante?->nombre }}</h2>
    <x-alert type="info" message="La cédula de este partido ya está cerrada: ya no se puede modificar." />
    <a href="{{ route('cedulas.show', $partido) }}" class="mt-4 inline-flex items-center min-h-12 px-5 py-3 text-base font-semibold text-white bg-gray-700 hover:bg-gray-600 rounded-lg">Ver la cédula</a>
</div>
@endsection

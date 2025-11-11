@extends('layouts.guest')

@section('title', 'Masuk | Kasirku')

@section('brand')
  <x-ui.brand logo-src="/assets/logos/brand.svg" app-name="Kasirku" />
@endsection

@section('content')
  <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
    {{-- Panel visual --}}
    <div class="anim-fade-slide-up anim-delay-1">
      <div class="max-w-xl">
        <h2 class="text-3xl font-semibold tracking-tight text-gray-900">Cepat, Akurat, Andal.</h2>
        <p class="mt-2 text-gray-600">Transaksi lancar untuk bisnis ritel Anda.</p>

        <div class="mt-6 grid grid-cols-2 gap-4">
          <div class="card p-4">
            <p class="text-xs text-gray-500">Transaksi Hari Ini</p>
            <p class="mt-1 text-2xl font-semibold text-gray-900">152</p>
          </div>
          <div class="card p-4">
            <p class="text-xs text-gray-500">Pendapatan</p>
            <p class="mt-1 text-2xl font-semibold text-gray-900">Rp 12.540.000</p>
          </div>
        </div>
      </div>
    </div>

    {{-- Kartu login --}}
    <div class="lg:justify-self-end anim-fade-slide-up">
      <x-ui.card title="Masuk ke Kasirku" subtitle="Kelola transaksi harian dengan cepat dan aman."
        class="max-w-md w-full">
        <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4" novalidate>
          @csrf

          {{-- Banner error global --}}
          @if ($errors->any())
            <div class="mb-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
              {{ $errors->first('email') ?: 'Terjadi kesalahan. Periksa input Anda.' }}
            </div>
          @endif

          <x-ui.form-row for="email" :error="$errors->first('email')">
            <x-slot name="label">
              <x-ui.label for="email" text="Email atau Username" />
            </x-slot>
            <x-ui.input id="email" name="email" type="text" placeholder="nama@toko.com" autocomplete="username"
              value="{{ old('email') }}" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" required />
            @error('email')
              <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
          </x-ui.form-row>

          <x-ui.form-row for="password" :error="$errors->first('password')">
            <x-slot name="label">
              <x-ui.label for="password" text="Kata Sandi" />
            </x-slot>
            <x-ui.password-input id="password" name="password" placeholder="••••••••"
              aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" required />
            @error('password')
              <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
          </x-ui.form-row>

          <div class="flex items-center justify-between">
            <x-ui.checkbox id="remember" name="remember" :checked="old('remember', false)" label="Ingat saya" />
            <a href="#"
              class="text-sm font-medium text-indigo-600 hover:underline focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded">
              Lupa kata sandi?
            </a>
          </div>

          <x-ui.button type="submit" variant="primary" class="mt-2">Masuk</x-ui.button>
        </form>

      </x-ui.card>
    </div>
  </div>
@endsection
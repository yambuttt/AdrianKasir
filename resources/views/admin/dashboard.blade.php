@extends('layouts.admin')
@section('title', 'Dashboard Admin | Kasirku')

@section('content')
  <div class="mb-4 flex items-center justify-between">
    <h1 class="text-2xl font-semibold">Dashboard Admin</h1>
    <a href="{{ route('admin.users.create') }}"
      class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-white hover:opacity-95 transition">
      + User Baru
    </a>
  </div>

  {{-- Kartu KPI --}}
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 anim-card-in">
    <div class="card p-4">
      <p class="text-xs text-gray-500">Transaksi Hari Ini</p>
      <p class="mt-1 text-2xl font-semibold">{{ number_format($transaksiHariIni) }}</p>
    </div>
    <div class="card p-4">
      <p class="text-xs text-gray-500">Pendapatan (Nett) Hari Ini</p>
      <p class="mt-1 text-2xl font-semibold">Rp {{ number_format($pendapatanNett, 0, ',', '.') }}</p>
    </div>
    <div class="card p-4">
      <p class="text-xs text-gray-500">Item Terjual</p>
      <p class="mt-1 text-2xl font-semibold">{{ number_format($itemTerjual) }}</p>
    </div>
  </div>
  <div class="card p-6 anim-card-in anim-delay-1 gap-6 mt-6">
    <h2 class="text-lg font-semibold">Ringkasan Penjualan</h2>
    <p class="text-sm text-gray-600">Omzet nett per hari (7 hari terakhir).</p>
    <div class="mt-4">
      <canvas id="chartDashboardNett" height="120"></canvas>
    </div>
  </div>


  {{-- Aksi cepat bawah --}}
  <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <a href="{{ route('admin.users.index') }}" class="card p-5 hover:shadow-lg transition anim-card-in">
      <p class="text-sm text-gray-600">Kelola</p>
      <p class="mt-1 text-lg font-semibold">Daftar User</p>
    </a>
    <a href="{{ route('admin.users.create') }}" class="card p-5 hover:shadow-lg transition anim-card-in anim-delay-1">
      <p class="text-sm text-gray-600">Tambah</p>
      <p class="mt-1 text-lg font-semibold">User Baru</p>
    </a>
    <a href="{{ route('admin.reports.index') }}" class="card p-5 hover:shadow-lg transition anim-card-in anim-delay-2">
      <p class="text-sm text-gray-600">Lihat</p>
      <p class="mt-1 text-lg font-semibold">Ringkasan Penjualan</p>
    </a>
  </div>
@endsection

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    (function () {
      const ctx = document.getElementById('chartDashboardNett');
      new Chart(ctx, {
        type: 'line',
        data: {
          labels: {!! json_encode($chartLabels) !!},
          datasets: [{
            label: 'Omzet Nett',
            data: {!! json_encode($chartNett) !!},
            tension: 0.25,
            fill: false
          }]
        },
        options: {
          responsive: true,
          plugins: { legend: { display: false } },
          scales: {
            y: {
              ticks: {
                callback: (v) => 'Rp ' + new Intl.NumberFormat('id-ID').format(v)
              }
            }
          }
        }
      });
    })();
  </script>
@endpush
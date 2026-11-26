@extends('layouts.admin')

@section('title', 'Detail Pengguna')

@section('content')
    <x-page-header title="Detail Pengguna" subtitle="Lihat data akun pengguna lengkap.">
        <x-slot:actions>
            <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">
                <x-icon name="arrow-left" class="h-4 w-4" />
                Kembali
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="surface-card p-6">
            <div class="flex items-center gap-3 mb-4">
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-gradient-to-br from-accent to-violet text-base font-bold text-white shadow-[0_0_24px_-8px_rgba(6,182,212,0.9)]">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </span>
                <div>
                    <p class="text-xl font-bold text-ink">{{ $user->name }}</p>
                    <p class="text-base text-muted">{{ '@'.$user->username }}</p>
                </div>
            </div>

            <dl class="grid grid-cols-2 gap-4">
                <div>
                    <dt class="text-muted">Username</dt>
                    <dd class="font-medium">{{ $user->username }}</dd>
                </div>
                <div>
                    <dt class="text-muted">Role</dt>
                    <dd class="font-medium {{ $user->role === 'admin' ? 'text-danger' : 'text-success' }}">
                        {{ $user->role === 'admin' ? 'Admin' : 'Pengguna' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted">Status</dt>
                    <dd class="font-medium {{ $user->is_active ? 'text-success' : 'text-danger' }}">
                        {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted">Tanggal Dibuat</dt>
                    <dd class="font-medium">{{ $user->created_at->translatedFormat('d M Y H:i') }}</dd>
                </div>
            </dl>

            @if ($user->memberCard()->exists())
                <div class="mt-4 pt-4 border-t border-line/60">
                    <dt class="text-muted">Kartu Anggota</dt>
                    <dd>
                        <a href="{{ route('admin.cards.index') }}" class="text-primary hover:underline">
                            <x-icon name="card" class="h-4 w-4 me-2" /> Lihat Kartu
                        </a>
                        <span class="text-xs text-muted">(ID: {{ $user->memberCard->card_number }})</span>
                    </dd>
                </div>
            @else
                <div class="mt-4 pt-4 border-t border-line/60">
                    <dt class="text-muted">Kartu Anggota</dt>
                    <dd class="text-muted">Belum memiliki kartu anggota</dd>
                </div>
            @endif
        </div>
    </div>
@endsection
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Auditoría') — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="font-sans antialiased bg-gray-50">

    <nav class="bg-white border-b border-gray-200 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-1">
                    <span class="font-extrabold text-gray-900 text-sm tracking-tight mr-3">Módulo de Auditoría</span>
                    @foreach([
                        ['auditoria.riesgos', 'Riesgos'],
                        ['auditoria.controles', 'Controles'],
                        ['auditoria.objetivos', 'Objetivos'],
                        ['auditoria.planes', 'Planes de Acción'],
                        ['auditoria.tareas', 'Tareas'],
                    ] as [$prefix, $label])
                        <a href="{{ route($prefix . '.index') }}"
                           class="px-3 py-2 rounded-lg text-sm font-semibold transition-colors
                               {{ request()->routeIs($prefix . '.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                    @auth
                        <a href="{{ route('auditoria.vencimientos.index') }}"
                           class="px-3 py-2 rounded-lg text-sm font-semibold transition-colors
                               {{ request()->routeIs('auditoria.vencimientos.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700' }}">
                            Vencimientos
                        </a>
                        @if(auth()->user()->esGerente() || auth()->user()->esComite())
                            <a href="{{ route('auditoria.pendientes.index') }}"
                               class="px-3 py-2 rounded-lg text-sm font-semibold transition-colors
                                   {{ request()->routeIs('auditoria.pendientes.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700' }}">
                                Pendientes
                            </a>
                        @endif
                    @endauth
                </div>
                @auth
                <form method="POST" action="{{ route('logout') }}" class="flex items-center gap-3">
                    @csrf
                    <span class="text-xs text-gray-400">{{ Auth::user()->name }}</span>
                    <button type="submit" class="text-xs text-gray-400 hover:text-gray-600 transition-colors">Salir</button>
                </form>
                @endauth
            </div>
        </div>
    </nav>

    @if (session('ok'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-5">
            <div class="rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm font-medium">
                {{ session('ok') }}
            </div>
        </div>
    @endif
    @if (session('error'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-5">
            <div class="rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm font-medium">
                {{ session('error') }}
            </div>
        </div>
    @endif

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @yield('content')
    </main>

    @livewire('auditoria.modal.detalle-riesgo')
    @livewire('auditoria.modal.detalle-control')
    @livewire('auditoria.modal.detalle-objetivo')
    @livewire('auditoria.modal.detalle-plan')
    @livewire('auditoria.modal.detalle-tarea')

    @stack('scripts')
</body>
</html>

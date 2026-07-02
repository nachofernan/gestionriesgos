@props(['titulo', 'subtitulo' => null])
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/50">
        <h2 class="text-sm font-bold text-gray-700">
            {{ $titulo }}
            @if($subtitulo)
                <span class="ml-2 text-[10px] font-normal text-gray-400">{{ $subtitulo }}</span>
            @endif
        </h2>
    </div>
    <div class="divide-y divide-gray-100">
        {{ $slot }}
    </div>
</div>

{{-- Referencia de cómo se valora un control y cómo lo regula la evidencia (D-051). --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 space-y-3 sticky top-6">
    <h2 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Referencia de Valoración</h2>

    <p class="text-xs text-gray-500 leading-relaxed">
        El valor del control sale de su cuestionario (de 1 a 10) y es lo que mitiga en cada riesgo.
        Si el control es una expresión de deseo, mitiga 0.
    </p>

    <table class="w-full text-xs border-collapse">
        <thead>
            <tr class="border-b border-gray-200">
                <th class="text-left font-bold text-gray-500 uppercase tracking-wider py-2 pr-2">Clasificación</th>
                <th class="text-right font-bold text-gray-500 uppercase tracking-wider py-2">Puntaje</th>
            </tr>
        </thead>
        <tbody>
            <tr class="border-b border-gray-100">
                <td class="py-2 pr-2 font-semibold text-red-700">Deficiente</td>
                <td class="py-2 text-right font-bold text-gray-800">menos de 4</td>
            </tr>
            <tr class="border-b border-gray-100">
                <td class="py-2 pr-2 font-semibold text-amber-700">Moderado</td>
                <td class="py-2 text-right font-bold text-gray-800">4–6</td>
            </tr>
            <tr>
                <td class="py-2 pr-2 font-semibold text-green-700">Suficiente</td>
                <td class="py-2 text-right font-bold text-gray-800">7 o más</td>
            </tr>
        </tbody>
    </table>

    <table class="w-full text-xs border-collapse">
        <thead>
            <tr class="border-b border-gray-200">
                <th class="text-left font-bold text-gray-500 uppercase tracking-wider py-2 pr-2">Evidencia de cumplimiento</th>
                <th class="text-right font-bold text-gray-500 uppercase tracking-wider py-2">Aplica</th>
            </tr>
        </thead>
        <tbody>
            @foreach(['Menos de 60%' => '0%', '60–69%' => '20%', '70–79%' => '40%', '80–90%' => '60%', '91–98%' => '80%', '99–100%' => '100%'] as $tramo => $aplica)
                <tr class="{{ $loop->last ? '' : 'border-b border-gray-100' }}">
                    <td class="py-1.5 pr-2 text-gray-700">{{ $tramo }}</td>
                    <td class="py-1.5 text-right font-bold text-gray-800">{{ $aplica }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="text-[11px] text-gray-400">La evidencia se mide en cada riesgo que el control mitiga. El porcentaje se aplica sobre el valor del control, hacia abajo.</p>
</div>

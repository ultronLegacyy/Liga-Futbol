{{-- Una temporada cerrada solo se consulta (RF-01.4) --}}
@if ($temporada->estado === 'cerrada')
    <p role="note" class="mb-4 px-4 py-3 rounded-lg bg-yellow-500/10 border border-yellow-500/30 text-yellow-300 text-sm">
        Temporada cerrada: sus categorías y grupos solo se consultan.
    </p>
@endif

{{-- Variante para simplePaginate(): solo Anterior / Siguiente. --}}
@if ($paginator->hasPages())
    <nav class="pager" role="navigation" aria-label="Paginación">
        <p class="pager-info muted">Página {{ $paginator->currentPage() }}</p>
        <div class="pager-links">
            @if ($paginator->onFirstPage())
                <span class="off" aria-disabled="true">‹ Anterior</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Anterior</a>
            @endif

@if ($paginator->hasMorePages())
<a href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente ›</a>
@else
<span class="off" aria-disabled="true">Siguiente ›</span>
@endif
        </div>
    </nav>
@endif

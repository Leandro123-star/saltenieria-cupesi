{{-- Paginador propio del sistema: sin SVG ni clases de Tailwind (el layout no carga Tailwind). --}}
@if ($paginator->hasPages())
    <nav class="pager" role="navigation" aria-label="Paginación">
        <p class="pager-info muted">
            Mostrando {{ $paginator->firstItem() }} a {{ $paginator->lastItem() }} de {{ $paginator->total() }} registros
            · Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}
        </p>
        <div class="pager-links">
            @if ($paginator->onFirstPage())
                <span class="off" aria-disabled="true">‹ Anterior</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Anterior</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="off gap" aria-disabled="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="on" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" aria-label="Ir a la página {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente ›</a>
            @else
                <span class="off" aria-disabled="true">Siguiente ›</span>
            @endif
        </div>
    </nav>
@endif

@if ($paginator->hasPages())
    <nav class="pagination" role="navigation">
        <div class="pagination-controls">
            @if ($paginator->onFirstPage())
                <span class="page-btn disabled" aria-disabled="true">‹ Prev</span>
            @else
                <a class="page-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Prev</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="page-ellipsis">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="page-btn active" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="page-btn" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="page-btn" href="{{ $paginator->nextPageUrl() }}" rel="next">Next ›</a>
            @else
                <span class="page-btn disabled" aria-disabled="true">Next ›</span>
            @endif
        </div>
    </nav>
@endif

@extends('layouts.storefront')

@section('content')
<section class="section" id="products">
    <div class="container">
        <div class="shop-head">
            <div>
                <span class="sku">Catalog</span>
                <h1>Shop Hardware</h1>
                <p class="muted">Genuine computer parts and peripherals — orders are confirmed with delivery details by our team.</p>
            </div>
            <form method="get" class="search-form">
                <input class="search" name="s" value="{{ $search }}" placeholder="Search by SKU, name, or category">
                <button class="primary" type="submit">Search</button>
            </form>
        </div>

        <div class="filters">
            <a class="filter{{ $search === '' ? ' active' : '' }}" href="{{ route('home') }}">All</a>
            @foreach ($categories as $category)
                <a class="filter" href="{{ route('collections.show', $category) }}">{{ $category->name }}</a>
            @endforeach
        </div>

        <div class="grid">
            @foreach ($products as $product)
                @include('storefront.partials.product-card', ['product' => $product])
            @endforeach
        </div>

        {{ $products->links('vendor.pagination.custom') }}
    </div>
</section>
@endsection

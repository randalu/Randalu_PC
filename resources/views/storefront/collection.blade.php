@extends('layouts.storefront')

@section('title', $category->name.' | Randalu PC')
@section('description', $category->description)

@section('content')
<section class="section">
    <div class="container">
        <div class="shop-head">
            <div>
                <span class="sku">Collection</span>
                <h1>{{ $category->name }}</h1>
                <p class="muted">{{ $category->description }}</p>
            </div>
        </div>

        <div class="filters">
            <a class="filter" href="{{ route('home') }}">All</a>
            @foreach ($categories as $item)
                <a class="filter{{ $item->id === $category->id ? ' active' : '' }}" href="{{ route('collections.show', $item) }}">{{ $item->name }}</a>
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

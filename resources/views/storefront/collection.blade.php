@extends('layouts.storefront')

@section('title', $category->name.' Bedsheets | Priyanthi Multi Stores')
@section('description', $category->description)

@section('content')
<section class="section">
    <div class="container">
        <h1>{{ $category->name }}</h1>
        <p class="muted">{{ $category->description }}</p>
        <div class="filters">
            @foreach ($categories as $item)
                <a class="filter" href="{{ route('collections.show', $item) }}">{{ $item->name }}</a>
            @endforeach
        </div>
        <div class="grid" style="margin-top:20px">
            @foreach ($products as $product)
                @include('storefront.partials.product-card', ['product' => $product])
            @endforeach
        </div>
    </div>
</section>
@endsection

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'ShopCDC')</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, sans-serif; background: #f5f6f8; color: #1f2328; }
        header { background: #1f2937; color: #fff; padding: 12px 24px; display: flex; gap: 20px; align-items: center; }
        header a, header button { color: #fff; text-decoration: none; background: none; border: 0; font: inherit; cursor: pointer; }
        header .brand { font-weight: 700; margin-right: auto; }
        main { max-width: 1100px; margin: 24px auto; padding: 0 16px; }
        .status { background: #dcfce7; border: 1px solid #86efac; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; }
        .card { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.08); display: flex; flex-direction: column; }
        .card img { width: 100%; height: 160px; object-fit: cover; }
        .card .body { padding: 12px; flex: 1; display: flex; flex-direction: column; gap: 8px; }
        .price { font-weight: 700; font-size: 1.1rem; }
        .actions { display: flex; gap: 8px; margin-top: auto; }
        .btn { padding: 8px 12px; border-radius: 6px; border: 1px solid #d0d7de; background: #fff; cursor: pointer; }
        .btn-primary { background: #2563eb; border-color: #2563eb; color: #fff; }
        .btn-fav.on { background: #fee2e2; border-color: #fca5a5; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { padding: 10px; border-bottom: 1px solid #eee; text-align: left; }
        input[type=number] { width: 64px; padding: 6px; }
        form.inline { display: inline; }
    </style>
</head>
<body>
<header>
    <a class="brand" href="{{ route('products.index') }}">🛒 ShopCDC</a>
    @auth
        <a href="{{ route('wishlist.index') }}">♥ Favoris</a>
        <a href="{{ route('cart.index') }}">Panier ({{ auth()->user()->cartItems()->sum('quantity') }})</a>
        <form class="inline" method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Déconnexion ({{ auth()->user()->name }})</button>
        </form>
    @else
        <a href="{{ route('login') }}">Connexion</a>
    @endauth
</header>
<main>
    @if (session('status'))
        <div class="status">{{ session('status') }}</div>
    @endif

    @yield('content')
</main>
</body>
</html>

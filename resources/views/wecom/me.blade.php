<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $user->name }} - 天辰表行</title>
    <style>
        :root { color-scheme: light; }
        html, body { margin: 0; background: #ffffff; color: #111111; }
        main { font-family: sans-serif; padding: 2rem; }
        h1 { font-size: 1.5rem; font-weight: 600; margin: 0 0 1rem; }
        p { margin: 0.5rem 0; font-size: 1rem; }
    </style>
</head>
<body>
    <main>
        <h1>已进入，{{ $user->name }}</h1>
        <p>门店：{{ $user->store?->name ?? '—' }}</p>
        <p>专柜：{{ $user->counter?->name ?? '—' }}</p>
    </main>
</body>
</html>

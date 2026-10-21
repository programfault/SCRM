<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>已进入</title>
</head>
<body style="font-family: sans-serif; margin: 2rem;">
    <p>已进入，{{ $user->name }}</p>
    <p>门店：{{ $user->store?->name ?? '—' }}</p>
    <p>专柜：{{ $user->counter?->name ?? '—' }}</p>
</body>
</html>

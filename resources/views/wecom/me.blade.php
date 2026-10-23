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
        main { font-family: sans-serif; padding: 1.25rem; max-width: 40rem; }
        h1 { font-size: 1.5rem; font-weight: 600; margin: 0 0 1rem; }
        h2 { font-size: 1.125rem; font-weight: 600; margin: 0; }
        p { margin: 0.5rem 0; font-size: 1rem; }
        .phone { font-size: 1.25rem; letter-spacing: 0.02em; }
        section { border-top: 1px solid #e5e5e5; padding: 0.75rem 0; }
    </style>
</head>
<body>
    <main>
        <h1>已进入，{{ $user->name }}</h1>
        <p>门店：{{ $user->store?->name ?? '—' }}</p>
        <p>专柜：{{ $user->counter?->name ?? '—' }}</p>
        <p>待添加 {{ $pendingCustomers->count() }}，已加入 {{ $addedCustomers->count() }}</p>
        <p>加上微信客户后，把手机号写进客户名片的电话。管理员核对后，这里会变成已加入。</p>
        @forelse ($pendingCustomers as $customer)
            <section>
                <h2>{{ $customer->name }}</h2>
                <p class="phone">{{ $customer->phone }}</p>
                <p>待添加</p>
                @if ($customer->conflict_wecom_userid)
                    <p>这个号码已经出现在其他员工的客户名片上，仍要加到你自己的企业微信里。</p>
                @endif
            </section>
        @empty
            @if ($addedCustomers->isEmpty())
                <p>还没有分给你的旧客户</p>
            @endif
        @endforelse
        @foreach ($addedCustomers as $customer)
            <section>
                <h2>{{ $customer->name }}</h2>
                <p class="phone">{{ $customer->phone }}</p>
                <p>已加入</p>
            </section>
        @endforeach
    </main>
</body>
</html>

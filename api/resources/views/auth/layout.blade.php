<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name', 'Nox Tasks') }}</title>
    <style>
        :root { --bg:#f5f6f8; --card:#fff; --text:#1f2430; --muted:#667085; --border:#e4e7ec; --primary:#2563eb; --primary-text:#fff; --danger:#b42318; }
        @media (prefers-color-scheme: dark) {
            :root { --bg:#0f1115; --card:#181b21; --text:#e6e8ec; --muted:#98a2b3; --border:#2a2f38; --primary:#3b82f6; --danger:#f97066; }
        }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:16px; background:var(--bg); color:var(--text); font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; }
        .card { width:100%; max-width:400px; background:var(--card); border:1px solid var(--border); border-radius:12px; padding:28px; }
        h1 { font-size:20px; margin:0 0 4px; }
        p { margin:0 0 16px; color:var(--muted); }
        label { display:block; font-size:13px; font-weight:600; margin:14px 0 6px; }
        input { width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; background:transparent; color:inherit; font:inherit; }
        button { width:100%; padding:10px 14px; border-radius:8px; border:1px solid var(--border); background:transparent; color:inherit; font:inherit; font-weight:600; cursor:pointer; }
        button.primary { background:var(--primary); border-color:var(--primary); color:var(--primary-text); }
        .actions { display:flex; gap:10px; margin-top:20px; }
        .actions form { flex:1; }
        .error { color:var(--danger); font-size:13px; margin-top:6px; }
        ul { margin:0 0 16px; padding-left:18px; }
        strong { color:var(--text); }
    </style>
</head>
<body>
    <main class="card">
        @yield('content')
    </main>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Kolabing Admin' }}</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f5efe7;
            --surface: #fffdf8;
            --surface-alt: #f0e6d6;
            --text: #20180f;
            --muted: #6d5b48;
            --line: #d8c8b3;
            --accent: #b5542f;
            --accent-strong: #8f3f1f;
            --danger: #b72d2d;
            --success: #22663f;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
            background: linear-gradient(180deg, #efe3cf 0%, var(--bg) 42%, #f9f3eb 100%);
            color: var(--text);
        }
        a { color: inherit; }
        .shell {
            max-width: 1160px;
            margin: 0 auto;
            padding: 32px 20px 48px;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 28px;
        }
        .brand {
            text-decoration: none;
        }
        .brand small {
            display: block;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--muted);
            font-size: 12px;
        }
        .brand strong {
            display: block;
            font-size: 30px;
            line-height: 1;
        }
        .nav {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .button,
        button,
        input,
        select,
        textarea {
            font: inherit;
        }
        .button,
        button[type="submit"] {
            border: 0;
            border-radius: 999px;
            background: var(--accent);
            color: white;
            padding: 12px 18px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s ease;
        }
        .button:hover,
        button[type="submit"]:hover {
            background: var(--accent-strong);
        }
        .button.secondary {
            background: transparent;
            color: var(--text);
            border: 1px solid var(--line);
        }
        .card {
            background: rgba(255, 253, 248, 0.92);
            border: 1px solid rgba(216, 200, 179, 0.9);
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 18px 46px rgba(79, 56, 34, 0.08);
        }
        .card + .card {
            margin-top: 18px;
        }
        .flash {
            border-radius: 16px;
            padding: 14px 16px;
            margin-bottom: 18px;
            border: 1px solid transparent;
        }
        .flash.success {
            background: rgba(34, 102, 63, 0.1);
            border-color: rgba(34, 102, 63, 0.2);
            color: var(--success);
        }
        .flash.error {
            background: rgba(183, 45, 45, 0.08);
            border-color: rgba(183, 45, 45, 0.18);
            color: var(--danger);
        }
        .page-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 20px;
            margin-bottom: 20px;
        }
        .page-head h1 {
            margin: 0 0 6px;
            font-size: 34px;
        }
        .page-head p {
            margin: 0;
            color: var(--muted);
        }
        .grid {
            display: grid;
            gap: 18px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        label {
            display: block;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .field {
            margin-bottom: 18px;
        }
        .field input,
        .field select,
        .field textarea {
            width: 100%;
            border-radius: 16px;
            border: 1px solid var(--line);
            background: #fff;
            padding: 12px 14px;
        }
        .field textarea {
            min-height: 120px;
            resize: vertical;
        }
        .checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 8px;
        }
        .checkbox input {
            width: auto;
        }
        .errors {
            margin: 0 0 18px;
            padding: 0;
            list-style: none;
        }
        .errors li {
            color: var(--danger);
            margin-bottom: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th,
        td {
            padding: 14px 10px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: top;
        }
        th {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
        }
        .pill {
            display: inline-flex;
            padding: 6px 10px;
            border-radius: 999px;
            background: var(--surface-alt);
            color: var(--muted);
            font-size: 13px;
        }
        .muted {
            color: var(--muted);
        }
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        .login-wrap {
            max-width: 460px;
            margin: 60px auto 0;
        }
        @media (max-width: 720px) {
            .page-head,
            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }
            .grid {
                grid-template-columns: 1fr;
            }
            table,
            thead,
            tbody,
            tr,
            td,
            th {
                display: block;
            }
            thead {
                display: none;
            }
            tr {
                padding: 14px 0;
                border-bottom: 1px solid var(--line);
            }
            td {
                padding: 6px 0;
                border: 0;
            }
        }
    </style>
</head>
<body>
    <div class="shell">
        @if (! ($hideHeader ?? false))
            <div class="topbar">
                <a class="brand" href="/admin/users">
                    <small>Maintainer Panel</small>
                    <strong>Kolabing Admin</strong>
                </a>

                @if (auth('admin')->check())
                    <div class="nav">
                        <a class="button secondary" href="/admin/users">Users</a>
                        <form method="post" action="/admin/logout">
                            @csrf
                            <button type="submit">Logout</button>
                        </form>
                    </div>
                @endif
            </div>
        @endif

        @if (session('status'))
            <div class="flash success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="flash error">
                <ul class="errors">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </div>
</body>
</html>

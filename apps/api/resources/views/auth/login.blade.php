<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title>ورود WorkTracker</title>
    <style>:root {
            color-scheme: dark;
            --bg: #0b1020;
            --panel: #121a2b;
            --input: #0d1424;
            --border: #2a3854;
            --text: #e6edf7;
            --muted: #92a0b5;
            --primary: #5b8fff;
            --danger: #ff9a92
        }

        * {
            box-sizing: border-box
        }

        body {
            font-family: Vazirmatn, Tahoma, Arial, sans-serif;
            background: radial-gradient(circle at 80% 0, rgba(78, 127, 255, .18), transparent 35%), linear-gradient(180deg, #0a0f1d, #0b1020);
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            color: var(--text)
        }

        .card {
            width: min(420px, calc(100% - 28px));
            background: linear-gradient(180deg, #172033, #121a2b);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .34)
        }

        h1 {
            margin: 0 0 6px;
            color: #f5f8fc
        }

        .muted {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 22px
        }

        label {
            display: grid;
            gap: 6px;
            margin: 12px 0;
            font-size: 13px;
            color: #bec9d9
        }

        input {
            padding: 11px;
            border: 1px solid #31405f;
            border-radius: 10px;
            font: inherit;
            background: var(--input);
            color: var(--text);
            outline: none;
            transition: .15s border-color, .15s box-shadow, .15s background
        }

        input:focus {
            border-color: #6ea8fe;
            box-shadow: 0 0 0 3px rgba(110, 168, 254, .14);
            background: #10192b
        }

        input[type=checkbox] {
            accent-color: var(--primary)
        }

        button {
            width: 100%;
            padding: 11px;
            border: 1px solid #6c9cff;
            border-radius: 10px;
            background: linear-gradient(145deg, #4d7fff, #6ea8fe);
            color: #fff;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 8px 22px rgba(78, 127, 255, .22)
        }

        button:hover {
            filter: brightness(1.06)
        }

        .err {
            background: #3a1f27;
            border: 1px solid #6c3440;
            color: var(--danger);
            padding: 10px;
            border-radius: 10px
        }</style>
</head>
<body>
<form class="card" method="post" action="{{ route('login.store') }}">@csrf<h1>WorkTracker</h1>
    <div class="muted">ورود به داشبورد مدیریت کار و پروژه</div>@if($errors->any())
        <div class="err">{{ $errors->first() }}</div>
    @endif<label>ایمیل<input type="email" name="email" value="{{ old('email') }}" required autofocus></label><label>رمز عبور<input
            type="password" name="password" required></label><label
        style="display:flex;grid-auto-flow:column;justify-content:start"><input type="checkbox" name="remember" value="1"> مرا به
        خاطر بسپار</label>
    <button>ورود</button>
</form>
</body>
</html>

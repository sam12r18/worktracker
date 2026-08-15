<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ورود WorkTracker</title>
    <style>body {
            font-family: Tahoma, Arial;
            background: #f4f6fb;
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            color: #182235
        }

        .card {
            width: min(420px, calc(100% - 28px));
            background: #fff;
            border: 1px solid #e2e7f0;
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 12px 40px #1b274b12
        }

        h1 {
            margin: 0 0 6px
        }

        .muted {
            color: #6e7a8d;
            font-size: 13px;
            margin-bottom: 22px
        }

        label {
            display: grid;
            gap: 6px;
            margin: 12px 0;
            font-size: 13px
        }

        input {
            padding: 11px;
            border: 1px solid #d8deea;
            border-radius: 10px;
            font: inherit
        }

        button {
            width: 100%;
            padding: 11px;
            border: 0;
            border-radius: 10px;
            background: #3158d4;
            color: #fff;
            font: inherit;
            font-weight: 700;
            cursor: pointer
        }

        .err {
            background: #fdecec;
            color: #a8241d;
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

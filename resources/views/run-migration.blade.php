<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Run Migration | NEXA ERP</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; padding: 40px 18px; display: grid; place-items: center; background: #f4f6fb; color: #182230; font-family: Inter, ui-sans-serif, system-ui, -apple-system, sans-serif; }
        .panel { width: min(100%, 680px); padding: 32px; border: 1px solid #e3e8ef; border-radius: 18px; background: #fff; box-shadow: 0 18px 50px rgba(20, 35, 60, .08); }
        .eyebrow { margin: 0 0 10px; color: #52637a; font-size: 12px; font-weight: 750; letter-spacing: .12em; text-transform: uppercase; }
        h1 { margin: 0; font-size: clamp(25px, 4vw, 34px); letter-spacing: -.04em; }
        .intro { margin: 10px 0 28px; color: #637083; line-height: 1.6; }
        label { display: block; margin-bottom: 9px; font-size: 14px; font-weight: 700; }
        input { width: 100%; min-height: 50px; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 9px; color: #182230; font: 14px ui-monospace, SFMono-Regular, Menlo, monospace; outline: none; }
        input:focus { border-color: #315fcb; box-shadow: 0 0 0 3px rgba(49, 95, 203, .13); }
        .hint { margin: 9px 0 20px; color: #718096; font-size: 12px; line-height: 1.5; }
        button { width: 100%; min-height: 48px; border: 0; border-radius: 9px; background: #244fc2; color: #fff; font-size: 14px; font-weight: 750; cursor: pointer; }
        button:hover { background: #1d42a5; }
        button:disabled { opacity: .65; cursor: wait; }
        .notice { margin-top: 20px; padding: 14px 16px; border-radius: 9px; white-space: pre-wrap; overflow-wrap: anywhere; font-size: 13px; line-height: 1.6; }
        .success { border: 1px solid #a7dfc1; background: #effbf4; color: #17633b; }
        .error { border: 1px solid #f2b8b5; background: #fff4f3; color: #9d2822; }
        .field-error { margin-top: 8px; color: #b42318; font-size: 12px; }
        .warning { margin-top: 22px; padding-top: 18px; border-top: 1px solid #edf0f4; color: #7b5a19; font-size: 12px; line-height: 1.6; }
        .back { display: inline-block; margin-top: 20px; color: #315fcb; font-size: 13px; text-decoration: none; }
    </style>
</head>
<body>
    <main class="panel">
        <p class="eyebrow">NEXA / ERP · Maintenance</p>
        <h1>Run a database migration</h1>
        <p class="intro">Enter the migration filename only. The system will look for that exact file inside Laravel's database/migrations folder and run only that migration.</p>

        <form method="POST" action="{{ route('run-migration.execute') }}" onsubmit="const b=this.querySelector('button'); b.disabled=true; b.textContent='Running migration…';">
            @csrf
            <label for="migration">Migration filename</label>
            <input
                id="migration"
                name="migration"
                type="text"
                value="{{ old('migration', '2026_11_13_000001_add_sales_tax_profile_retailer_to_companies.php') }}"
                placeholder="2026_11_13_000001_add_sales_tax_profile_retailer_to_companies.php"
                autocomplete="off"
                spellcheck="false"
                required
            >
            <p class="hint">Filename only — do not enter database/migrations or any other folder path.</p>
            @error('migration')
                <div class="field-error">{{ $message }}</div>
            @enderror
            <button type="submit">Run selected migration</button>
        </form>

        @if (session('migration_success'))
            <div class="notice success" role="status">{{ session('migration_success') }}</div>
        @endif
        @if (session('migration_error'))
            <div class="notice error" role="alert">{{ session('migration_error') }}</div>
        @endif

        <div class="warning">
            Only run a migration after reviewing its purpose and taking a database backup. This action changes the connected database schema.
        </div>
        <a class="back" href="/#/overview">← Back to ERP workspace</a>
    </main>
</body>
</html>

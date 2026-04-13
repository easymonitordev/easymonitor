<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Icon Preview</title>
    @vite(['resources/css/app.css'])
    <style>
        body { margin: 0; padding: 0; display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #fff; }
        .plain { width: 1024px; height: 1024px; display: flex; align-items: center; justify-content: center; }
        .plain svg { width: 100%; height: 100%; stroke-width: 2; color: #000; }
        .rounded { width: 1024px; height: 1024px; border-radius: 180px; background: #1d232a; display: flex; align-items: center; justify-content: center; }
        .rounded svg { width: 60%; height: 60%; color: #fff; stroke-width: 2; }
        .grid { display: flex; flex-direction: column; gap: 40px; padding: 40px; }
        .row { display: flex; gap: 40px; align-items: center; }
        .label { font-family: Inter, sans-serif; color: #555; }
    </style>
</head>
<body>
    <div class="grid">
        <div class="row">
            <div class="plain">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19.07 4.93A10 10 0 0 0 6.99 3.34"/>
                    <path d="M4 6h.01"/>
                    <path d="M2.29 9.62A10 10 0 1 0 21.31 8.35"/>
                    <path d="M16.24 7.76A6 6 0 1 0 8.23 16.67"/>
                    <path d="M12 18h.01"/>
                    <path d="M17.99 11.66A6 6 0 0 1 15.77 16.67"/>
                    <circle cx="12" cy="12" r="2"/>
                    <path d="m13.41 10.59 5.66-5.66"/>
                </svg>
            </div>
            <div class="label">Plain — for favicon.svg</div>
        </div>
        <div class="row">
            <div class="rounded">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19.07 4.93A10 10 0 0 0 6.99 3.34"/>
                    <path d="M4 6h.01"/>
                    <path d="M2.29 9.62A10 10 0 1 0 21.31 8.35"/>
                    <path d="M16.24 7.76A6 6 0 1 0 8.23 16.67"/>
                    <path d="M12 18h.01"/>
                    <path d="M17.99 11.66A6 6 0 0 1 15.77 16.67"/>
                    <circle cx="12" cy="12" r="2"/>
                    <path d="m13.41 10.59 5.66-5.66"/>
                </svg>
            </div>
            <div class="label">Rounded (iOS style) — for apple-touch-icon.png</div>
        </div>
    </div>
</body>
</html>

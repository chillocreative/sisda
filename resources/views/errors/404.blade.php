<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Halaman Tidak Dijumpai - SISDA</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Figtree', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            overflow: hidden;
            position: relative;
        }
        .particles { position: fixed; inset: 0; overflow: hidden; z-index: 0; }
        .particle { position: absolute; border-radius: 50%; animation: float linear infinite; }
        .particle:nth-child(1) { width: 300px; height: 300px; top: -10%; left: -5%; animation-duration: 25s; background: rgba(56, 189, 248, 0.08); }
        .particle:nth-child(2) { width: 200px; height: 200px; top: 60%; right: -5%; animation-duration: 20s; animation-delay: -5s; background: rgba(168, 85, 247, 0.06); }
        .particle:nth-child(3) { width: 150px; height: 150px; bottom: -5%; left: 30%; animation-duration: 22s; animation-delay: -10s; background: rgba(56, 189, 248, 0.05); }
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg) scale(1); }
            25% { transform: translateY(-30px) rotate(90deg) scale(1.05); }
            50% { transform: translateY(0) rotate(180deg) scale(1); }
            75% { transform: translateY(30px) rotate(270deg) scale(1.05); }
        }
        .glow-ring { position: absolute; width: 500px; height: 500px; border-radius: 50%; border: 1px solid rgba(168, 85, 247, 0.1); top: 50%; left: 50%; transform: translate(-50%, -50%); animation: pulse-ring 4s ease-in-out infinite; }
        .glow-ring::after { content: ''; position: absolute; inset: 30px; border-radius: 50%; border: 1px solid rgba(56, 189, 248, 0.08); animation: pulse-ring 4s ease-in-out infinite reverse; }
        @keyframes pulse-ring { 0%, 100% { transform: translate(-50%, -50%) scale(1); opacity: 0.5; } 50% { transform: translate(-50%, -50%) scale(1.1); opacity: 1; } }
        .container { position: relative; z-index: 10; text-align: center; padding: 2rem; max-width: 480px; width: 100%; }
        .icon-wrapper { display: inline-flex; align-items: center; justify-content: center; width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, rgba(168, 85, 247, 0.1), rgba(56, 189, 248, 0.1)); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1); margin-bottom: 2rem; animation: icon-breathe 3s ease-in-out infinite; }
        @keyframes icon-breathe { 0%, 100% { box-shadow: 0 0 30px rgba(168, 85, 247, 0.1), 0 0 60px rgba(56, 189, 248, 0.05); } 50% { box-shadow: 0 0 50px rgba(168, 85, 247, 0.2), 0 0 100px rgba(56, 189, 248, 0.1); } }
        .icon-wrapper svg { width: 48px; height: 48px; color: #a855f7; }
        .error-code { font-size: 5rem; font-weight: 700; background: linear-gradient(135deg, #a855f7, #38bdf8, #a855f7); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; line-height: 1; margin-bottom: 0.5rem; animation: gradient-shift 6s ease-in-out infinite; background-size: 200% 200%; }
        @keyframes gradient-shift { 0%, 100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }
        .title { font-size: 1.5rem; font-weight: 600; color: #e2e8f0; margin-bottom: 1rem; }
        .description { font-size: 0.95rem; color: #94a3b8; line-height: 1.7; margin-bottom: 2.5rem; }
        .btn-group { display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; }
        .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.75rem; border-radius: 12px; font-size: 0.9rem; font-weight: 500; font-family: inherit; cursor: pointer; transition: all 0.3s ease; text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, #a855f7, #38bdf8); color: white; border: none; box-shadow: 0 4px 15px rgba(168, 85, 247, 0.3); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(168, 85, 247, 0.4); }
        .btn-secondary { background: rgba(255, 255, 255, 0.05); color: #cbd5e1; border: 1px solid rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); }
        .btn-secondary:hover { background: rgba(255, 255, 255, 0.1); transform: translateY(-2px); border-color: rgba(255, 255, 255, 0.2); }
        .btn svg { width: 18px; height: 18px; }
        .footer { margin-top: 3rem; color: #475569; font-size: 0.8rem; }
        .footer span { color: #64748b; }
        @media (max-width: 480px) { .error-code { font-size: 3.5rem; } .title { font-size: 1.25rem; } .icon-wrapper { width: 90px; height: 90px; } .icon-wrapper svg { width: 36px; height: 36px; } .glow-ring { width: 300px; height: 300px; } }
    </style>
</head>
<body>
    <div class="particles"><div class="particle"></div><div class="particle"></div><div class="particle"></div></div>
    <div class="glow-ring"></div>
    <div class="container">
        <div class="icon-wrapper">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
        </div>
        <div class="error-code">404</div>
        <h1 class="title">Halaman Tidak Dijumpai</h1>
        <p class="description">Halaman yang anda cari tidak wujud atau telah dipindahkan ke lokasi lain.</p>
        <div class="btn-group">
            <a href="javascript:history.back()" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" /></svg>
                Kembali
            </a>
            <a href="/" class="btn btn-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg>
                Laman Utama
            </a>
        </div>
        <div class="footer">SISDA <span>&mdash; Sistem Data Pengundi</span></div>
    </div>
</body>
</html>

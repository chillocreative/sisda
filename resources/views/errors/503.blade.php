<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Penyelenggaraan - SISDA</title>
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
        .particle:nth-child(1) { width: 300px; height: 300px; top: -10%; left: -5%; animation-duration: 25s; background: rgba(16, 185, 129, 0.08); }
        .particle:nth-child(2) { width: 200px; height: 200px; top: 60%; right: -5%; animation-duration: 20s; animation-delay: -5s; background: rgba(56, 189, 248, 0.06); }
        .particle:nth-child(3) { width: 150px; height: 150px; bottom: -5%; left: 30%; animation-duration: 22s; animation-delay: -10s; background: rgba(16, 185, 129, 0.05); }
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg) scale(1); }
            25% { transform: translateY(-30px) rotate(90deg) scale(1.05); }
            50% { transform: translateY(0) rotate(180deg) scale(1); }
            75% { transform: translateY(30px) rotate(270deg) scale(1.05); }
        }
        .glow-ring { position: absolute; width: 500px; height: 500px; border-radius: 50%; border: 1px solid rgba(16, 185, 129, 0.1); top: 50%; left: 50%; transform: translate(-50%, -50%); animation: pulse-ring 4s ease-in-out infinite; }
        .glow-ring::after { content: ''; position: absolute; inset: 30px; border-radius: 50%; border: 1px solid rgba(56, 189, 248, 0.08); animation: pulse-ring 4s ease-in-out infinite reverse; }
        @keyframes pulse-ring { 0%, 100% { transform: translate(-50%, -50%) scale(1); opacity: 0.5; } 50% { transform: translate(-50%, -50%) scale(1.1); opacity: 1; } }
        .container { position: relative; z-index: 10; text-align: center; padding: 2rem; max-width: 480px; width: 100%; }
        .icon-wrapper { display: inline-flex; align-items: center; justify-content: center; width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(56, 189, 248, 0.1)); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1); margin-bottom: 2rem; animation: icon-breathe 3s ease-in-out infinite; }
        @keyframes icon-breathe { 0%, 100% { box-shadow: 0 0 30px rgba(16, 185, 129, 0.1), 0 0 60px rgba(56, 189, 248, 0.05); } 50% { box-shadow: 0 0 50px rgba(16, 185, 129, 0.2), 0 0 100px rgba(56, 189, 248, 0.1); } }
        .icon-wrapper svg { width: 48px; height: 48px; color: #10b981; animation: spin 8s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .error-code { font-size: 5rem; font-weight: 700; background: linear-gradient(135deg, #10b981, #38bdf8, #10b981); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; line-height: 1; margin-bottom: 0.5rem; animation: gradient-shift 6s ease-in-out infinite; background-size: 200% 200%; }
        @keyframes gradient-shift { 0%, 100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }
        .title { font-size: 1.5rem; font-weight: 600; color: #e2e8f0; margin-bottom: 1rem; }
        .description { font-size: 0.95rem; color: #94a3b8; line-height: 1.7; margin-bottom: 2.5rem; }
        .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.75rem; border-radius: 12px; font-size: 0.9rem; font-weight: 500; font-family: inherit; cursor: pointer; transition: all 0.3s ease; text-decoration: none; background: linear-gradient(135deg, #10b981, #38bdf8); color: white; border: none; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4); }
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
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.049.58.025 1.193-.14 1.743" />
            </svg>
        </div>
        <div class="error-code">503</div>
        <h1 class="title">Sedang Dalam Penyelenggaraan</h1>
        <p class="description">Sistem sedang dikemaskini untuk pengalaman yang lebih baik. Kami akan kembali sebentar lagi. Terima kasih atas kesabaran anda.</p>
        <a href="javascript:void(0)" class="btn" onclick="window.location.reload();">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" /></svg>
            Cuba Semula
        </a>
        <div class="footer">SISDA <span>&mdash; Sistem Data Pengundi</span></div>
    </div>
</body>
</html>

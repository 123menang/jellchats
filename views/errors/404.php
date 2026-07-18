<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>404 — Halaman Tidak Ditemukan</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{
  font-family:'Inter',system-ui,sans-serif;
  background:linear-gradient(135deg, #f5f7fa 0%, #eef2f7 100%);
  min-height:100vh;
  display:flex;
  align-items:center;
  justify-content:center;
  padding:20px;
}
.error-container{
  background:#fff;
  border-radius:32px;
  padding:64px 48px;
  max-width:480px;
  width:100%;
  box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);
  text-align:center;
}
.error-code{font-size:96px;font-weight:800;color:#1e62ff;line-height:1;margin-bottom:8px;}
.error-title{font-size:24px;font-weight:700;margin-bottom:12px;color:#111827;}
.error-desc{font-size:14px;color:#6b7280;line-height:1.6;margin-bottom:32px;}
.btn{
  display:inline-flex;align-items:center;gap:8px;
  padding:12px 28px;background:#1e62ff;color:#fff;border:none;
  border-radius:14px;font-size:15px;font-weight:600;cursor:pointer;
  text-decoration:none;transition:all 0.3s ease;font-family:inherit;
}
.btn:hover{background:#1348cc;transform:translateY(-2px);box-shadow:0 8px 25px rgba(30,98,255,0.3);}
</style>
</head>
<body>
<div class="error-container">
  <div class="error-code">404</div>
  <div class="error-title">Halaman Tidak Ditemukan</div>
  <div class="error-desc">Halaman yang Anda cari tidak ada atau telah dipindahkan. Periksa kembali URL atau kembali ke dashboard.</div>
  <a href="/chats" class="btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg> Kembali ke Dashboard</a>
</div>
</body>
</html>
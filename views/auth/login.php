<?php
/**
 * Login View
 * @var string $error
 */
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — LiveChat Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{
  --blue:#1e62ff;
  --blue-dark:#1348cc;
  --blue-light:#eff6ff;
  --green:#10b981;
  --red:#ef4444;
  --dark:#0a0f1e;
  --dark2:#1a2035;
  --text:#111827;
  --muted:#6b7280;
  --border:#e5e7eb;
  --bg:#fafafa;
}
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
.login-container{
  background:#fff;
  border-radius:32px;
  padding:48px 40px;
  max-width:420px;
  width:100%;
  box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);
}
.login-header{text-align:center;margin-bottom:32px;}
.login-header h1{font-size:28px;font-weight:800;margin-bottom:8px;}
.login-header p{color:var(--muted);font-size:14px;}
.login-header p a{color:var(--blue);text-decoration:none;font-weight:600;}
.form-group{margin-bottom:20px;}
.form-group label{display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:var(--text);}
.input-wrapper{position:relative;}
.input-wrapper i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:16px;}
.input-wrapper input{
  width:100%;padding:14px 14px 14px 44px;
  border:1.5px solid var(--border);border-radius:12px;font-size:14px;
  transition:all 0.3s ease;font-family:inherit;
}
.input-wrapper input:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(30,98,255,0.1);}
.login-btn{
  width:100%;padding:14px;background:var(--blue);color:#fff;border:none;
  border-radius:14px;font-size:16px;font-weight:700;cursor:pointer;
  transition:all 0.3s ease;font-family:inherit;
}
.login-btn:hover{background:var(--blue-dark);transform:translateY(-2px);box-shadow:0 8px 25px rgba(30,98,255,0.3);}
.divider{display:flex;align-items:center;gap:16px;margin:24px 0;}
.divider-line{flex:1;height:1px;background:var(--border);}
.divider-text{font-size:12px;color:var(--muted);}
.message{padding:12px;border-radius:10px;margin-bottom:20px;font-size:13px;background:#fee2e2;color:var(--red);border:1px solid #fecaca;text-align:center;}
@media(max-width:480px){.login-container{padding:28px 20px;}}
</style>
</head>
<body>
<div class="login-container">
  <div class="login-header">
    <h1>Welcome Back</h1>
    <p>Sign in to your account</p>
  </div>

  <?php if ($error): ?>
    <div class="message"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post" action="/login">
    <?= \App\Core\App::auth()->csrfField() ?>
    <div class="form-group">
      <label>Email or Username</label>
      <div class="input-wrapper">
        <i class="fa-solid fa-envelope"></i>
        <input type="text" name="username" placeholder="name@company.com" required autocomplete="username">
      </div>
    </div>
    <div class="form-group">
      <label>Password</label>
      <div class="input-wrapper">
        <i class="fa-solid fa-lock"></i>
        <input type="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
      </div>
    </div>
    <button type="submit" class="login-btn">Sign In</button>
  </form>

  <div class="divider">
    <div class="divider-line"></div>
    <span class="divider-text">or</span>
    <div class="divider-line"></div>
  </div>

  <p style="text-align:center;font-size:13px;color:var(--muted);">
    Don't have an account? <a href="/signup" style="color:var(--blue);text-decoration:none;font-weight:600;">Sign up</a>
  </p>
</div>
</body>
</html>

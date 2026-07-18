<?php
/**
 * Signup View
 * @var string $error
 * @var string $success
 */
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign Up — LiveChat Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{
  --blue:#1e62ff;
  --blue-dark:#1348cc;
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
.signup-container{
  background:#fff;
  border-radius:32px;
  padding:48px 40px;
  max-width:480px;
  width:100%;
  box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);
}
.signup-header{text-align:center;margin-bottom:32px;}
.signup-header h1{font-size:28px;font-weight:800;margin-bottom:8px;}
.signup-header p{color:var(--muted);font-size:14px;}
.signup-header p a{color:var(--blue);text-decoration:none;font-weight:600;}
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
.phone-wrapper{display:flex;gap:12px;}
.country-code{width:100px;padding:14px;border:1.5px solid var(--border);border-radius:12px;font-size:14px;background:#fff;font-family:inherit;}
.country-code:focus{outline:none;border-color:var(--blue);}
.phone-wrapper .input-wrapper{flex:1;}
.signup-btn{
  width:100%;padding:14px;background:var(--blue);color:#fff;border:none;
  border-radius:14px;font-size:16px;font-weight:700;cursor:pointer;
  transition:all 0.3s ease;font-family:inherit;
}
.signup-btn:hover{background:var(--blue-dark);transform:translateY(-2px);box-shadow:0 8px 25px rgba(30,98,255,0.3);}
.message{padding:12px;border-radius:10px;margin-bottom:20px;font-size:13px;text-align:center;}
.message.error{background:#fee2e2;color:var(--red);border:1px solid #fecaca;}
.message.success{background:#d1fae5;color:var(--green);border:1px solid #a7f3d0;}
@media(max-width:480px){.signup-container{padding:28px 20px;}}
</style>
</head>
<body>
<div class="signup-container">
  <div class="signup-header">
    <h1>Create Account</h1>
    <p>Already have an account? <a href="/login">Sign in</a></p>
  </div>

  <?php if ($error): ?>
    <div class="message error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="message success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <form method="post" action="/signup">
    <?= \App\Core\App::auth()->csrfField() ?>
    <div class="form-group">
      <label>Email</label>
      <div class="input-wrapper">
        <i class="fa-solid fa-envelope"></i>
        <input type="email" name="email" placeholder="name@company.com" required autocomplete="email">
      </div>
    </div>
    <div class="form-group">
      <label>Phone Number</label>
      <div class="phone-wrapper">
        <select name="country_code" class="country-code">
          <option value="+62">+62 (ID)</option>
          <option value="+60">+60 (MY)</option>
          <option value="+65">+65 (SG)</option>
          <option value="+1">+1 (US)</option>
          <option value="+44">+44 (UK)</option>
          <option value="+81">+81 (JP)</option>
        </select>
        <div class="input-wrapper">
          <i class="fa-solid fa-phone"></i>
          <input type="tel" name="phone" placeholder="81234567890" required autocomplete="tel">
        </div>
      </div>
    </div>
    <div class="form-group">
      <label>Password</label>
      <div class="input-wrapper">
        <i class="fa-solid fa-lock"></i>
        <input type="password" name="password" placeholder="Min. 8 characters" required>
      </div>
    </div>
    <div class="form-group">
      <label>Confirm Password</label>
      <div class="input-wrapper">
        <i class="fa-solid fa-lock"></i>
        <input type="password" name="confirm_password" placeholder="Re-enter password" required>
      </div>
    </div>
    <button type="submit" class="signup-btn">Create Account</button>
  </form>
</div>
</body>
</html>

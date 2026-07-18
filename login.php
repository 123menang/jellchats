<?php
require_once 'includes/auth.php';

if ($auth->isLoggedIn()) {
    header('Location: chats');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid session. Please refresh the page.';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $result = $auth->login($username, $password);
        if ($result['success']) {
            header('Location: chats');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk ke JellChat Pro — Platform Live Chat & AI Chatbot</title>
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
*{
  margin:0;
  padding:0;
  box-sizing:border-box;
}
body{
  font-family:'Inter',system-ui,sans-serif;
  background:linear-gradient(135deg, #f5f7fa 0%, #eef2f7 100%);
  min-height:100vh;
  color:var(--text);
}
.container{
  display:flex;
  min-height:100vh;
}
/* Left Side - Branding */
.brand-side{
  flex:1;
  background:linear-gradient(135deg, var(--dark) 0%, var(--dark2) 100%);
  color:#fff;
  padding:60px 40px;
  display:flex;
  flex-direction:column;
  justify-content:space-between;
  position:relative;
  overflow:hidden;
}
.brand-side::before{
  content:'';
  position:absolute;
  top:-50%;
  right:-50%;
  width:200%;
  height:200%;
  background:radial-gradient(circle, rgba(30,98,255,0.1) 0%, transparent 70%);
  pointer-events:none;
}
.brand-logo{
  display:flex;
  align-items:center;
  gap:15px;
  margin-bottom:60px;
  position:relative;
  z-index:1;
}
.brand-logo img{
  width:180px; /* Ukuran logo disesuaikan */
  height:auto;
  object-fit:contain;
}
.brand-content{
  position:relative;
  z-index:1;
}
.brand-content h1{
  font-size:42px;
  font-weight:800;
  line-height:1.2;
  margin-bottom:24px;
  letter-spacing:-1px;
}
.brand-content h1 span{
  background:linear-gradient(135deg,var(--blue),#8b5cf6);
  -webkit-background-clip:text;
  -webkit-text-fill-color:transparent;
}
.brand-content p{
  font-size:18px;
  opacity:0.8;
  line-height:1.6;
  margin-bottom:40px;
  max-width:400px;
}
.feature-list{
  list-style:none;
}
.feature-list li{
  display:flex;
  align-items:center;
  gap:12px;
  margin-bottom:20px;
  font-size:15px;
}
.feature-list li i{
  width:24px;
  height:24px;
  background:rgba(30,98,255,0.2);
  border-radius:50%;
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:12px;
  color:var(--blue);
}
.brand-footer{
  position:relative;
  z-index:1;
  font-size:13px;
  opacity:0.6;
}
/* Right Side - Form */
.form-side{
  flex:1;
  display:flex;
  align-items:center;
  justify-content:center;
  padding:40px;
}
.form-container{
  max-width:460px;
  width:100%;
  background:#fff;
  border-radius:32px;
  padding:48px 40px;
  box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);
}
.form-header{
  text-align:center;
  margin-bottom:32px;
}
.form-header h2{
  font-size:28px;
  font-weight:800;
  margin-bottom:8px;
}
.form-header p{
  color:var(--muted);
  font-size:14px;
}
.form-header p a{
  color:var(--blue);
  text-decoration:none;
  font-weight:600;
}
.form-header p a:hover{
  text-decoration:underline;
}
/* Social Buttons */
.social-buttons{
  display:flex;
  gap:12px;
  margin-bottom:24px;
}
.social-btn{
  flex:1;
  display:flex;
  align-items:center;
  justify-content:center;
  gap:10px;
  padding:12px;
  border:1.5px solid var(--border);
  border-radius:12px;
  background:#fff;
  cursor:pointer;
  transition:all 0.3s ease;
  font-weight:500;
  font-size:14px;
  color:var(--text);
  text-decoration:none;
}
.social-btn:hover{
  border-color:var(--blue);
  background:#f8faff;
  transform:translateY(-2px);
}
.social-btn i{
  font-size:18px;
}
.social-btn.google i{color:#ea4335;}
.social-btn.facebook i{color:#1877f2;}
/* Divider */
.divider{
  display:flex;
  align-items:center;
  gap:16px;
  margin-bottom:24px;
}
.divider-line{
  flex:1;
  height:1px;
  background:var(--border);
}
.divider-text{
  font-size:12px;
  color:var(--muted);
}
/* Form Group */
.form-group{
  margin-bottom:20px;
}
.form-group label{
  display:block;
  font-size:13px;
  font-weight:600;
  margin-bottom:6px;
  color:var(--text);
}
.input-wrapper{
  position:relative;
}
.input-wrapper i{
  position:absolute;
  left:14px;
  top:50%;
  transform:translateY(-50%);
  color:var(--muted);
  font-size:16px;
}
.input-wrapper input{
  width:100%;
  padding:14px 14px 14px 44px;
  border:1.5px solid var(--border);
  border-radius:12px;
  font-size:14px;
  transition:all 0.3s ease;
  font-family:inherit;
}
.input-wrapper input:focus{
  outline:none;
  border-color:var(--blue);
  box-shadow:0 0 0 3px rgba(30,98,255,0.1);
}
.input-wrapper .toggle-password{
  position:absolute;
  right:14px;
  top:50%;
  transform:translateY(-50%);
  cursor:pointer;
  color:var(--muted);
  transition:color 0.3s;
}
.input-wrapper .toggle-password:hover{
  color:var(--blue);
}
/* Submit Button */
.login-btn{
  width:100%;
  padding:14px;
  background:var(--blue);
  color:#fff;
  border:none;
  border-radius:14px;
  font-size:16px;
  font-weight:700;
  cursor:pointer;
  transition:all 0.3s ease;
  display:flex;
  align-items:center;
  justify-content:center;
  gap:8px;
}
.login-btn:hover{
  background:var(--blue-dark);
  transform:translateY(-2px);
  box-shadow:0 8px 25px rgba(30,98,255,0.3);
}
/* Error Message */
.message{
  padding:12px;
  border-radius:10px;
  margin-bottom:20px;
  font-size:13px;
  background:#fee2e2;
  color:var(--red);
  border:1px solid #fecaca;
}
/* Responsive */
@media(max-width:992px){
  .brand-side{ display:none; }
  .form-side{ padding:20px; }
  .form-container{ padding:36px 28px; }
}
</style>
</head>
<body>
<div class="container">
  <div class="brand-side">
    <div class="brand-logo">
      <img src="assets/images/logo-white.png" alt="JellChat Pro">
    </div>
    <div class="brand-content">
      <h1>Selamat <span>Datang</span><br>Kembali</h1>
      <p>Masuk untuk mengelola obrolan pelanggan dan tim Anda secara real-time.</p>
      <ul class="feature-list">
        <li><i class="fa-solid fa-check"></i> Monitoring Chat Real-time</li>
        <li><i class="fa-solid fa-check"></i> Manajemen Agent & Tim</li>
        <li><i class="fa-solid fa-check"></i> Analitik Performa Lengkap</li>
      </ul>
    </div>
    <div class="brand-footer">
      <p>© 2025 JellChat Pro. All rights reserved.</p>
    </div>
  </div>

  <div class="form-side">
    <div class="form-container">
      <div class="form-header">
        <h2>Masuk ke Akun</h2>
        <p>Belum punya akun? <a href="register.php">Daftar sekarang</a></p>
      </div>

      <?php if ($error): ?>
      <div class="message">
          <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
      </div>
      <?php endif; ?>

      <div class="social-buttons">
        <a href="#" class="social-btn google">
          <i class="fa-brands fa-google"></i> Google
        </a>
        <a href="#" class="social-btn facebook">
          <i class="fa-brands fa-facebook"></i> Facebook
        </a>
      </div>

      <div class="divider">
        <div class="divider-line"></div>
        <span class="divider-text">atau gunakan username</span>
        <div class="divider-line"></div>
      </div>

      <form action="" method="POST">
        <?= $auth->csrfField() ?>
        <div class="form-group">
          <label>Username atau Email</label>
          <div class="input-wrapper">
            <i class="fa-solid fa-user"></i>
            <input type="text" name="username" placeholder="Masukkan username" required value="<?php echo htmlspecialchars($username ?? ''); ?>">
          </div>
        </div>

        <div class="form-group">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
            <label style="margin-bottom: 0;">Password</label>
            <a href="forgot-password.php" style="font-size: 12px; color: var(--blue); text-decoration: none;">Lupa password?</a>
          </div>
          <div class="input-wrapper">
            <i class="fa-solid fa-lock"></i>
            <input type="password" id="password" name="password" placeholder="••••••••" required>
            <span class="toggle-password" onclick="togglePassword('password')">
              <i class="fa-regular fa-eye"></i>
            </span>
          </div>
        </div>

        <button type="submit" class="login-btn">
          <i class="fa-solid fa-right-to-bracket"></i> Masuk Sekarang
        </button>
      </form>
    </div>
  </div>
</div>

<script>
function togglePassword(fieldId) {
  const field = document.getElementById(fieldId);
  const icon = field.parentElement.querySelector('.toggle-password i');
  if (field.type === 'password') {
    field.type = 'text';
    icon.classList.replace('fa-eye', 'fa-eye-slash');
  } else {
    field.type = 'password';
    icon.classList.replace('fa-eye-slash', 'fa-eye');
  }
}
</script>
</body>
</html>
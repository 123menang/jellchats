<?php
/**
 * Signup Page - User Registration
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$db = Database::getInstance();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    // Validate
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (empty($phone) || !preg_match('/^\+?[0-9]{8,15}$/', $phone)) {
        $error = 'Invalid phone number.';
    } elseif (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $error = 'Password must be at least 8 characters with letters and numbers.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Check existing email
        $existing = $db->fetch("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            $error = 'Email already registered.';
        } else {
            // Create user
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $db->insert("INSERT INTO users (email, phone, password, role, created_at) VALUES (?, ?, ?, 'agent', datetime('now'))", [$email, $phone, $hashed]);
            $userId = $db->lastInsertId();
            
            // Create agent record
            $db->insert("INSERT INTO agents (user_id, display_name, email, role, is_online, created_at) VALUES (?, ?, ?, 'agent', 0, datetime('now'))", [$userId, explode('@', $email)[0], $email]);
            
            $success = 'Registration successful! You can now login.';
        }
    }
}

$displayError = $error ? '<div class="message error">' . htmlspecialchars($error) . '</div>' : '';
$displaySuccess = $success ? '<div class="message success">' . htmlspecialchars($success) . '</div>' : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar JellChat Pro — Platform Live Chat & AI Chatbot</title>
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
  gap:12px;
  margin-bottom:60px;
  position:relative;
  z-index:1;
}
.brand-logo img{
  width:40px;
  height:40px;
  object-fit:contain;
}
.brand-logo span{
  font-size:24px;
  font-weight:800;
  background:linear-gradient(135deg,#fff,#a5b4fc);
  -webkit-background-clip:text;
  -webkit-text-fill-color:transparent;
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
/* Phone Input */
.phone-wrapper{
  display:flex;
  gap:12px;
}
.country-code{
  width:100px;
  padding:14px;
  border:1.5px solid var(--border);
  border-radius:12px;
  font-size:14px;
  background:#fff;
  font-family:inherit;
}
.country-code:focus{
  outline:none;
  border-color:var(--blue);
}
.phone-wrapper .input-wrapper{
  flex:1;
}
/* Checkbox */
.checkbox-group{
  display:flex;
  align-items:flex-start;
  gap:12px;
  margin-bottom:24px;
}
.checkbox-group input{
  width:18px;
  height:18px;
  margin-top:2px;
  cursor:pointer;
  accent-color:var(--blue);
}
.checkbox-group label{
  font-size:13px;
  color:var(--muted);
  line-height:1.5;
  cursor:pointer;
}
.checkbox-group a{
  color:var(--blue);
  text-decoration:none;
  font-weight:500;
}
.checkbox-group a:hover{
  text-decoration:underline;
}
/* Submit Button */
.register-btn{
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
.register-btn:hover{
  background:var(--blue-dark);
  transform:translateY(-2px);
  box-shadow:0 8px 25px rgba(30,98,255,0.3);
}
/* Terms Text */
.terms-text{
  text-align:center;
  font-size:12px;
  color:var(--muted);
  margin-top:24px;
}
.terms-text a{
  color:var(--blue);
  text-decoration:none;
}
/* Error/Success Messages */
.message{
  padding:12px;
  border-radius:10px;
  margin-bottom:20px;
  font-size:13px;
  display:none;
}
.message.error{
  background:#fee2e2;
  color:var(--red);
  border:1px solid #fecaca;
  display:block;
}
.message.success{
  background:#d1fae5;
  color:var(--green);
  border:1px solid #a7f3d0;
  display:block;
}
/* Responsive */
@media(max-width:992px){
  .brand-side{
    display:none;
  }
  .form-side{
    padding:20px;
  }
  .form-container{
    padding:36px 28px;
  }
}
@media(max-width:480px){
  .form-container{
    padding:28px 20px;
  }
  .form-header h2{
    font-size:24px;
  }
  .social-buttons{
    flex-direction:column;
  }
  .phone-wrapper{
    flex-direction:column;
  }
  .country-code{
    width:100%;
  }
}
</style>
</head>
<body>
<div class="container">
  <!-- Left Branding Side -->
  <div class="brand-side">
    <div class="brand-logo">
      <img src="https://placehold.co/40x40/1e62ff/white?text=JC" alt="JellChat Pro">
      <span>JellChat Pro</span>
    </div>
    <div class="brand-content">
      <h1>Mulai <span>perjalanan</span><br>customer service Anda</h1>
      <p>Bergabunglah dengan ribuan bisnis yang telah meningkatkan layanan pelanggan mereka dengan JellChat Pro.</p>
      <ul class="feature-list">
        <li><i class="fa-solid fa-check"></i> Gratis 14 hari trial</li>
        <li><i class="fa-solid fa-check"></i> Setup dalam 5 menit</li>
        <li><i class="fa-solid fa-check"></i> Dukungan 24/7</li>
        <li><i class="fa-solid fa-check"></i> Tanpa kartu kredit</li>
      </ul>
    </div>
    <div class="brand-footer">
      <p>© 2025 JellChat Pro. All rights reserved.</p>
    </div>
  </div>

  <!-- Right Form Side -->
  <div class="form-side">
    <div class="form-container">
      <div class="form-header">
        <h2>Buat Akun Baru</h2>
        <p>Sudah punya akun? <a href="login.php">Masuk di sini</a></p>
      </div>

      <!-- Message Alert -->
      <?= $displayError ?>
      <?= $displaySuccess ?>

      <!-- Social Login -->
      <div class="social-buttons">
        <a href="#" class="social-btn google" onclick="socialSignup('google')">
          <i class="fa-brands fa-google"></i> Google
        </a>
        <a href="#" class="social-btn facebook" onclick="socialSignup('facebook')">
          <i class="fa-brands fa-facebook"></i> Facebook
        </a>
      </div>

      <div class="divider">
        <div class="divider-line"></div>
        <span class="divider-text">atau dengan email</span>
        <div class="divider-line"></div>
      </div>

      <!-- Register Form -->
      <form id="registerForm" method="post" action="signup.php" onsubmit="return validateForm(event)">
        <div class="form-group">
          <label>Email</label>
          <div class="input-wrapper">
            <i class="fa-solid fa-envelope"></i>
            <input type="email" id="email" name="email" placeholder="nama@perusahaan.com" required autocomplete="email">
          </div>
        </div>

        <div class="form-group">
          <label>Nomor Telepon</label>
          <div class="phone-wrapper">
            <select class="country-code" id="countryCode">
              <option value="+62">🇮🇩 +62 (Indonesia)</option>
              <option value="+60">🇲🇾 +60 (Malaysia)</option>
              <option value="+65">🇸🇬 +65 (Singapore)</option>
              <option value="+66">🇹🇭 +66 (Thailand)</option>
              <option value="+63">🇵🇭 +63 (Philippines)</option>
              <option value="+1">🇺🇸 +1 (US)</option>
              <option value="+44">🇬🇧 +44 (UK)</option>
              <option value="+81">🇯🇵 +81 (Japan)</option>
              <option value="+82">🇰🇷 +82 (Korea)</option>
              <option value="+86">🇨🇳 +86 (China)</option>
              <option value="+91">🇮🇳 +91 (India)</option>
            </select>
            <div class="input-wrapper">
              <i class="fa-solid fa-phone"></i>
              <input type="tel" id="phone" name="phone" placeholder="81234567890" required autocomplete="tel">
            </div>
          </div>
        </div>

        <div class="form-group">
          <label>Password</label>
          <div class="input-wrapper">
            <i class="fa-solid fa-lock"></i>
            <input type="password" id="password" name="password" placeholder="Buat password" required>
            <span class="toggle-password" onclick="togglePassword('password')">
              <i class="fa-regular fa-eye"></i>
            </span>
          </div>
        </div>

        <div class="form-group">
          <label>Konfirmasi Password</label>
          <div class="input-wrapper">
            <i class="fa-solid fa-lock"></i>
            <input type="password" id="confirmPassword" placeholder="Konfirmasi password" required>
            <span class="toggle-password" onclick="togglePassword('confirmPassword')">
              <i class="fa-regular fa-eye"></i>
            </span>
          </div>
        </div>

        <div class="checkbox-group">
          <input type="checkbox" id="terms" required>
          <label for="terms">
            Saya menyetujui <a href="#">Syarat & Ketentuan</a> dan <a href="#">Kebijakan Privasi</a>
          </label>
        </div>

        <button type="submit" class="register-btn">
          <i class="fa-solid fa-rocket"></i> Daftar Gratis
        </button>

        <p class="terms-text">
          Dengan mendaftar, Anda menyetujui bahwa kami dapat mengirimkan email terkait layanan.
        </p>
      </form>
    </div>
  </div>
</div>

<script>
// Toggle Password Visibility
function togglePassword(fieldId) {
  const field = document.getElementById(fieldId);
  const icon = field.parentElement.querySelector('.toggle-password i');
  if (field.type === 'password') {
    field.type = 'text';
    icon.classList.remove('fa-eye');
    icon.classList.add('fa-eye-slash');
  } else {
    field.type = 'password';
    icon.classList.remove('fa-eye-slash');
    icon.classList.add('fa-eye');
  }
}

// Format phone number (basic)
function formatPhoneNumber(value) {
  // Remove non-digits
  let cleaned = value.replace(/\D/g, '');
  // Remove leading 0 if present
  if (cleaned.startsWith('0')) {
    cleaned = cleaned.substring(1);
  }
  return cleaned;
}

// Show message
function showMessage(message, type) {
  const msgDiv = document.getElementById('message');
  msgDiv.textContent = message;
  msgDiv.className = `message ${type}`;
  setTimeout(() => {
    msgDiv.style.display = 'none';
    msgDiv.className = 'message';
  }, 5000);
}

// Validation functions
function validateEmail(email) {
  const re = /^[^\s@]+@([^\s@.,]+\.)+[^\s@.,]{2,}$/;
  return re.test(email);
}

function validatePhone(phone) {
  const re = /^[0-9]{8,15}$/;
  return re.test(phone);
}

function validatePassword(password) {
  // Minimal 8 karakter, minimal 1 huruf dan 1 angka
  const re = /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/;
  return re.test(password);
}

// Social signup simulation
function socialSignup(provider) {
  showMessage(`Redirecting ke ${provider} untuk verifikasi...`, 'success');
  setTimeout(() => {
    // Redirect ke halaman dashboard setelah "sukses"
    window.location.href = 'dashboard.php';
  }, 1500);
}

// Handle Register - Client-side validation before form submit
function validateForm(event) {
  // Get values
  const email = document.getElementById('email').value.trim();
  const countryCode = document.getElementById('countryCode').value;
  let phone = document.getElementById('phone').value.trim();
  const password = document.getElementById('password').value;
  const confirmPassword = document.getElementById('confirmPassword').value;
  const terms = document.getElementById('terms').checked;

  // Validate email
  if (!validateEmail(email)) {
    showMessage('Masukkan email yang valid (contoh: nama@perusahaan.com)', 'error');
    return false;
  }

  // Validate phone
  phone = formatPhoneNumber(phone);
  if (!validatePhone(phone)) {
    showMessage('Masukkan nomor telepon yang valid (8-15 digit angka)', 'error');
    return false;
  }

  // Validate password
  if (!validatePassword(password)) {
    showMessage('Password minimal 8 karakter dan mengandung huruf & angka', 'error');
    return false;
  }

  // Validate confirm password
  if (password !== confirmPassword) {
    showMessage('Konfirmasi password tidak sesuai', 'error');
    return false;
  }

  // Validate terms
  if (!terms) {
    showMessage('Anda harus menyetujui Syarat & Ketentuan', 'error');
    return false;
  }

  // Set full phone in hidden field before submit
  const fullPhone = countryCode + phone;
  let phoneInput = document.getElementById('phone');
  phoneInput.value = fullPhone;

  // Add confirm_password to form
  let confirmInput = document.getElementById('confirmInput');
  if (!confirmInput) {
    confirmInput = document.createElement('input');
    confirmInput.type = 'hidden';
    confirmInput.name = 'confirm_password';
    confirmInput.id = 'confirmInput';
    document.getElementById('registerForm').appendChild(confirmInput);
  }
  confirmInput.value = confirmPassword;

  // Show loading
  const submitBtn = document.querySelector('.register-btn');
  submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses...';
  submitBtn.disabled = true;

  return true;
}

// Real-time validation untuk password
document.addEventListener('DOMContentLoaded', function() {
  const passwordField = document.getElementById('password');
  const confirmField = document.getElementById('confirmPassword');
  
  // Password strength indicator (optional)
  passwordField.addEventListener('input', function() {
    const password = this.value;
    const strength = getPasswordStrength(password);
    updatePasswordStrength(strength);
  });
  
  confirmField.addEventListener('input', function() {
    const password = passwordField.value;
    const confirm = this.value;
    if (password === confirm && password !== '') {
      this.style.borderColor = 'var(--green)';
    } else {
      this.style.borderColor = 'var(--border)';
    }
  });
});

function getPasswordStrength(password) {
  if (password.length === 0) return 0;
  let strength = 0;
  if (password.length >= 8) strength++;
  if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
  if (password.match(/\d/)) strength++;
  if (password.match(/[^a-zA-Z\d]/)) strength++;
  return strength;
}

function updatePasswordStrength(strength) {
  const strengthColors = ['#ef4444', '#f59e0b', '#eab308', '#10b981'];
  const strengthTexts = ['Sangat Lemah', 'Lemah', 'Sedang', 'Kuat'];
  
  let indicator = document.querySelector('.password-strength');
  if (!indicator && strength > 0) {
    const wrapper = document.querySelector('#password').parentElement;
    indicator = document.createElement('div');
    indicator.className = 'password-strength';
    indicator.style.cssText = 'font-size:11px;margin-top:4px;transition:all 0.3s';
    wrapper.appendChild(indicator);
  }
  
  if (indicator && strength > 0) {
    indicator.textContent = strengthTexts[strength - 1];
    indicator.style.color = strengthColors[strength - 1];
  } else if (indicator && strength === 0) {
    indicator.remove();
  }
}

// Prevent form submission on Enter key accidentally
document.addEventListener('keypress', function(e) {
  if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
    e.preventDefault();
    document.getElementById('registerForm').requestSubmit();
  }
});
</script>
</body>
</html>
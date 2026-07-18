
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>CUSTOMER SERVICE — Live Chat</title>
<meta name="robots" content="noindex, nofollow">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

:root {
  --primary: #1a40ff;
  --font: 'Plus Jakarta Sans', sans-serif;
}

body {
  font-family: var(--font);
  background: #fff;
  height: 100vh;
  display: flex;
  overflow: hidden;
}

/* ── LAYOUT ──────────────────────────────────────────── */
.wrapper { display:flex; width:100%; height:100%; }

/* Sisi Kiri */
.side-info {
  flex: 1;
  padding: 80px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  background: #fff;
  position: relative;
}

.logo-brand {
  position: absolute;
  top: 28px;
  left: 40px;
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 700;
  font-size: 16px;
  color: #ff4e22;
}

.side-info h1 {
  font-size: 64px;
  font-weight: 800;
  color: #111;
  line-height: 1.1;
  margin-bottom: 24px;
}

.side-info p {
  font-size: 20px;
  color: #555;
  line-height: 1.5;
  max-width: 460px;
}

/* Sisi Kanan */
.side-chat {
  flex: 1;
  background: linear-gradient(135deg, #f0f4ff 0%, #e0c3fc 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px;
}

/* ── CHAT BOX ─────────────────────────────────────────── */
.chat-box {
  width: 100%;
  max-width: 420px;
  background: #fff;
  border-radius: 24px;
  box-shadow: 0 10px 40px rgba(0,0,0,0.08);
  height: 85vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  position: relative;
}

/* ── HEADER ──────────────────────────────────────────── */
.chat-header {
  padding: 16px 20px;
  display: flex;
  align-items: center;
  gap: 10px;
  border-bottom: 1px solid #f1f5f9;
  flex-shrink: 0;
}

.btn-icon {
  cursor: pointer;
  border: none;
  background: #f1f5f9;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  color: #64748b;
  flex-shrink: 0;
}

.agent-card {
  flex: 1;
  background: #f8fafc;
  border-radius: 40px;
  padding: 6px 14px 6px 8px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.agent-av {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: var(--primary);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 16px;
  overflow: hidden;
  flex-shrink: 0;
}
.agent-av img { width:100%; height:100%; object-fit:cover; }

.agent-name { font-weight: 700; font-size: 13px; color: #111; }
.agent-sub  { font-size: 11px; color: #888; }

.header-end-btn {
  border: none;
  background: none;
  color: #ef4444;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 8px;
  display: none;
  flex-shrink: 0;
}
.header-end-btn:hover { background: #fef2f2; }

/* ── SCREENS ──────────────────────────────────────────── */
.screen { display:none; flex-direction:column; flex:1; overflow:hidden; padding:20px; }
.screen.active { display:flex; }

/* ── FORM SCREEN ─────────────────────────────────────── */
.welcome-msg {
  background: #f8fafc;
  border-radius: 16px;
  padding: 14px 16px;
  font-size: 13px;
  line-height: 1.6;
  color: #475569;
  margin-bottom: 20px;
}

.fi { margin-bottom: 14px; }
.fi label { display:block; font-size:12px; font-weight:600; margin-bottom:6px; color:#374151; }

.fi input {
  width: 100%;
  padding: 11px 14px;
  border: 1.5px solid #e2e8f0;
  background: #f8fafc;
  border-radius: 12px;
  font-family: inherit;
  font-size: 13px;
  outline: none;
  transition: border-color .2s;
}
.fi input:focus { border-color: var(--primary); background: #fff; }

.wa-wrap { display:flex; align-items:center; border:1.5px solid #e2e8f0; background:#f8fafc; border-radius:12px; overflow:hidden; }
.wa-wrap:focus-within { border-color: var(--primary); background:#fff; }
.wa-prefix { padding:11px 10px 11px 14px; font-size:13px; color:#64748b; font-weight:600; white-space:nowrap; }
.wa-wrap input { border:none; background:transparent; padding:11px 14px 11px 0; flex:1; font-family:inherit; font-size:13px; outline:none; }

.form-err { font-size:12px; color:#ef4444; margin-top:8px; display:none; }
.form-err.show { display:block; }

.btn-start {
  width: 100%;
  background: var(--primary);
  color: #fff;
  border: none;
  padding: 13px;
  border-radius: 12px;
  font-family: inherit;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  margin-top: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: opacity .2s;
}
.btn-start:disabled { opacity: .6; cursor: not-allowed; }

/* ── CHAT SCREEN ─────────────────────────────────────── */
.waiting-bar {
  background: #fef9c3;
  border-radius: 10px;
  padding: 9px 12px;
  font-size: 12px;
  color: #92400e;
  display: none;
  align-items: center;
  gap: 8px;
  margin-bottom: 10px;
  flex-shrink: 0;
}

.msgs {
  flex: 1;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-right: 4px;
}
.msgs::-webkit-scrollbar { width: 4px; }
.msgs::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }

.msg { max-width: 85%; display:flex; flex-direction:column; }
.msg.v { align-self:flex-end; }
.msg.a { align-self:flex-start; }
.msg.s { align-self:center; }

.bub {
  padding: 9px 13px;
  border-radius: 14px;
  font-size: 13px;
  line-height: 1.5;
}
.msg.v .bub { background: var(--primary); color:#fff; border-radius:14px 14px 4px 14px; }
.msg.a .bub { background: #f1f5f9; color:#1e293b; border-radius:14px 14px 14px 4px; }
.msg.s .bub { background: #f0fdf4; color:#166534; font-size:11px; padding:5px 12px; border-radius:20px; }

.msg-t { font-size:10px; color:#94a3b8; margin-top:3px; padding:0 2px; }
.msg.v .msg-t { text-align:right; }

.msg-img { max-width:100%; border-radius:10px; cursor:pointer; display:block; max-height:200px; object-fit:cover; }
.msg-file { display:inline-flex; align-items:center; gap:6px; font-size:12px; color:var(--primary); text-decoration:none; }

.typing-bar { font-size:11px; color:#94a3b8; min-height:16px; margin-top:4px; padding:2px 0; flex-shrink:0; }

.upload-bar {
  background: #eff6ff;
  border-radius: 8px;
  padding: 8px 12px;
  font-size:12px;
  color: var(--primary);
  display:none;
  margin-bottom:6px;
  flex-shrink:0;
}

.input-area {
  display: flex;
  align-items: flex-end;
  gap: 8px;
  border-top: 1px solid #f1f5f9;
  padding-top: 12px;
  flex-shrink: 0;
  margin-top: 8px;
}

.inp-attach-label {
  cursor: pointer;
  color: #94a3b8;
  display: flex;
  align-items: center;
  padding: 4px;
  border-radius: 8px;
}
.inp-attach-label:hover { color: var(--primary); }
.inp-attach-label input { display:none; }

textarea#inp-txt {
  flex: 1;
  border: none;
  outline: none;
  font-family: inherit;
  font-size: 13px;
  resize: none;
  line-height: 1.5;
  max-height: 100px;
  padding: 4px 0;
  background: transparent;
  color: #1e293b;
}

.inp-send {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: var(--primary);
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

/* ── CLOSED BAR ──────────────────────────────────────── */
.closed-bar {
  background: #fef2f2;
  border-radius: 12px;
  padding: 12px 16px;
  text-align: center;
  font-size: 13px;
  color: #991b1b;
  display: none;
  margin-top: 8px;
  flex-shrink: 0;
}

.rating-box {
  padding: 12px;
  text-align: center;
  border-top: 1px solid #f1f5f9;
  flex-shrink: 0;
  display: none;
}
.rating-box p { font-size: 12px; color: #64748b; margin-bottom: 8px; }
#stars button {
  background: none;
  border: none;
  font-size: 22px;
  cursor: pointer;
  color: #e2e8f0;
  transition: color .15s;
}
#stars button.on { color: #f59e0b; }

/* ── FOOTER ──────────────────────────────────────────── */
.footer-brand {
  text-align: center;
  padding: 10px;
  font-size: 10px;
  color: #94a3b8;
  flex-shrink: 0;
  border-top: 1px solid #f8fafc;
}

/* ── SPINNER ──────────────────────────────────────────── */
@keyframes spin { to { transform: rotate(360deg); } }
.spin {
  display: inline-block;
  width: 14px; height: 14px;
  border: 2px solid rgba(255,255,255,.4);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin .7s linear infinite;
}

/* ── MOBILE ───────────────────────────────────────────── */
@media (max-width: 900px) {
  .side-info { display: none; }
  .side-chat { padding: 0; }
  .chat-box { height: 100vh; max-width: 100%; border-radius: 0; box-shadow: none; }
}
</style>
</head>
<body>
<div class="wrapper">
  <div class="side-info">
    <div class="logo-brand">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="#ff4e22"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      LiveChat
    </div>
    <h1>Halo! 👋</h1>
    <p>Selamat datang di halaman obrolan kami.Butuh bantuan?</p>
  </div>
  <!-- KANAN: Chat Box -->
  <div class="side-chat">
    <div class="chat-box">
      <!-- HEADER -->
      <div class="chat-header">
        <button class="btn-icon" title="Kembali">←</button>
        <div class="agent-card">
      <div class="agent-av" id="agav">
            <img src="https://pro.golivechat.site/uploads/avatars/av_17_1a00e77f.jpg" alt="Agent Avatar">
    </div>
          <div>
              
            <div class="agent-name" id="ag-name">CUSTOMER SERVICE</div>
            <div class="agent-sub" id="ag-status">Selamat datang di halaman obrolan kami.Butuh bantuan?</div>
          </div>
        </div>
        <button class="header-end-btn" id="btn-end" onclick="endChat()">✕ Akhiri</button>
      </div>
      <!-- FORM -->
      <div class="screen active" id="screen-form">
        <div class="welcome-msg">Hai! 👋 Bagaimana kami dapat membantu Anda hari ini?</div>
        <div class="fi">
          <label>Nama</label>
          <input type="text" id="f-name" placeholder="Nama lengkap Anda" autocomplete="name">
        </div>
        <div class="fi">
          <label>WhatsApp (tanpa awalan 0)</label>
          <div class="wa-wrap">
            <span class="wa-prefix">+62</span>
            <input type="tel" id="f-wa" placeholder="8123456789" inputmode="numeric">
          </div>
        </div>
        <div class="form-err" id="form-err"></div>
        <button class="btn-start" id="btn-start" onclick="startChat()">Mulai Obrolan</button>
      </div>

      <!-- CHAT -->
      <div class="screen" id="screen-chat">
        <div class="waiting-bar" id="waiting-bar">
         Menunggu agen bergabung...
        </div>
        <div class="msgs" id="msgs"></div>
        <div class="typing-bar" id="typing-bar"></div>
        <div class="upload-bar" id="upload-bar">Mengunggah file...</div>
        <div class="input-area" id="input-area">
          <label class="inp-attach-label" title="Lampirkan file">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
            <input type="file" onchange="uploadFile(this)" accept="image/*,.pdf,.doc,.docx,.zip">
          </label>
          <textarea id="inp-txt" rows="1" placeholder="Ketik pesan…" onkeydown="handleKey(event)" oninput="autoGrow(this)"></textarea>
          <button class="inp-send" onclick="sendMsg()" title="Kirim">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="white"><path d="M2 21l21-9L2 3v7l15 2-15 2v7z"/></svg>
          </button>
        </div>
        <div class="closed-bar" id="closed-bar">Chat telah berakhir. Terima kasih! 🙏</div>
        <div class="rating-box" id="rating-box">
          <p>Beri penilaian untuk percakapan ini:</p>
          <div id="stars">
                        <button data-s="1" onclick="rateChat(1)">★</button>
                        <button data-s="2" onclick="rateChat(2)">★</button>
                        <button data-s="3" onclick="rateChat(3)">★</button>
                        <button data-s="4" onclick="rateChat(4)">★</button>
                        <button data-s="5" onclick="rateChat(5)">★</button>
                      </div>
        </div>
      </div>

      <div class="footer-brand">Powered by <strong>LiveChat Pro</strong></div>

    </div><!-- /chat-box -->
  </div><!-- /side-chat -->

</div><!-- /wrapper -->

<script>
(function(){
'use strict';

var API        = "https:\/\/pro.golivechat.site\/visitor\/api";
var showRating = true;
var soundOn    = true;

// ── State ─────────────────────────────────────────────
var LS_KEY = 'lcdc_session_v1';
var S = {
  token  : lsGet('token'),
  lastId : lsGet('lastId') || 0,
  ended  : lsGet('ended') === true,
  sid    : lsGet('sid') || ('lcsdc_' + Math.random().toString(36).substr(2,12)),
  poll   : null,
};
lsSet('sid', S.sid);

// ── localStorage ───────────────────────────────────────
function lsGet(k){
  try{ var d=JSON.parse(localStorage.getItem(LS_KEY)||'{}'); return d[k]!=null?d[k]:null; }catch(e){return null;}
}
function lsSet(k,v){
  try{ var d=JSON.parse(localStorage.getItem(LS_KEY)||'{}'); d[k]=v; localStorage.setItem(LS_KEY,JSON.stringify(d)); }catch(e){}
}
function lsDel(k){
  try{ var d=JSON.parse(localStorage.getItem(LS_KEY)||'{}'); delete d[k]; localStorage.setItem(LS_KEY,JSON.stringify(d)); }catch(e){}
}
function lsClear(){
  try{ localStorage.removeItem(LS_KEY); }catch(e){}
}

// ── Audio ──────────────────────────────────────────────
var audioCtx = null;
function beep(){
  if(!soundOn) return;
  try{
    if(!audioCtx) audioCtx = new(window.AudioContext||window.webkitAudioContext)();
    var ctx=audioCtx, t=ctx.currentTime;
    [[880,.18,.15],[1047,.22,.12]].forEach(function(n,i){
      var o=ctx.createOscillator(), g=ctx.createGain();
      o.connect(g); g.connect(ctx.destination);
      o.frequency.value=n[0]; o.type='sine';
      var st=t+(i===0?0:.18);
      g.gain.setValueAtTime(n[2],st);
      g.gain.exponentialRampToValueAtTime(.001,st+n[1]);
      o.start(st); o.stop(st+n[1]+.05);
    });
  }catch(e){}
}

// ── Helpers ────────────────────────────────────────────
function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function $(id){ return document.getElementById(id); }
function show(id){ var e=$(id); if(e) e.style.display=''; }
function hide(id){ var e=$(id); if(e) e.style.display='none'; }
function text(id,t){ var e=$(id); if(e) e.textContent=t; }

function showScreen(name){
  document.querySelectorAll('.screen').forEach(function(el){ el.classList.remove('active'); });
  var s=$('screen-'+name);
  if(s) s.classList.add('active');
  var endBtn=$('btn-end');
  if(endBtn) endBtn.style.display = (name==='chat' && !S.ended) ? 'block' : 'none';
}

function autoGrow(el){
  el.style.height='auto';
  el.style.height=Math.min(el.scrollHeight,100)+'px';
}

// ── Append Message ─────────────────────────────────────
function appendMsg(area, m){
  if(!area) return;
  var div=document.createElement('div');
  div.className='msg '+(m.sender_type==='agent'?'a':m.sender_type==='visitor'?'v':'s');
  if(m.sender_type==='system'){
    div.innerHTML='<div class="bub">'+esc(m.body)+'</div>';
  } else if(m.msg_type==='image' && m.attachment_url){
    div.innerHTML='<div><img src="'+esc(m.attachment_url)+'" class="msg-img" onclick="window.open(this.src,\'_blank\')" alt=""><div class="msg-t">'+esc(m.t||'')+'</div></div>';
  } else if(m.msg_type==='file' && m.attachment_url){
    div.innerHTML='<div><a href="'+esc(m.attachment_url)+'" target="_blank" class="msg-file">📎 '+esc(m.attachment_name||'file')+'</a><div class="msg-t">'+esc(m.t||'')+'</div></div>';
  } else {
    div.innerHTML='<div><div class="bub">'+esc(m.body||'').replace(/\n/g,'<br>')+'</div><div class="msg-t">'+esc(m.t||'')+'</div></div>';
  }
  area.appendChild(div);
}

// ── Update Agent Bar ────────────────────────────────────
function updateAgentBar(agent){
  var av = $('agav');
  if(av){
    // Jika agent.avatar_url adalah link (lebih dari 2 karakter)
    if(agent.avatar_url && agent.avatar_url.length > 2){
      av.innerHTML = '<img src="'+esc(agent.avatar_url)+'" alt="">';
    } else {
      // Jika hanya inisial
      var initial = agent.avatar_url || (agent.name ? agent.name[0] : 'A');
      av.innerHTML = '<span class="av-initial">'+esc(initial.toUpperCase())+'</span>';
    }
  }
  text('ag-name', agent.name || 'Support');
  text('ag-status', agent.status === 'online' ? '🟢 Online' : '🟡 Away');
}

// ── Show Closed ─────────────────────────────────────────
function showClosed(){
  hide('input-area');
  hide('waiting-bar');
  var cb=$('closed-bar');
  if(cb) cb.style.display='block';
  var endBtn=$('btn-end');
  if(endBtn) endBtn.style.display='none';
  if(showRating){ $('rating-box').style.display='block'; }
}

// ── Init ────────────────────────────────────────────────
async function init(){
  try{
    var r=await fetch(API+'?action=config');
    var cfg=await r.json();
    var online=parseInt(cfg.agents_online)||0;
    if(online>0){
      text('ag-status','🟢 Online · '+online+' agen');
    } else {
      text('ag-status','🔴 Sedang offline');
    }
  }catch(e){}

  trackVisitor();

  if(S.token && !S.ended){
    await restoreSession();
  } else if(S.ended && S.token){
    lsClear(); S.token=null; S.ended=false; S.lastId=0;
  }

  var nameEl=$('f-name'), waEl=$('f-wa');
  if(nameEl && lsGet('visitor_name')) nameEl.value=lsGet('visitor_name');
  if(waEl && lsGet('visitor_wa')) waEl.value=lsGet('visitor_wa');
}

// ── Track Visitor ───────────────────────────────────────
async function trackVisitor(){
  try{
    var f=new FormData();
    f.append('action','track');
    f.append('session_id',S.sid);
    f.append('page_url',location.href);
    f.append('referrer',document.referrer||'');
    await fetch(API,{method:'POST',body:f});
  }catch(e){}
}

// ── Restore Session ─────────────────────────────────────
async function restoreSession(){
  try{
    var r=await fetch(API+'?action=restore&token='+encodeURIComponent(S.token));
    var d=await r.json();
    if(!d.ok){ lsClear(); S.token=null; return; }
    showScreen('chat');
    var area=$('msgs');
    (d.messages||[]).forEach(function(m){ appendMsg(area,m); });
    S.lastId=d.last_id||0; lsSet('lastId',S.lastId);
    if(area) area.scrollTop=area.scrollHeight;
    if(d.agent) updateAgentBar(d.agent);
    var wb=$('waiting-bar');
    if(d.status==='waiting'){ if(wb) wb.style.display='flex'; }
    else { hide('waiting-bar'); }
    if(d.status==='closed'){
      S.ended=true; lsSet('ended',true); showClosed();
    } else {
      S.poll=setInterval(doPoll,2000);
    }
  }catch(e){ lsClear(); S.token=null; }
}

// ── Start Chat ──────────────────────────────────────────
async function startChat(){
  var name=($('f-name').value||'').trim();
  var wa=($('f-wa').value||'').trim();
  var errEl=$('form-err');
  errEl.classList.remove('show'); errEl.textContent='';

  if(!name){ errEl.textContent='Nama wajib diisi.'; errEl.classList.add('show'); $('f-name').focus(); return; }
  if(!wa){ errEl.textContent='Nomor WhatsApp wajib diisi.'; errEl.classList.add('show'); $('f-wa').focus(); return; }
  if(!/^[0-9]{8,15}$/.test(wa)){ errEl.textContent='Nomor tidak valid (8-15 digit, tanpa awalan 0).'; errEl.classList.add('show'); return; }

  var btn=$('btn-start');
  btn.innerHTML='<span class="spin"></span> Menghubungkan...';
  btn.disabled=true;

  try{
    var f=new FormData();
    f.append('action','start');
    f.append('name',name);
    f.append('whatsapp','+62'+wa);
    f.append('session_id',S.sid);
    var r=await fetch(API,{method:'POST',body:f});
    var d=await r.json();
    if(d.token){
      S.token=d.token; S.ended=false; S.lastId=0;
      lsSet('token',d.token); lsSet('visitor_name',name); lsSet('visitor_wa',wa); lsDel('ended');
      showScreen('chat');
      var wb=$('waiting-bar');
      if(d.status==='waiting' && wb) wb.style.display='flex';
      await doPoll();
      S.poll=setInterval(doPoll,2000);
    } else {
      errEl.textContent=d.error||'Gagal memulai chat.';
      errEl.classList.add('show');
      btn.innerHTML='Mulai Obrolan';
      btn.disabled=false;
    }
  }catch(ex){
    errEl.textContent='Error jaringan. Periksa koneksi Anda.';
    errEl.classList.add('show');
    btn.innerHTML='Mulai Obrolan';
    btn.disabled=false;
  }
}
window.startChat=startChat;

// ── Poll ────────────────────────────────────────────────
async function doPoll(){
  if(!S.token) return;
  try{
    var r=await fetch(API+'?action=poll&token='+encodeURIComponent(S.token)+'&last_id='+S.lastId);
    var d=await r.json();
    var area=$('msgs');
    if(!area) return;
    var atBot=area.scrollHeight-area.scrollTop<=area.clientHeight+80;
    var hasNew=false;
    (d.messages||[]).forEach(function(m){
      S.lastId=m.id; lsSet('lastId',m.id);
      hasNew=true; appendMsg(area,m);
      if(m.sender_type==='agent') beep();
    });
    if(hasNew && atBot) area.scrollTop=area.scrollHeight;
    if(d.agent) updateAgentBar(d.agent);
    if(d.status==='active') hide('waiting-bar');
    var tb=$('typing-bar');
    if(tb) tb.textContent=d.typing?d.typing+' sedang mengetik...':'';
    if(d.status==='closed' && !S.ended){
      S.ended=true; lsSet('ended',true);
      clearInterval(S.poll);
      showClosed();
    }
  }catch(e){}
}

// ── Send ────────────────────────────────────────────────
async function sendMsg(){
  var inp=$('inp-txt');
  var msg=(inp?inp.value:'').trim();
  if(!msg||!S.token||S.ended) return;
  inp.value=''; inp.style.height='auto';
  var f=new FormData();
  f.append('action','send'); f.append('token',S.token); f.append('message',msg);
  await fetch(API,{method:'POST',body:f});
  await doPoll();
}
window.sendMsg=sendMsg;

function handleKey(e){
  if(e.key==='Enter'&&!e.shiftKey){ e.preventDefault(); sendMsg(); return; }
  autoGrow(e.target);
  if(S.token&&!S.ended) sendTyping();
}
window.handleKey=handleKey;
window.autoGrow=autoGrow;

function sendTyping(){
  if(!S.token||S.ended) return;
  var f=new FormData();
  f.append('action','typing'); f.append('token',S.token);
  fetch(API,{method:'POST',body:f}).catch(function(){});
}

// ── Upload ──────────────────────────────────────────────
async function uploadFile(inp){
  var file=inp.files[0];
  if(!file||!S.token||S.ended) return;
  $('upload-bar').style.display='block';
  var f=new FormData();
  f.append('action','upload'); f.append('token',S.token); f.append('file',file);
  try{
    var r=await fetch(API,{method:'POST',body:f});
    var d=await r.json();
    if(d.ok) await doPoll();
    else alert(d.error||'Upload gagal');
  }catch(e){ alert('Error upload'); }
  $('upload-bar').style.display='none';
  inp.value='';
}
window.uploadFile=uploadFile;

// ── End Chat ────────────────────────────────────────────
async function endChat(){
  if(!S.token) return;
  if(!confirm('Akhiri chat ini?')) return;
  var f=new FormData();
  f.append('action','close'); f.append('token',S.token);
  await fetch(API,{method:'POST',body:f});
  S.ended=true; clearInterval(S.poll);
  lsClear(); S.token=null; S.lastId=0;
  showClosed();
  setTimeout(function(){
    lsClear(); S.token=null; S.ended=false; S.lastId=0;
    $('msgs').innerHTML='';
    $('closed-bar').style.display='none';
    $('rating-box').style.display='none';
    show('input-area');
    hide('waiting-bar');
    showScreen('form');
  }, 3000);
}
window.endChat=endChat;

// ── Rate ────────────────────────────────────────────────
async function rateChat(stars){
  if(!S.token) return;
  document.querySelectorAll('#stars button').forEach(function(b){
    b.classList.toggle('on', parseInt(b.dataset.s)<=stars);
  });
  var f=new FormData();
  f.append('action','rate'); f.append('token',S.token); f.append('rating',stars);
  await fetch(API,{method:'POST',body:f});
  setTimeout(function(){
    $('rating-box').innerHTML='<div style="font-size:13px;padding:8px">Terima kasih atas penilaian Anda! 🙏</div>';
  },400);
}
window.rateChat=rateChat;

// ── Boot ────────────────────────────────────────────────
init();
})();
</script>
<script defer src="https://static.cloudflareinsights.com/beacon.min.js/v833ccba57c9e4d2798f2e76cebdd09a11778172276447" integrity="sha512-57MDmcccJXYtNnH+ZiBwzC4jb2rvgVCEokYN+L/nLlmO8rfYT/gIpW2A569iJ/3b+0UEasghjuZH/ma3wIs/EQ==" data-cf-beacon='{"version":"2024.11.0","token":"9df6b056563c4d82b7623e89b470c76c","r":1,"server_timing":{"name":{"cfCacheStatus":true,"cfEdge":true,"cfExtPri":true,"cfL4":true,"cfOrigin":true,"cfSpeedBrain":true},"location_startswith":null}}' crossorigin="anonymous"></script>
</body>
</html>
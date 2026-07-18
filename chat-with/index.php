<?php
$licenseKey = $_GET['license'] ?? '';
if (!$licenseKey) {
    http_response_code(400);
    die('Missing license key');
}

$apiBase = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . "://$_SERVER[HTTP_HOST]";
?>
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

.wrapper { display:flex; width:100%; height:100%; }

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
  color: var(--primary);
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

.side-chat {
  flex: 1;
  background: linear-gradient(135deg, #f0f4ff 0%, #e0c3fc 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px;
}

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

.screen { display:none; flex-direction:column; flex:1; overflow:hidden; padding:20px; }
.screen.active { display:flex; }

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

.typing-bar { font-size:11px; color:#94a3b8; min-height:16px; margin-top:4px; padding:2px 0; flex-shrink:0; display:none; }

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
  display: none;
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

.inp-send:disabled { opacity: .5; cursor: not-allowed; }

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

.footer-brand {
  text-align: center;
  padding: 10px;
  font-size: 10px;
  color: #94a3b8;
  flex-shrink: 0;
  border-top: 1px solid #f8fafc;
}

@keyframes spin { to { transform: rotate(360deg); } }
.spin {
  display: inline-block;
  width: 14px; height: 14px;
  border: 2px solid rgba(255,255,255,.4);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin .7s linear infinite;
}

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
      <svg width="22" height="22" viewBox="0 0 24 24" fill="var(--primary)"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      LiveChat
    </div>
    <h1 id="sideTitle">Halo! 👋</h1>
    <p id="sideText">Selamat datang di halaman obrolan kami. Ada yang bisa kami bantu?</p>
  </div>
  <div class="side-chat">
    <div class="chat-box">
      <div class="chat-header">
        <button class="btn-icon" style="visibility:hidden"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5m7-7l-7 7 7 7"/></svg></button>
        <div class="agent-card">
          <div class="agent-av" id="agav">S</div>
          <div>
            <div class="agent-name" id="ag-name">Support</div>
            <div class="agent-sub" id="ag-status">Online</div>
          </div>
        </div>
        <button class="header-end-btn" id="btn-end" onclick="endChat()"><svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:3px"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>Akhiri</button>
      </div>

      <div class="screen active" id="screen-form">
        <div class="welcome-msg" id="welcomeMsg">Hai! 👋 Ada yang bisa kami bantu?</div>
        <div class="fi">
          <label>Nama</label>
          <input type="text" id="f-name" placeholder="Nama lengkap Anda" autocomplete="name" value="Guest">
        </div>
        <div class="fi">
          <label>WhatsApp (opsional)</label>
          <input type="tel" id="f-wa" placeholder="8123456789" inputmode="numeric">
        </div>
        <div class="form-err" id="form-err"></div>
        <button class="btn-start" id="btn-start" onclick="startChat()">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="white"><path d="M2 21l21-9L2 3v7l15 2-15 2v7z"/></svg>
          Mulai Obrolan
        </button>
      </div>

      <div class="screen" id="screen-chat">
        <div class="waiting-bar" id="waiting-bar"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 13.59L10.5 9.5V5h2v3.59L18 15.59z"/></svg> Menunggu agen bergabung...</div>
        <div class="msgs" id="msgs"></div>
        <div class="typing-bar" id="typing-bar"></div>
        <div class="upload-bar" id="upload-bar"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15v-6H9l4-4 4 4h-2v6h-2z"/></svg> Mengunggah file...</div>
        <div class="input-area" id="input-area">
          <label class="inp-attach-label" title="Lampirkan file">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
            <input type="file" onchange="uploadFile(this)" accept="image/*,.pdf,.doc,.docx,.zip">
          </label>
          <textarea id="inp-txt" rows="1" placeholder="Ketik pesan…" onkeydown="handleKey(event)" oninput="autoGrow(this)"></textarea>
          <button class="inp-send" onclick="sendMsg()" title="Kirim" id="inp-send-btn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="white"><path d="M2 21l21-9L2 3v7l15 2-15 2v7z"/></svg>
          </button>
        </div>
        <div class="closed-bar" id="closed-bar"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg> Chat telah berakhir. Terima kasih!</div>
      </div>

      <div class="footer-brand">Powered by <strong>LiveChat</strong></div>
    </div>
  </div>
</div>

<script>
(function(){
'use strict';

var apiBase = '<?= $apiBase ?>';
var licenseKey = '<?= htmlspecialchars($licenseKey, ENT_QUOTES) ?>';
var config = {};
var S = {
  token: null,
  lastId: 0,
  ended: false,
  sessId: 'dc_' + Date.now() + '_' + Math.random().toString(36).substr(2,9),
  poll: null,
  isSending: false
};

var LS_KEY = 'dc_sess_' + licenseKey;
try { var saved = JSON.parse(localStorage.getItem(LS_KEY) || '{}'); if(saved.token) { S.token = saved.token; S.lastId = saved.lastId || 0; S.ended = saved.ended || false; } } catch(e){}

function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function $(id){ return document.getElementById(id); }
function show(id, tp){ var e=$(id); if(e) e.style.display=tp||'block'; }
function hide(id){ var e=$(id); if(e) e.style.display='none'; }

function saveState(){
  try{ localStorage.setItem(LS_KEY, JSON.stringify({token:S.token,lastId:S.lastId,ended:S.ended})); }catch(e){}
}
function clearState(){
  try{ localStorage.removeItem(LS_KEY); }catch(e){}
}

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

function fmtTime(iso){
  try{ return new Date(iso).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'}); }catch(e){return ''}
}

function appendMsg(type,content,time,fileUrl,id){
  var area=$('msgs');
  if(!area) return;
  if(id && area.querySelector('[data-id="'+id+'"]')) return;

  var div=document.createElement('div');
  div.className='msg '+(type==='visitor'?'v':(type==='system'?'s':'a'));
  if(id) div.setAttribute('data-id',id);

  var inner=document.createElement('div');
  if(fileUrl){
    var fs=fileUrl.startsWith('/')?apiBase+fileUrl:apiBase+'/'+fileUrl;
    if(fileUrl.match(/\.(jpeg|jpg|gif|png|webp)$/i)){
      inner.innerHTML='<img src="'+esc(fs)+'" class="msg-img" onclick="window.open(this.src,\'_blank\')" alt="">';
    } else {
      inner.innerHTML='<a href="'+esc(fs)+'" target="_blank" class="msg-file">📎 File</a>';
    }
  } else if(content){
    inner.innerHTML='<div class="bub">'+esc(content).replace(/\n/g,'<br>').replace(/\*([^*]+)\*/g,'<strong>$1</strong>')+'</div>';
  }
  var t=document.createElement('div');t.className='msg-t';t.textContent=fmtTime(time);
  inner.appendChild(t);div.appendChild(inner);area.appendChild(div);
  area.scrollTop=area.scrollHeight;
}

function appendSystem(msg){
  appendMsg('system',msg,new Date().toISOString(),null,Date.now()+'_sys');
}

function updateAgentBar(name,status,avatar){
  if(avatar && avatar.length>2){
    $('agav').innerHTML='<img src="'+esc(avatar)+'" alt="">';
  } else {
    $('agav').innerHTML=esc(name?name.charAt(0).toUpperCase():'S');
  }
  $('ag-name').textContent=name||'Support';
  $('ag-status').textContent=status&&status==='online'?'🟢 Online':(status==='away'?'🟡 Away':'🔴 Offline');
  if($('sideTitle')) $('sideTitle').textContent='Chat dengan '+(name||'Support');
}

// ── Init ──
function init(){
  loadConfig();
}

function loadConfig(){
  fetch(apiBase+'/api/widget-config?license_key='+encodeURIComponent(licenseKey)+'&_v='+Date.now(),{cache:'no-store'})
    .then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json()})
    .then(function(d){
      if(d.success){
        config=d.config||{};
        applyConfig();
        if(S.token && !S.ended){
          restoreSession();
        } else if(S.ended && S.token){
          clearState(); S.token=null; S.ended=false; S.lastId=0;
        } else if(config.pre_chat_form!=1){
          autoStartChat();
        }
      } else {
        console.error('Config error:',d.message||d.error);
      }
    })
    .catch(function(e){console.error('Failed to load config:',e)});
}

function applyConfig(){
  var color=config.primary_color||'#1a40ff';
  document.documentElement.style.setProperty('--primary',color);
  var welcomeMsg=config.welcome_msg||config.welcome_message||'Hai! Ada yang bisa kami bantu?';
  $('welcomeMsg').textContent=welcomeMsg;
  updateAgentBar(config.agent_name||'Support',config.is_online==1?'online':'away',config.agent_avatar||'');
}

function autoStartChat(){
  doStartChat('Guest','','Chat');
}

function startChat(){
  var name=($('f-name').value||'').trim();
  var wa=($('f-wa').value||'').trim();
  var errEl=$('form-err');
  errEl.classList.remove('show'); errEl.textContent='';

  if(!name && config.pre_chat_form==1){ errEl.textContent='Nama wajib diisi.'; errEl.classList.add('show'); $('f-name').focus(); return; }
  doStartChat(name||'Guest',wa,'Chat');
}

function doStartChat(name,phone,subject){
  var btn=$('btn-start');
  if(btn){ btn.innerHTML='<span class="spin"></span> Menghubungkan...'; btn.disabled=true; }

  var url=apiBase+'/api/widget/chat?session_id='+encodeURIComponent(S.sessId)+'&license_key='+encodeURIComponent(licenseKey)+'&name='+encodeURIComponent(name)+'&phone='+encodeURIComponent(phone)+'&subject='+encodeURIComponent(subject);
  fetch(url)
    .then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json()})
    .then(function(d){
      if(d.success){
        S.token=d.conversation_id; S.ended=false; S.lastId=0;
        saveState();
        showScreen('chat');
        show('input-area','flex');
        if(d.messages&&d.messages.length){
          d.messages.forEach(function(m){appendMsg(m.sender_type,m.content,m.created_at,m.content_type==='file'?m.file_url:null,m.id)});
          S.lastId=d.messages[d.messages.length-1].id||0; saveState();
        }
        if($('waiting-bar')) $('waiting-bar').style.display='flex';
        doPoll();
        S.poll=setInterval(doPoll,3000);
      } else {
        if(btn){ btn.innerHTML='Mulai Obrolan'; btn.disabled=false; }
        var errEl=$('form-err');
        if(errEl){ errEl.textContent=d.error||'Gagal memulai chat'; errEl.classList.add('show'); }
      }
    })
    .catch(function(err){
      if(btn){ btn.innerHTML='Mulai Obrolan'; btn.disabled=false; }
      var errEl=$('form-err');
      if(errEl){ errEl.textContent='Error: '+err.message; errEl.classList.add('show'); }
    });
}

function restoreSession(){
  if(!S.token) return;
  fetch(apiBase+'/api/widget/poll-messages?conversation_id='+S.token+'&since_id=0')
    .then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json()})
    .then(function(data){
      if(data.conv_status&&data.conv_status!=='closed'){
        showScreen('chat');
        show('input-area','flex');
        if(data.messages&&data.messages.length){
          data.messages.forEach(function(m){appendMsg(m.sender_type,m.content,m.created_at,m.content_type==='file'?m.file_url:null,m.id)});
          S.lastId=data.messages[data.messages.length-1].id||0; saveState();
        }
        if(data.conv_status==='waiting'&&$('waiting-bar')) $('waiting-bar').style.display='flex';
        else hide('waiting-bar');
        doPoll();
        S.poll=setInterval(doPoll,3000);
      } else {
        clearState(); S.token=null; S.ended=false; S.lastId=0;
      }
    })
    .catch(function(){clearState(); S.token=null; S.ended=false; S.lastId=0});
}

function doPoll(){
  if(!S.token||S.ended) return;
  fetch(apiBase+'/api/widget/poll-messages?conversation_id='+S.token+'&since_id='+S.lastId)
    .then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json()})
    .then(function(data){
      var area=$('msgs');
      if(!area) return;
      var atBot=area.scrollHeight-area.scrollTop<=area.clientHeight+80;
      var hasNew=false;
      if(data.messages&&data.messages.length){
        data.messages.forEach(function(m){
          if(m.sender_type!=='visitor'){
            appendMsg(m.sender_type,m.content,m.created_at,m.content_type==='file'?m.file_url:null,m.id);
            hasNew=true;
          }
        });
        S.lastId=data.last_id||S.lastId; saveState();
      }
      if(hasNew&&atBot) area.scrollTop=area.scrollHeight;
      if(data.conv_status==='active') hide('waiting-bar');
      var tb=$('typing-bar');
      if(tb){
        if(data.is_typing&&data.typing_text){
          tb.textContent=data.typing_text+'...';
          tb.style.display='block';
        } else {
          tb.textContent='';
          tb.style.display='none';
        }
      }
      if(data.conv_status==='closed'&&!S.ended){
        S.ended=true; saveState();
        clearInterval(S.poll);
        hide('input-area');
        hide('waiting-bar');
        $('closed-bar').style.display='block';
        $('btn-end').style.display='none';
      }
    })
    .catch(function(){});
}

function sendMsg(){
  var inp=$('inp-txt');
  var msg=(inp?inp.value:'').trim();
  if(!msg||!S.token||S.ended||S.isSending) return;
  S.isSending=true;
  inp.value=''; inp.style.height='auto';
  appendMsg('visitor',msg,new Date().toISOString());
  fetch(apiBase+'/api/widget/chat',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({sender_type:'visitor',conversation_id:S.token,content:msg})
  })
  .then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json()})
  .then(function(d){if(!d.success)throw new Error(d.error||'Failed')})
  .catch(function(e){console.error('Send error:',e)})
  .finally(function(){S.isSending=false});
}

function handleKey(e){
  if(e.key==='Enter'&&!e.shiftKey){ e.preventDefault(); sendMsg(); }
  autoGrow(e.target);
}

function uploadFile(inp){
  var file=inp.files[0];
  if(!file||!S.token||S.ended) return;
  $('upload-bar').style.display='block';
  var fd=new FormData();
  fd.append('conversation_id',S.token);
  fd.append('sender_type','visitor');
  fd.append('file',file);
  fetch(apiBase+'/api/widget/chat',{method:'POST',body:fd})
    .then(function(r){return r.json()})
    .then(function(d){
      if(d.success){ doPoll(); }
      else{ alert(d.error||'Upload gagal'); }
    })
    .catch(function(){alert('Error upload')})
    .finally(function(){$('upload-bar').style.display='none';inp.value=''});
}

function endChat(){
  if(!S.token||!confirm('Akhiri chat ini?')) return;
  S.ended=true; clearInterval(S.poll);
  clearState(); S.token=null; S.lastId=0;
  hide('input-area'); hide('waiting-bar');
  $('closed-bar').style.display='block';
  $('btn-end').style.display='none';
}

init();
})();
</script>
</body>
</html>

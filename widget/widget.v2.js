(function () {
    'use strict';

    const script = document.currentScript;
    const licenseKey = script ? script.getAttribute('license') : null;
    const apiBase = script ? script.src.replace('/widget/widget.js', '') : '';

    if (!licenseKey) {
        console.error('LiveChat: license required');
        return;
    }

    // ---------- Storage ----------
    const STORE_KEY = 'lc_sess_' + licenseKey;
    function loadSess() { try { return JSON.parse(localStorage.getItem(STORE_KEY)) || {}; } catch { return {}; } }
    function saveSess(d) { try { localStorage.setItem(STORE_KEY, JSON.stringify(d)); } catch {} }
    function clearSess() { try { localStorage.removeItem(STORE_KEY); } catch {} }

    let sess = loadSess();
    let config = {}, isOpen = false, isFullscreen = false;
    let lastMsgId = sess.lastMsgId || 0;
    let pollTimer = null, typingTimer = null, agentTypingTimer = null;
    let unreadCount = 0, isSending = false;

    // ---------- Cek Mobile ----------
    function isMobile() {
        return window.innerWidth <= 768;
    }

    // ---------- Notifikasi Suara ----------
    let notifSound = null;
    function initSound() {
        if (!notifSound) {
            notifSound = new Audio('/assets/sounds/new_message.CTorF0S8.mp3');
        }
    }
    function playNotif() {
        if (notifSound) {
            notifSound.currentTime = 0;
            notifSound.play().catch(() => {});
        }
    }

    // ---------- Ambil Konfigurasi ----------
    fetch(`${apiBase}/api/widget-config?license_key=${encodeURIComponent(licenseKey)}&_v=${Date.now()}`, { cache: 'no-store' })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                config = d.config || {};
                if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', build);
                else build();
            } else {
                console.warn('LC status:', d.message || d.error);
            }
        }).catch(e => console.error('LC cfg:', e));

    function injectFA() {
        if (document.querySelector('link[href*="font-awesome"]')) return;
        const l = document.createElement('link'); l.rel = 'stylesheet';
        l.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
        document.head.appendChild(l);
    }

    function build() {
        injectFA();
        const color = config.primary_color || '#1e62ff';
        const theme = config.widget_theme || 'light';
        const pos = config.position === 'left' ? 'left' : 'right';
        const agent = esc(config.agent_name || 'Support');
        const opos = pos === 'left' ? 'right' : 'left';

        const isDark = theme === 'dark';
        const bgW = isDark ? '#1a1a1a' : '#f7f9fc';
        const bgCard = isDark ? '#242424' : '#ffffff';
        const txtM = isDark ? '#ffffff' : '#1f2937';
        const txtS = isDark ? '#a0a0a0' : '#6b7280';
        const brd = isDark ? '#333333' : '#e5e7eb';
        const inBg = isDark ? '#2a2a2a' : '#f9fafb';
        const btnBg = isDark ? '#333333' : '#f3f4f6';

        /* =========== CSS MODERN + FULLSCREEN MOBILE ========== */
        const st = document.createElement('style');
        st.id = '_lcStyle';
        st.textContent = `
        /* Reset & Base */
        #_lc {
            position: fixed;
            bottom: 20px;
            ${pos}: 20px;
            z-index: 2147483647;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        /* Animasi Pulse */
        @keyframes _lcPulse {
            0% { box-shadow: 0 0 0 0 rgba(255,255,255,0.4); }
            50% { box-shadow: 0 0 0 12px rgba(255,255,255,0); }
            100% { box-shadow: 0 0 0 0 rgba(255,255,255,0); }
        }
        @keyframes _lcSlideUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes _lcTypingDot {
            0%, 80%, 100% { transform: scale(0); opacity: 0.3; }
            40% { transform: scale(1); opacity: 1; }
        }

        /* Tombol Chat */
        #_lcBtn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, ${color}, ${color}dd);
            color: #fff;
            border: none;
            cursor: pointer;
            font-size: 24px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        #_lcBtn::after {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.2);
            animation: _lcPulse 2s infinite;
        }
        #_lcBtn:hover { transform: scale(1.1) rotate(-5deg); box-shadow: 0 8px 25px rgba(0, 0, 0, 0.25); }
        #_lcBtn:active { transform: scale(0.95); }
        #_lcBtn:hover::after { animation: none; opacity: 0; }

        /* Badge Notifikasi */
        #_lcBadge {
            position: absolute;
            top: -5px;
            ${opos}: -5px;
            min-width: 20px;
            height: 20px;
            background: #ff3b30;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            border-radius: 20px;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 0 6px;
            border: 2px solid #fff;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        #_lcBadge.on { display: flex; }

        /* Container Chat */
        #_lcW {
            position: fixed;
            bottom: 90px;
            ${pos}: 20px;
            width: 380px;
            height: 600px;
            max-width: calc(100vw - 40px);
            max-height: calc(100dvh - 110px);
            background: ${bgW};
            border-radius: 20px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.18);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transform: translateY(20px) scale(0.95);
            opacity: 0;
            pointer-events: none;
            transition: all 0.25s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }
        #_lcW.on {
            transform: translateY(0) scale(1);
            opacity: 1;
            pointer-events: all;
        }
        #_lcW.fs {
            width: 100% !important;
            height: 100% !important;
            bottom: 0 !important;
            ${pos}: 0 !important;
            max-width: none !important;
            max-height: none !important;
            border-radius: 9px !important;
        }

        /* Header */
        #_lcHead {
            background: linear-gradient(135deg, ${color}, ${color}cc);
            padding: 16px 20px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            color: #fff;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        #_lcHead::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
        }
        ._lcHLeft, ._lcHRight { display: flex; gap: 8px; }
        ._lcHTopBtn {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.1);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: all 0.2s;
            backdrop-filter: blur(4px);
        }
        ._lcHTopBtn:hover { background: rgba(255,255,255,0.3); transform: scale(1.1); }

        ._lcFloatHead {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        ._lcAv {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 18px;
            overflow: hidden;
        }
        ._lcAv img { width: 100%; height: 100%; object-fit: cover; }
        ._lcHName { font-size: 15px; font-weight: 600; }
        ._lcHSub { font-size: 11px; opacity: 0.8; }

        /* Form Awal (Pre-chat) */
        #_lcPre {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
        }
        ._lcWhiteCard {
            background: ${bgCard};
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        }
        ._lcInfoBox {
            background: ${isDark ? '#2a2a2a' : '#f0fdf4'};
            border-left: 4px solid ${color};
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 8px;
            display: flex;
            gap: 10px;
        }
        ._lcInfoBox i { color: ${color}; }
        ._lcInfoTxt { font-size: 12px; color: ${txtM}; line-height: 1.4; }

        ._lcFLabel { font-size: 13px; font-weight: 600; color: ${txtM}; margin-bottom: 6px; display: block; }
        ._lcFG { margin-bottom: 16px; }
        ._lcFI, ._lcSelect {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid ${brd};
            border-radius: 12px;
            font-size: 14px;
            outline: none;
            background: ${inBg};
            color: ${txtM};
            transition: all 0.2s;
        }
        ._lcFI:focus, ._lcSelect:focus { border-color: ${color}; box-shadow: 0 0 0 3px ${color}22; }
        ._lcSBtn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, ${color}, ${color}dd);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        ._lcSBtn:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(0,0,0,0.2); }
        ._lcSBtn:active { transform: translateY(0); }
        ._lcPreErr {
            color: #ef4444;
            font-size: 12px;
            margin-bottom: 12px;
            display: none;
            padding: 10px;
            background: #fef2f2;
            border-radius: 8px;
        }

        /* Area Pesan */
        #_lcMsgs {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: none;
            flex-direction: column;
            gap: 12px;
        }
        ._lcMW {
            display: flex;
            gap: 8px;
            max-width: 85%;
            animation: _lcSlideUp 0.3s ease;
        }
        ._lcMW._v { align-self: flex-end; flex-direction: row-reverse; }
        ._lcMW._a { align-self: flex-start; }

        ._lcM {
            padding: 12px 16px;
            border-radius: 18px;
            font-size: 14px;
            line-height: 1.5;
            word-break: break-word;
            position: relative;
        }
        ._lcMVisitor {
            background: linear-gradient(135deg, ${color}, ${color}dd);
            color: #fff;
            border-bottom-right-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        ._lcMAgent, ._lcMBot {
            background: ${bgCard};
            color: ${txtM};
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            border: 1px solid ${brd};
        }
        ._lcT {
            font-size: 10px;
            margin-top: 6px;
            opacity: 0.6;
            text-align: right;
        }
        ._lcSmallAv {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, ${color}, ${color}cc);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            flex-shrink: 0;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            margin-top: 4px;
        }

        /* Typing Indicator */
        #_lcTyp {
            padding: 8px 20px;
            display: none;
        }
        #_lcTyp.on { display: block; }
        ._lcMBot.typing {
            padding: 12px 16px;
            color: ${txtS};
            display: flex;
            align-items: center;
            gap: 4px;
        }
        ._lcMBot.typing span {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: ${txtS};
            display: inline-block;
            animation: _lcTypingDot 1.4s infinite both;
        }
        ._lcMBot.typing span:nth-child(2) { animation-delay: 0.2s; }
        ._lcMBot.typing span:nth-child(3) { animation-delay: 0.4s; }

        /* Input Area */
        #_lcIn {
            padding: 12px 16px;
            background: ${bgCard};
            border-top: 1px solid ${brd};
            display: none;
            gap: 10px;
            align-items: center;
        }
        #_lcIn.on { display: flex; }
        ._lcInputWrap {
            flex: 1;
            background: ${inBg};
            border-radius: 24px;
            padding: 8px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 2px solid ${brd};
            transition: border-color 0.2s;
        }
        ._lcInputWrap:focus-within { border-color: ${color}; }
        #_lcTxt {
            flex: 1;
            border: none;
            outline: none;
            padding: 8px 0;
            font-size: 14px;
            background: transparent;
            color: ${txtM};
            resize: none;
            max-height: 100px;
            font-family: inherit;
        }
        ._lcIB {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            background: linear-gradient(135deg, ${color}, ${color}dd);
            color: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        ._lcIB:hover { transform: scale(1.1); }
        ._lcIB:active { transform: scale(0.95); }
        ._lcIB:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }

        /* Footer */
        #_lcFoot {
            padding: 10px;
            text-align: center;
            font-size: 10px;
            color: ${txtS};
            background: ${bgCard};
            border-top: 1px solid ${brd};
            flex-shrink: 0;
        }

        /* Modal Konfirmasi */
        #_lcModal {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 100;
            display: none;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }
        #_lcModal.on { display: flex; }
        ._lcMCard {
            background: ${bgCard};
            border-radius: 20px;
            width: 280px;
            padding: 24px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        ._lcMBtn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #e11d48, #be123c);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 16px;
            transition: all 0.2s;
        }
        ._lcMBtn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(225,29,72,0.4); }

        /* System Message */
        ._lcSys {
            text-align: center;
            font-size: 11px;
            color: ${txtS};
            margin: 8px 0;
            padding: 8px 16px;
            background: ${btnBg};
            border-radius: 20px;
            align-self: center;
            max-width: 90%;
            border: 1px solid ${brd};
        }

        /* RESPONSIVE: Mobile Fullscreen seperti LiveChat */
        @media (max-width: 768px) {
            #_lc {
                bottom: 0;
                ${pos}: 0;
                right: 0;
                left: 0;
            }
            #_lcW {
                position: fixed;
                bottom: 0;
                ${pos}: 0;
                width: 100% !important;
                height: 100dvh !important;
                max-width: none !important;
                max-height: none !important;
                border-radius: 0 !important;
                bottom: 0;
                ${pos}: 0;
            }
            #_lcW:not(.on) {
                visibility: hidden;
            }
            #_lcBtn {
                position: fixed;
                bottom: 20px;
                ${pos}: 20px;
                right: 20px;
                left: auto;
            }
            ._lcMW {
                max-width: 90%;
            }
        }
        `;
        document.head.appendChild(st);

        /* =========== HTML =========== */
        const avatarSrc = config.agent_avatar || '';
        const avatarHtml = avatarSrc
            ? `<img src="${avatarSrc}" alt="${agent}" onerror="this.style.display='none';this.parentNode.textContent='${agent.charAt(0).toUpperCase()}'">`
            : agent.charAt(0).toUpperCase();

        const wrap = document.createElement('div');
        wrap.id = '_lc';
        wrap.innerHTML = `
        <script id="hs-script-loader" async="" defer="defer" src="//js.hs-scripts.com/26269451.js"></script>
        <button id="_lcBtn" aria-label="Buka chat">
            <i class="fa-solid fa-comment-dots" id="_lcBtnIco"></i>
            <span id="_lcBadge"></span>
        </button>
        <div id="_lcW" role="dialog" aria-label="Live Chat" aria-modal="true">
            <div id="_lcModal">
                <div class="_lcMCard">
                    <div class="_lcMTtl" style="margin-bottom:8px;">Tutup obrolan?</div>
                    <button class="_lcMBtn" id="_lcMConfirm">Ya, Tutup</button>
                </div>
            </div>
            <div id="_lcHead">
                <div class="_lcHLeft">
                    <button class="_lcHTopBtn" id="_lcFsBtn" title="Fullscreen"><i class="fa-solid fa-expand"></i></button>
                </div>
                <div class="_lcFloatHead">
                    <div class="_lcAv">${avatarHtml}</div>
                    <div class="_lcHInfo">
                        <div class="_lcHName">${agent}</div>
                        <div class="_lcHSub">Online</div>
                    </div>
                </div>
                <div class="_lcHRight">
                    <button class="_lcHTopBtn" id="_lcMinBtn" title="Minimize"><i class="fa-solid fa-minus"></i></button>
                    <button class="_lcHTopBtn" id="_lcCloseBtn" title="Tutup"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
            <div id="_lcPre">
                <div class="_lcWhiteCard">
                    <div class="_lcInfoBox">
                        <i class="fa-solid fa-circle-info"></i>
                        <div class="_lcInfoTxt">Halo! Silakan isi data di bawah untuk memulai percakapan.</div>
                    </div>
                    <div class="_lcFG">
                        <label class="_lcFLabel">Nama Lengkap</label>
                        <input class="_lcFI" id="_lcName" type="text" placeholder="Masukkan nama Anda">
                    </div>
                    <div class="_lcFG">
                        <label class="_lcFLabel">Nomor WhatsApp</label>
                        <input class="_lcFI" id="_lcPhone" type="tel" placeholder="Contoh: 08123456789">
                    </div>
                    <div class="_lcFG">
                        <label class="_lcFLabel">Topik</label>
                        <select class="_lcSelect" id="_lcSubject">
                            <option value="">Pilih topik...</option>
                            <option value="deposit">Deposit</option>
                            <option value="withdraw">Withdraw</option>
                            <option value="reset_password">Reset Password</option>
                            <option value="kendala_lainnya">Kendala Lainnya</option>
                        </select>
                    </div>
                    <div id="_lcPreErr" class="_lcPreErr"></div>
                    <button class="_lcSBtn" id="_lcSBtn">Mulai Chat</button>
                </div>
            </div>
            <div id="_lcMsgs"></div>
            <div id="_lcTyp">
                <div class="_lcMW _a">
                    <div class="_lcSmallAv">${avatarHtml}</div>
                    <div class="_lcM _lcMBot typing"><span></span><span></span><span></span></div>
                </div>
            </div>
            <div id="_lcIn">
                <div class="_lcInputWrap">
                    <input type="file" id="_lcFile" accept="image/*,application/pdf" style="display:none">
                    <button id="_lcUpBtn" style="background:none;border:none;color:${txtS};font-size:18px;cursor:pointer;"><i class="fa-solid fa-paperclip"></i></button>
                    <textarea id="_lcTxt" rows="1" placeholder="Ketik pesan..."></textarea>
                </div>
                <button class="_lcIB" id="_lcSend" disabled><i class="fa-solid fa-paper-plane"></i></button>
            </div>
        <div id="_lcFoot">
            Powered by
            <a href="#" target="_blank">
                <img src="/assets/images/white.png" alt="LiveChat" style="height: 24px; vertical-align: middle; opacity: 0.8;">
            </a>
        </div> 
        </div>`;


        document.body.appendChild(wrap);
        bindEvt();
        setupPublicAPI();
        if (sess.conversationId) resumeSess();
    }

    function bindEvt() {
        gi('_lcBtn').onclick = toggle;
        gi('_lcMinBtn').onclick = close;
        gi('_lcFsBtn').onclick = toggleFs;
        gi('_lcCloseBtn').onclick = () => gi('_lcModal').classList.add('on');
        gi('_lcMConfirm').onclick = () => {
            gi('_lcModal').classList.remove('on');
            fullClose();
        };
        gi('_lcSBtn').onclick = startChat;
        gi('_lcSend').onclick = sendMsg;
        gi('_lcUpBtn').onclick = () => gi('_lcFile').click();
        gi('_lcFile').onchange = e => uploadFile(e.target.files[0]);

        const txt = gi('_lcTxt');
        txt.addEventListener('input', () => {
            resize(txt);
            gi('_lcSend').disabled = !txt.value.trim();
            clearTimeout(typingTimer);
            sendTyping(true, txt.value);
            typingTimer = setTimeout(() => sendTyping(false, ''), 2000);
        });
        txt.addEventListener('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMsg(); }
        });
    }

    function setupPublicAPI() {
        var lcw = window.LiveChatWidget;
        if (!lcw) {
            lcw = {};
            window.LiveChatWidget = lcw;
            window.__lcw = lcw;
        }
        var publicApi = {
            _events: {},
            on: function(event, fn) {
                if (!this._events[event]) this._events[event] = [];
                this._events[event].push(fn);
            },
            off: function(event, fn) {
                if (!this._events[event]) return;
                this._events[event] = this._events[event].filter(function(f) { return f !== fn; });
            },
            get: function(prop) {
                if (prop === 'isOpen') return isOpen;
                if (prop === 'unreadCount') return unreadCount;
                return undefined;
            },
            call: function(method) {
                if (method === 'open') toggle();
                else if (method === 'close') close();
                else if (method === 'toggle') toggle();
            }
        };
        Object.assign(lcw, publicApi);
    }

    /* ========== FUNGSI UTAMA ========== */
    function toggle() {
        if (isOpen) { close(); return; }
        initSound();
        isOpen = true;
        gi('_lcW').classList.add('on');
        gi('_lcBtnIco').className = 'fa-solid fa-times';
        updateBadge(0);
        
        // Fullscreen otomatis di mobile
        if (isMobile() && !isFullscreen) {
            isFullscreen = true;
            gi('_lcW').classList.add('fs');
            gi('_lcFsBtn').innerHTML = '<i class="fa-solid fa-compress"></i>';
        }
        
        setTimeout(() => {
            const el = gi('_lcMsgs').style.display !== 'none' ? gi('_lcTxt') : gi('_lcName');
            if (el) el.focus();
            scrollBot();
        }, 100);
    }

    function close() {
        if (!isOpen) return;
        isOpen = false;
        gi('_lcW').classList.remove('on');
        gi('_lcBtnIco').className = 'fa-solid fa-comment-dots';
        if (isFullscreen && !isMobile()) {
            isFullscreen = false;
            gi('_lcW').classList.remove('fs');
            gi('_lcFsBtn').innerHTML = '<i class="fa-solid fa-expand"></i>';
        }
    }

    function toggleFs() {
        isFullscreen = !isFullscreen;
        gi('_lcW').classList.toggle('fs', isFullscreen);
        gi('_lcFsBtn').innerHTML = isFullscreen ? '<i class="fa-solid fa-compress"></i>' : '<i class="fa-solid fa-expand"></i>';
    }

    function fullClose() {
        close();
        clearSess();
        sess = {};
        lastMsgId = 0;
        gi('_lcMsgs').innerHTML = '';
        gi('_lcMsgs').style.display = 'none';
        gi('_lcPre').style.display = 'flex';
        gi('_lcIn').classList.remove('on');
        clearInterval(pollTimer);
    }

function resumeSess() {
    if (!sess.conversationId) return;
    
    // Tampilkan loading indicator
    gi('_lcPreErr').textContent = 'Memuat percakapan...';
    gi('_lcPreErr').style.display = 'block';
    
    fetch(`${apiBase}/api/poll-messages?conv=${sess.conversationId}&last_id=0&side=visitor`)
        .then(r => r.json())
        .then(data => {
            gi('_lcPreErr').style.display = 'none';
            
            if (data.conv_status && data.conv_status !== 'closed') {
                showChat();
                
                if (data.messages && data.messages.length) {
                    data.messages.forEach(m => {
                        appendMsg(m.sender_type, m.content, m.created_at, m.file_url, m.id);
                    });
                    // Update lastMsgId
                    const lastMsg = data.messages[data.messages.length - 1];
                    if (lastMsg && lastMsg.id) {
                        sess.lastMsgId = lastMsg.id;
                        saveSess(sess);
                    }
                }
                
                scrollBot();
                startPoll();
            } else {
                // Chat sudah closed, mulai chat baru
                clearSess();
                sess = {};
                gi('_lcPre').style.display = 'flex';
                gi('_lcMsgs').style.display = 'none';
                gi('_lcIn').classList.remove('on');
            }
        })
        .catch((err) => {
            console.error('Resume session error:', err);
            gi('_lcPreErr').style.display = 'none';
            clearSess();
            sess = {};
        });
}

function showChat() {
    const preDiv = gi('_lcPre');
    const msgsDiv = gi('_lcMsgs');
    const inDiv = gi('_lcIn');
    
    if (preDiv) preDiv.style.display = 'none';
    if (msgsDiv) msgsDiv.style.display = 'flex';
    if (inDiv) inDiv.classList.add('on');
    
    // ðŸ”¥ Fullscreen otomatis untuk mobile
    if (isMobile() && !isFullscreen) {
        isFullscreen = true;
        const widget = gi('_lcW');
        const fsBtn = gi('_lcFsBtn');
        if (widget) widget.classList.add('fs');
        if (fsBtn) fsBtn.innerHTML = '<i class="fa-solid fa-compress"></i>';
    }
    
    // ðŸ”¥ Scroll ke bawah setelah chat ditampilkan
    setTimeout(() => scrollBot(), 100);
}
function startChat() {
    const name = gi('_lcName').value.trim();
    const phone = gi('_lcPhone').value.trim();
    const subject = gi('_lcSubject').value;
    
    if (!name || !phone || !subject) {
        gi('_lcPreErr').textContent = 'Harap isi semua field!';
        gi('_lcPreErr').style.display = 'block';
        return;
    }
    
    gi('_lcPreErr').style.display = 'none';
    gi('_lcSBtn').innerHTML = 'Memulai...';
    gi('_lcSBtn').disabled = true; // ðŸ”¥ Tambahan: disable tombol
        
    fetch(`${apiBase}/api/chat`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            action: 'init', 
            license_key: licenseKey, 
            username: name, 
            phone: phone, 
            issue_type: [subject] 
        })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            // ðŸ”¥ Simpan semua data session dengan lengkap
            sess = { 
                conversationId: d.conversation_id, 
                visitorId: d.visitor_id, 
                sessionId: d.session_id,
                lastMsgId: 0 
            };
            saveSess(sess);
            
            // ðŸ”¥ Tampilkan chat area
            showChat();
            
            // ðŸ”¥ Handle resume chat (pesan sebelumnya)
            if (d.resume === true && d.messages && d.messages.length > 0) {
                // Kosongkan container pesan terlebih dahulu
                const msgsContainer = gi('_lcMsgs');
                if (msgsContainer) msgsContainer.innerHTML = '';
                
                // Tampilkan semua pesan sebelumnya
                d.messages.forEach(m => {
                    appendMsg(m.sender_type, m.content, m.created_at, m.file_url, m.id);
                });
                
                // Update lastMsgId dengan ID pesan terakhir
                const lastMsg = d.messages[d.messages.length - 1];
                if (lastMsg && lastMsg.id) {
                    sess.lastMsgId = lastMsg.id;
                    saveSess(sess);
                }
            }
            
            scrollBot();
            startPoll();
        } else {
            gi('_lcPreErr').textContent = 'Gagal memulai chat: ' + (d.error || 'Unknown');
            gi('_lcPreErr').style.display = 'block';
        }
    })
    .catch((err) => {
        console.error('Start chat error:', err);
        gi('_lcPreErr').textContent = 'Koneksi gagal, coba lagi.';
        gi('_lcPreErr').style.display = 'block';
    })
    .finally(() => {
        gi('_lcSBtn').innerHTML = 'Mulai Chat';
        gi('_lcSBtn').disabled = false; // ðŸ”¥ Aktifkan kembali tombol
    });
}
    function sendMsg() {
        const txt = gi('_lcTxt');
        const content = txt.value.trim();
        if (!content || !sess.conversationId || isSending) return;
        isSending = true;
        appendMsg('visitor', content, new Date().toISOString());
        txt.value = '';
        resize(txt);
        gi('_lcSend').disabled = true;
        clearTimeout(typingTimer);
        sendTyping(false, '');
        scrollBot();
        
        fetch(`${apiBase}/api/chat`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'send',
                sender_type: 'visitor',
                conversation_id: sess.conversationId,
                content: content
            })
        })
        .then(() => { isSending = false; })
        .catch(() => { isSending = false; });
    }

    function uploadFile(file) {
        if (!file || !sess.conversationId) return;
        if (file.size > 5 * 1024 * 1024) return alert('Maksimal 5MB');
        const fd = new FormData();
        fd.append('conversation_id', sess.conversationId);
        fd.append('sender_type', 'visitor');
        fd.append('file', file);
        fetch(`${apiBase}/api/chat`, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) appendMsg('visitor', '', new Date().toISOString(), d.url);
            });
        gi('_lcFile').value = '';
    }

    function sendTyping(on, text) {
        if (!sess.conversationId) return;
        fetch(`${apiBase}/api/chat`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'typing',
                user_type: 'visitor',
                conversation_id: sess.conversationId,
                is_typing: on ? 1 : 0,
                typing_text: text
            })
        }).catch(()=>{});
    }

    function startPoll() {
        clearInterval(pollTimer);
        doPoll();
        pollTimer = setInterval(doPoll, 3000);
    }

    function doPoll() {
        if (!sess.conversationId) return;
        fetch(`${apiBase}/api/poll-messages?conv=${sess.conversationId}&last_id=${lastMsgId}&side=visitor`)
            .then(r => r.json())
            .then(data => {
                if (data.messages && data.messages.length) {
                    data.messages.forEach(m => {
                        if (m.sender_type !== 'visitor') {
                            appendMsg(m.sender_type, m.content, m.created_at, m.file_url, m.id);
                        }
                    });
                    lastMsgId = data.last_id || lastMsgId;
                    saveSess({ ...sess, lastMsgId });
                    const newOtherMsgs = data.messages.filter(m => m.sender_type !== 'visitor');
                    if (newOtherMsgs.length > 0) {
                        if (!isOpen) {
                            unreadCount += newOtherMsgs.length;
                            updateBadge(unreadCount);
                            playNotif();
                        }
                    }
                    scrollBot();
                }
                if (data.is_typing) showTyp();
                else hideTyp();
                if (data.conv_status === 'closed') {
                    clearInterval(pollTimer);
                    appendSys('Chat telah ditutup. Terima kasih!');
                    gi('_lcIn').classList.remove('on');
                }
            }).catch(()=>{});
    }

    function appendMsg(type, content, time, fileUrl, id) {
        const c = gi('_lcMsgs');
        if (!c) return;
        if (id && c.querySelector(`[data-id="${id}"]`)) return;
        if (type !== 'visitor') hideTyp();

        const w = document.createElement('div');
        w.className = `_lcMW ${type === 'visitor' ? '_v' : '_a'}`;
        if (id) w.setAttribute('data-id', id);

        const b = document.createElement('div');
        const typeClass = type === 'visitor' ? '_lcMVisitor' : (type === 'bot' ? '_lcMBot' : '_lcMAgent');
        b.className = `_lcM ${typeClass}`;

        if (fileUrl) {
            if (fileUrl.match(/\.(jpeg|jpg|gif|png|webp)$/i)) {
                b.innerHTML = `<img src="${apiBase}/${fileUrl}" style="max-width:200px;border-radius:8px;cursor:pointer" onclick="window.open(this.src,'_blank')">`;
            } else {
                b.innerHTML = `<a href="${apiBase}/${fileUrl}" target="_blank" style="color:inherit;">ðŸ“Ž Download File</a>`;
            }
        } else if (content) {
            b.innerHTML = esc(content).replace(/\n/g, '<br>').replace(/\*([^*]+)\*/g, '<strong>$1</strong>');
        }

        const t = document.createElement('div');
        t.className = '_lcT';
        t.textContent = fmtTime(time);
        b.appendChild(t);
        w.appendChild(b);
        c.appendChild(w);
        scrollBot();
    }

    function appendSys(msg) {
        const c = gi('_lcMsgs');
        if (!c) return;
        const d = document.createElement('div');
        d.className = '_lcSys';
        d.textContent = msg;
        c.appendChild(d);
        scrollBot();
    }

    function showTyp() {
        const el = gi('_lcTyp');
        if (!el) return;
        el.classList.add('on');
        clearTimeout(agentTypingTimer);
        agentTypingTimer = setTimeout(hideTyp, 7000);
        scrollBot();
    }
    
    function hideTyp() {
        const el = gi('_lcTyp');
        if (el) el.classList.remove('on');
    }

    function updateBadge(n) {
        unreadCount = Math.max(0, n);
        const b = gi('_lcBadge');
        if (!b) return;
        b.textContent = unreadCount > 99 ? '99+' : unreadCount;
        b.classList.toggle('on', unreadCount > 0);
    }

    function scrollBot() {
        const m = gi('_lcMsgs');
        if (m) requestAnimationFrame(() => { m.scrollTop = m.scrollHeight; });
    }

    function resize(el) {
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 100) + 'px';
    }

    function gi(id) { return document.getElementById(id); }
    function esc(s) { if (!s) return ''; const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    function fmtTime(iso) {
        try { return new Date(iso).toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' }); } catch { return ''; }
    }
})();

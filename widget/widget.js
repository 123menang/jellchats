(function () {
    'use strict';

    const script = document.currentScript;
    const licenseKey = script 
        ? (script.getAttribute('license') || script.getAttribute('data-site-id') || script.getAttribute('data-license')) 
        : null;
    const apiBase = script ? script.src.replace('/widget/widget.js', '').split('?')[0] : '';

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

    // ---------- Ambil Konfigurasi (TANPA AUTO RELOAD) ----------

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
function icn(n) {
    const s='<svg viewBox="0 0 512 512" width="16" height="16" fill="currentColor"><path d="';
    const e='"/></svg>';
    const m={'comment':'M256 32C114.6 32 0 125.1 0 240c0 49.6 21.4 95 57 130.7C44.5 421.1 2.7 466 2.2 466.5c-2.2 2.3-2.8 5.7-1.5 8.7S4.8 480 8 480c66.3 0 116-31.8 140.6-51.4 32.7 12.3 69 19.4 107.4 19.4 141.4 0 256-93.1 256-208S397.4 32 256 32z',
    'minus':'M416 256H96c-17.7 0-32-14.3-32-32s14.3-32 32-32h320c17.7 0 32 14.3 32 32s-14.3 32-32 32z',
    'times':'M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z',
    'expand':'M32 32C14.3 32 0 46.3 0 64v96c0 17.7 14.3 32 32 32s32-14.3 32-32V96h64c17.7 0 32-14.3 32-32s-14.3-32-32-32H32zm0 384c-17.7 0-32 14.3-32 32v96c0 17.7 14.3 32 32 32h96c17.7 0 32-14.3 32-32s-14.3-32-32-32H96v-64c0-17.7-14.3-32-32-32s-32 14.3-32 32zM512 64c0-17.7-14.3-32-32-32h-96c-17.7 0-32 14.3-32 32s14.3 32 32 32h64v64c0 17.7 14.3 32 32 32s32-14.3 32-32V64zm0 384c0 17.7-14.3 32-32 32h-96c-17.7 0-32-14.3-32-32s14.3-32 32-32h64v-64c0-17.7 14.3-32 32-32s32 14.3 32 32v96z',
    'circle':'M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512z',
    'info':'M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM216 336h24V272H216c-13.3 0-24-10.7-24-24s10.7-24 24-24h48c13.3 0 24 10.7 24 24v88h8c13.3 0 24 10.7 24 24s-10.7 24-24 24H216c-13.3 0-24-10.7-24-24s10.7-24 24-24zm40-208a32 32 0 1 1 0 64 32 32 0 1 1 0-64z',
    'user':'M224 256A128 128 0 1 0 224 0a128 128 0 1 0 0 256zm-45.7 48C79.8 304 0 383.8 0 482.3C0 498.7 13.3 512 29.7 512H418.3c16.4 0 29.7-13.3 29.7-29.7C448 383.8 368.2 304 269.7 304H178.3z',
    'phone':'M164.9 24.6c-7.7-18.6-28-28.5-47.4-23.2l-88 24C12.1 30.2 0 46 0 64C0 311.4 200.6 512 448 512c18 0 33.8-12.1 38.6-29.5l24-88c5.3-19.4-4.6-39.7-23.2-47.4l-96-40c-16.3-6.8-35.2-2.1-46.3 11.6L304.7 368C234.3 334.7 177.3 277.7 144 207.3L193.3 167c13.7-11.2 18.4-30 11.6-46.3l-40-96z',
    'tag':'M0 80V229.5c0 17 6.7 33.3 18.7 45.3l176 176c25 25 65.5 25 90.5 0L418.7 317.3c25-25 25-65.5 0-90.5l-176-176c-12-12-28.3-18.7-45.3-18.7H48C21.5 32 0 53.5 0 80zm112 32a32 32 0 1 1 0 64 32 32 0 1 1 0-64z',
    'plane':'M498.1 5.6c10.1 7 15.4 19.1 13.5 31.2l-64 416c-1.5 9.7-7.4 18.2-16 23s-18.9 5.4-28 1.6L284 427.7l-68.5 74.1c-8.9 9.7-22.9 12.9-35.2 8.1S160 493.2 160 480V396.4c0-4 1.5-7.9 4.2-10.8L331.8 202.8c5.8-6.3 5.6-16-.4-22s-15.7-6.4-22-.7L106 360.8 17.7 316.6C7.1 311.3 .7 300.7 0 288.9s5.9-24.1 16.1-29.7l448-256c10.4-5.9 22.8-4.5 31.8 2.4z',
    'clip':'M364.5 117.7L183.6 310.6c-16 17.4-42.1 18.6-59.6 2.6s-18.6-42.1-2.6-59.6L376.1 87.8c38.5-41.9 100-42.6 139.1-1.6c38.9 41.1 38.3 107.2-1.4 147.5L276.8 460.7c-53.8 57.6-141.7 60.4-199.2 6.4s-60.4-141.7-6.4-199.2L238.8 100.5c7.1-7.7 18.9-8.1 26.6-1s8.1 18.9 1 26.6L98.8 293.5c-40.7 43.6-43.1 110.5-5.5 151.5c37.3 40.7 101.6 42.8 141.6 4.8L472.5 213.5c25.4-27.2 25.9-69.3 4.4-95.6c-21.7-26.7-62.9-28.4-86.8-3.7L164.2 346.6c-13.4 14.2-14.7 36.4-2.7 50.9c11.6 14 32.6 16.1 46.9 3.9L364.5 211.6c7.5-8.1 20.2-8.6 28.3-1.1s8.6 20.2 1.1 28.3l-156.8 170z',
    'warning':'M256 32c14.2 0 27.3 7.5 33.8 19.6L444.6 330c6.5 11.9 6.9 25.8 1 38.1s-17 20.9-30.1 20.9H40.5c-13.1 0-24.9-7.5-30.1-20.9s-5.5-26.2 1-38.1L222.2 51.6c6.5-12.1 19.6-19.6 33.8-19.6zm0 96c-13.3 0-24 10.7-24 24V264c0 13.3 10.7 24 24 24s24-10.7 24-24V152c0-13.3-10.7-24-24-24zm-32 224a32 32 0 1 1 64 0 32 32 0 1 1-64 0z',
    'check':'M438.6 105.4c12.5 12.5 12.5 32.8 0 45.3l-256 256c-12.5 12.5-32.8 12.5-45.3 0l-128-128c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0L160 338.7 393.4 105.4c12.5-12.5 32.8-12.5 45.3 0z',
    'pen':'M362.7 19.3L314.3 67.7 444.3 197.7l48.4-48.4c25-25 25-65.5 0-90.5L453.3 19.3c-25-25-65.5-25-90.5 0zm-71 71L58.6 323.5c-10.4 10.4-18 23.3-22.2 37.4L1 481.2C-1.5 489.7 .8 498.8 7 505s15.3 8.5 23.7 6.1l120.3-35.4c14.1-4.2 27-11.8 37.4-22.2L421.7 220.3 291.7 90.3z'};
    return s+m[n]+e;
}
function build() {
    const color = config.primary_color || '#1e62ff';
    const theme = config.widget_theme || 'light';
    const pos = config.position === 'left' ? 'left' : 'right';
    const agent = esc(config.agent_name || 'Support');
    const opos = pos === 'left' ? 'right' : 'left';

    const isDark = theme === 'dark';
    const bgW = '#1a1a1a';
    const bgCard = '#222222';
    const txtM = '#ffffff';
    const txtS = '#a0a0a0';
    const brd = '#2a2a2a';
    const inBg = '#2a2a2a';
    const btnBg = '#333333';

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

    /* Tombol Chat */
    #_lcBtn {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: ${color};
        color: #fff;
        border: none;
        cursor: pointer;
        font-size: 24px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }
    #_lcBtn:hover { transform: scale(1.05); box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2); }
    #_lcBtn:active { transform: scale(0.95); }

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
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
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
        border-radius: 0 !important;
    }

    /* Header */
    #_lcHead {
        background: ${color};
        padding: 16px 20px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
        color: #fff;
    }
    ._lcHLeft, ._lcHRight { display: flex; gap: 8px; align-items: center; }
    ._lcHTopBtn {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: rgba(255,255,255,0.2);
        color: #fff;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        transition: background 0.2s;
    }
    ._lcHTopBtn:hover { background: rgba(255,255,255,0.3); }

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
    ._lcHInfo { display: flex; flex-direction: column; }
    ._lcHName { font-size: 15px; font-weight: 600; line-height: 1.2; }
    ._lcHSub { font-size: 11px; opacity: 0.8; margin-top: 2px; }

    /* Form Awal (Pre-chat) */
    #_lcPre {
        flex: 1;
        overflow-y: auto;
        padding: 24px 20px;
        display: flex;
        flex-direction: column;
    }
    ._lcWhiteCard {
        background: ${bgCard};
        border-radius: 16px;
        padding: 24px;
    }
    ._lcInfoBox {
        background: #2a2a2a;
        border-left: 4px solid ${color};
        padding: 14px;
        margin-bottom: 24px;
        border-radius: 8px;
        display: flex;
        gap: 12px;
        align-items: flex-start;
    }
    ._lcInfoBox i { font-size: 16px; color: ${color}; margin-top: 2px; }
    ._lcInfoTxt { font-size: 13px; color: ${txtM}; line-height: 1.4; font-weight: 500; }

    ._lcFLabel { font-size: 14px; font-weight: 600; color: ${txtM}; margin-bottom: 8px; display: block; }
    ._lcFG { margin-bottom: 20px; }
    ._lcFI, ._lcSelect {
        width: 100%;
        padding: 14px 16px;
        border: 1.5px solid ${brd};
        border-radius: 14px;
        font-size: 14px;
        outline: none;
        background: ${inBg};
        color: ${txtM};
        box-sizing: border-box;
        transition: border-color 0.2s;
    }
    ._lcFI:focus, ._lcSelect:focus { border-color: ${color}; }
    ._lcFI::placeholder { color: #555555; }
    ._lcSelect {
        appearance: none;
        background-image: url("data:image/svg+xml;utf8,<svg fill='white' height='24' viewBox='0 0 24 24' width='24' xmlns='[http://www.w3.org/2000/svg](http://www.w3.org/2000/svg)'><path d='M7 10l5 5 5-5z'/><path d='M0 0h24v24H0z' fill='none'/></svg>");
        background-repeat: no-repeat;
        background-position: right 14px center;
        background-size: 20px;
        padding-right: 40px;
    }
    ._lcSBtn {
        width: 100%;
        padding: 16px;
        background: ${color};
        color: #fff;
        border: none;
        border-radius: 14px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        margin-top: 12px;
        transition: opacity 0.2s;
    }
    ._lcSBtn:hover { opacity: 0.9; }
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
    ._lcMW { display: flex; gap: 8px; max-width: 85%; }
    ._lcMW._v { align-self: flex-end; flex-direction: row-reverse; }
    ._lcMW._a { align-self: flex-start; }

    ._lcM {
        padding: 10px 14px;
        border-radius: 18px;
        font-size: 14px;
        line-height: 1.45;
        word-break: break-word;
    }
    ._lcMVisitor {
        background: ${color};
        color: #fff;
        border-bottom-right-radius: 4px;
    }
    ._lcMAgent, ._lcMBot {
        background: ${bgCard};
        color: ${txtM};
        border-bottom-left-radius: 4px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    ._lcT {
        font-size: 10px;
        margin-top: 6px;
        opacity: 0.7;
        text-align: right;
    }
    ._lcSmallAv {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: ${color};
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        flex-shrink: 0;
    }

    /* Typing Indicator */
    #_lcTyp {
        padding: 8px 20px;
        display: none;
    }
    #_lcTyp.on { display: block; }
    ._lcMBot.typing {
        padding: 12px 16px;
        font-style: italic;
        color: ${txtS};
    }

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
        border: 1px solid ${brd};
    }
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
        background: ${color};
        color: #fff;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
    }
    ._lcIB:disabled { opacity: 0.5; cursor: not-allowed; }

    /* Footer */
    #_lcFoot {
        padding: 14px;
        text-align: center;
        font-size: 11px;
        color: ${txtS};
        background: ${bgW};
        border-top: 1px solid ${brd};
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
    }
    #_lcModal.on { display: flex; }
    ._lcMCard {
        background: ${bgCard};
        border-radius: 20px;
        width: 280px;
        padding: 24px;
        text-align: center;
    }
    ._lcMBtn {
        width: 100%;
        padding: 12px;
        background: #e11d48;
        color: #fff;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        margin-top: 16px;
    }

    /* System Message */
    ._lcSys {
        text-align: center;
        font-size: 11px;
        color: ${txtS};
        margin: 8px 0;
        padding: 6px 12px;
        background: ${btnBg};
        border-radius: 20px;
        align-self: center;
    }

    /* RESPONSIVE: Mobile Fullscreen */
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

    /* =========== HTML DENGAN FONT AWESOME ========== */
    const avatarSrc = config.agent_avatar || '';
    const avatarHtml = avatarSrc
        ? `<img src="${avatarSrc}" alt="${agent}" onerror="this.style.display='none';this.parentNode.textContent='${agent.charAt(0).toUpperCase()}'">`
        : agent.charAt(0).toUpperCase();

    const wrap = document.createElement('div');
    wrap.id = '_lc';
    wrap.innerHTML = `
<button id="_lcBtn" aria-label="Buka chat">
    <span id="_lcBtnIco">
    <svg viewBox="0 0 512 512" width="24" height="24" fill="white">
        <path d="M256 32C114.6 32 0 125.1 0 240c0 49.6 21.4 95 57 130.7C44.5 421.1 2.7 466 2.2 466.5c-2.2 2.3-2.8 5.7-1.5 8.7S4.8 480 8 480c66.3 0 116-31.8 140.6-51.4 32.7 12.3 69 19.4 107.4 19.4 141.4 0 256-93.1 256-208S397.4 32 256 32z"/>
    </svg>
</span>
    <span id="_lcBadge"></span>
</button>

        <div id="_lcW" role="dialog" aria-label="Live Chat" aria-modal="true">
            <div id="_lcModal">
                <div class="_lcMCard">
                    <div class="_lcMTtl" style="margin-bottom:8px;">${icn('warning')} Tutup obrolan?</div>
                    <button class="_lcMBtn" id="_lcMConfirm">${icn('check')} Ya, Tutup</button>
                </div>
            </div>
            <div id="_lcHead">
                <div class="_lcHLeft">
                    <button class="_lcHTopBtn" id="_lcFsBtn" title="Fullscreen">${icn('expand')}</button>
                </div>
                <div class="_lcFloatHead">
                    <div class="_lcAv">${avatarHtml}</div>
                    <div class="_lcHInfo">
                        <div class="_lcHName">${agent}</div>
                        <div class="_lcHSub">${icn('circle').replace('width="16"','width="10"').replace('height="16"','height="10"').replace('fill="currentColor"','fill="#2ecc71"')} Online</div>
                    </div>
                </div>
                <div class="_lcHRight">
                    <button class="_lcHTopBtn" id="_lcMinBtn" title="Minimize">${icn('minus')}</button>
                    <button class="_lcHTopBtn" id="_lcCloseBtn" title="Tutup">${icn('times')}</button>
                </div>
            </div>
            <div id="_lcPre">
                <div class="_lcWhiteCard">
                    <div class="_lcInfoBox">
                        ${icn('info')}
                        <div class="_lcInfoTxt">Halo! Silakan isi data di bawah untuk memulai percakapan.</div>
                    </div>
                    <div class="_lcFG">
                        <label class="_lcFLabel">${icn('user')} Nama Lengkap</label>
                        <input class="_lcFI" id="_lcName" type="text" placeholder="Masukkan nama Anda">
                    </div>
                    <div class="_lcFG">
                        <label class="_lcFLabel">${icn('phone')} Nomor WhatsApp</label>
                        <input class="_lcFI" id="_lcPhone" type="tel" placeholder="Contoh: 08123456789">
                    </div>
                    <div class="_lcFG">
                        <label class="_lcFLabel">${icn('tag')} Topik</label>
                        <select class="_lcSelect" id="_lcSubject">
                            <option value="">Pilih topik...</option>
                            <option value="deposit">Deposit</option>
                            <option value="withdraw">Withdraw</option>
                            <option value="reset_password">Reset Password</option>
                            <option value="kendala_lainnya">Kendala Lainnya</option>
                        </select>
                    </div>
                    <div id="_lcPreErr" class="_lcPreErr"></div>
                    <button class="_lcSBtn" id="_lcSBtn">${icn('plane')} Mulai Chat</button>
                </div>
            </div>
            <div id="_lcMsgs"></div>
            <div id="_lcTyp">
                <div class="_lcMW _a">
                    <div class="_lcSmallAv">${avatarHtml}</div>
                    <div class="_lcM _lcMBot typing">${icn('pen')} Mengetik...</div>
                </div>
            </div>
            <div id="_lcIn">
                <div class="_lcInputWrap">
                    <input type="file" id="_lcFile" accept="image/*,application/pdf" style="display:none">
                    <button id="_lcUpBtn" style="background:none;border:none;color:${txtS};font-size:18px;cursor:pointer;">${icn('clip')}</button>
                    <textarea id="_lcTxt" rows="1" placeholder="Ketik pesan..."></textarea>
                </div>
                <button class="_lcIB" id="_lcSend" disabled>${icn('plane')}</button>
            </div>
            <div id="_lcFoot">
                Powered by <a href="#" target="_blank" style="color:${color};text-decoration:none;">LiveChat</a>
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
        gi('_lcBtnIco').innerHTML = '✕';
        updateBadge(0);
        
        if (isMobile() && !isFullscreen) {
            isFullscreen = true;
            gi('_lcW').classList.add('fs');
            gi('_lcFsBtn').innerHTML = '⤡';
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
        gi('_lcBtnIco').innerHTML = icn('comment');
        if (isFullscreen && !isMobile()) {
            isFullscreen = false;
            gi('_lcW').classList.remove('fs');
            gi('_lcFsBtn').innerHTML = '⤢';
        }
    }

    function toggleFs() {
        isFullscreen = !isFullscreen;
        gi('_lcW').classList.toggle('fs', isFullscreen);
        gi('_lcFsBtn').innerHTML = isFullscreen ? '⤡' : '⤢';
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
                        const lastMsg = data.messages[data.messages.length - 1];
                        if (lastMsg && lastMsg.id) {
                            sess.lastMsgId = lastMsg.id;
                            saveSess(sess);
                        }
                    }
                    
                    scrollBot();
                    startPoll();
                } else {
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
        
        if (isMobile() && !isFullscreen) {
            isFullscreen = true;
            const widget = gi('_lcW');
            const fsBtn = gi('_lcFsBtn');
            if (widget) widget.classList.add('fs');
            if (fsBtn) fsBtn.innerHTML = '⤡';
        }
        
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
        gi('_lcSBtn').innerHTML = '⏳ Memulai...';
        gi('_lcSBtn').disabled = true;
            
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
                sess = { 
                    conversationId: d.conversation_id, 
                    visitorId: d.visitor_id, 
                    sessionId: d.session_id,
                    lastMsgId: 0 
                };
                saveSess(sess);
                showChat();
                
                if (d.resume === true && d.messages && d.messages.length > 0) {
                    const msgsContainer = gi('_lcMsgs');
                    if (msgsContainer) msgsContainer.innerHTML = '';
                    
                    d.messages.forEach(m => {
                        appendMsg(m.sender_type, m.content, m.created_at, m.file_url, m.id);
                    });
                    
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
            gi('_lcSBtn').innerHTML = '📤 Mulai Chat';
            gi('_lcSBtn').disabled = false;
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
                    appendSys('🔒 Chat telah ditutup. Terima kasih!');
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
                b.innerHTML = `<a href="${apiBase}/${fileUrl}" target="_blank" style="color:inherit;">📎 Download File</a>`;
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
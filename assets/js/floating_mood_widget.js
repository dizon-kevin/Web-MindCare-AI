(function () {
  'use strict';

  if (window.__MC_FLOATING_CAMERA__) return; // prevent double init
  window.__MC_FLOATING_CAMERA__ = true;

  // Compute app base path like /MindCare-AI so we can call PHP endpoints from any page
  function getAppBasePath() {
    const path = window.location.pathname;
    const m = path.match(/\/MindCare-AI(\/|$)/);
    if (m && m.index !== undefined) {
      return path.slice(0, m.index) + '/MindCare-AI';
    }
    const baseEl = document.querySelector('base[href]');
    if (baseEl) {
      try {
        return new URL(baseEl.getAttribute('href'), window.location.origin).pathname.replace(/\/$/, '');
      } catch (e) {}
    }
    return '';
  }
  const APP_BASE = getAppBasePath();
  const SAVE_MOOD_URL = APP_BASE + '/moodtracker/save_mood.php';

  // Inject face-api.js if not already loaded
  if (typeof faceapi === 'undefined') {
    const s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js';
    document.head.appendChild(s);
    s.onload = () => initWidget();
  } else {
    initWidget();
  }

  async function initWidget() {
    let modelsReady = false;

    // Try to load models (make sure these files exist at this path)
    try {
      const MODEL_URL = APP_BASE + '/faceapi/weights/';
      await Promise.all([
        faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
        faceapi.nets.faceExpressionNet.loadFromUri(MODEL_URL),
      ]);
      modelsReady = true;
    } catch (e) {
      console.error('face-api models failed to load:', e);
    }

    // --- Styles ---
    const style = document.createElement('style');
    style.textContent = `
      .mcw-wrap{
        position:fixed; right:24px; bottom:24px;
        width:320px; height:220px; z-index:99999;
        background:#0b1220; color:#e5e7eb; border:1px solid #1f2937;
        border-radius:14px; box-shadow:0 15px 40px rgba(0,0,0,.35);
        overflow:hidden; user-select:none;
      }
      .mcw-head{
        display:flex; align-items:center; justify-content:space-between;
        padding:8px 10px; cursor:move; background:#0f172a; border-bottom:1px solid #1f2937;
      }
      .mcw-title{ display:flex; align-items:center; gap:8px; font:600 13px system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;}
      .mcw-title .dot{ width:8px; height:8px; border-radius:999px; background:#22c55e; box-shadow:0 0 0 3px rgba(34,197,94,.2);}
      .mcw-ctrls{ display:flex; gap:8px;}
      .mcw-btn{
        font:600 12px system-ui; padding:4px 8px; border-radius:8px; border:1px solid #334155;
        background:#111827; color:#d1d5db; cursor:pointer;
      }
      .mcw-btn:hover{ background:#0b1324; }
      .mcw-body{ position:relative; height:calc(100% - 38px); }
      .mcw-badge{
        position:absolute; left:10px; top:10px; z-index:2;
        font:600 12px system-ui; background:rgba(17,24,39,.7); padding:4px 8px; border-radius:999px; border:1px solid rgba(255,255,255,.08);
        backdrop-filter: blur(6px);
      }
      #mcwFeed{ position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
      .mcw-error{ position:absolute; left:10px; right:10px; top:44px; z-index:2;
        padding:10px; color:#b91c1c; background:#fef2f2; border:1px solid #fee2e2; border-radius:10px; font:600 12px system-ui;
      }
      .mcw-wrap.mcw-max{
        left:0 !important; top:0 !important; right:0 !important; bottom:0 !important;
        width:100vw !important; height:100vh !important; border-radius:0 !important; border:none;
      }
      .mcw-modal-backdrop{
        position:fixed; inset:0; display:none; align-items:center; justify-content:center; z-index:100000;
        background:rgba(2,6,23,.6); backdrop-filter:blur(3px);
      }
      .mcw-modal{ width:min(520px, 92vw); background:#0b1220; border:1px solid #1f2937; border-radius:14px; overflow:hidden; color:#e5e7eb;}
      .mcw-modal-hd{ display:flex; align-items:center; justify-content:space-between; padding:12px 14px; border-bottom:1px solid #1f2937;}
      .mcw-modal-ttl{ font:700 14px system-ui;}
      .mcw-modal-close{ border:1px solid #334155; background:#111827; color:#d1d5db; border-radius:8px; padding:4px 8px; cursor:pointer;}
      .mcw-modal-bd{ padding:16px; font: 400 13px system-ui; }
    `;
    document.head.appendChild(style);

    // --- Modal ---
    const modalBackdrop = document.createElement('div');
    modalBackdrop.className = 'mcw-modal-backdrop';
    modalBackdrop.innerHTML = `
      <div class="mcw-modal" role="dialog" aria-modal="true" aria-labelledby="mcwModalTitle">
        <div class="mcw-modal-hd">
          <div id="mcwModalTitle" class="mcw-modal-ttl">Mood Update</div>
          <button type="button" class="mcw-modal-close" aria-label="Close">Close</button>
        </div>
        <div class="mcw-modal-bd">
          <div id="mcwModalContent">Analyzing your emotions...</div>
        </div>
      </div>`;
    document.body.appendChild(modalBackdrop);
    modalBackdrop.querySelector('.mcw-modal-close').addEventListener('click', () => { modalBackdrop.style.display = 'none'; });
    modalBackdrop.addEventListener('click', (e) => { if (e.target === modalBackdrop) modalBackdrop.style.display = 'none'; });

    function showModal(html) {
      const el = document.getElementById('mcwModalContent');
      if (el) el.innerHTML = html;
      modalBackdrop.style.display = 'flex';
    }

    // --- Widget ---
    const wrap = document.createElement('div');
    wrap.className = 'mcw-wrap';
    wrap.innerHTML = `
      <div class="mcw-head">
        <div class="mcw-title"><span class="dot"></span><span>Mood Tracker</span></div>
        <div class="mcw-ctrls"><button type="button" class="mcw-btn" data-act="size">Maximize</button></div>
      </div>
      <div class="mcw-body">
        <div class="mcw-badge">Emotion: <span id="mcwCur">${modelsReady ? 'Analyzing...' : 'Unavailable'}</span></div>
        <video id="mcwFeed" autoplay playsinline muted></video>
        ${modelsReady ? '' : '<div class="mcw-error">Face model not loaded. Check MODEL_URL or host weights locally.</div>'}
      </div>`;
    document.body.appendChild(wrap);

    // --- Dragging + position persistence ---
    var POS_KEY = 'mcw-pos-v1';
    (function restorePosition() {
      try {
        var saved = JSON.parse(localStorage.getItem(POS_KEY) || '{}');
        if (saved && typeof saved.x === 'number' && typeof saved.y === 'number') {
          wrap.style.left = saved.x + 'px';
          wrap.style.top  = saved.y + 'px';
          wrap.style.right = 'auto';
          wrap.style.bottom = 'auto';
        }
      } catch(_) {}
    })();

    var headEl = wrap.querySelector('.mcw-head');
    var drag = { active:false, offX:0, offY:0 };
    function clamp(val, min, max){ return Math.max(min, Math.min(max, val)); }
    function startDrag(clientX, clientY){
      drag.active = true;
      var rect = wrap.getBoundingClientRect();
      drag.offX = clientX - rect.left;
      drag.offY = clientY - rect.top;
      document.body.style.userSelect = 'none';
    }
    function doDrag(clientX, clientY){
      if (!drag.active || wrap.classList.contains('mcw-max')) return;
      var x = clamp(clientX - drag.offX, 4, window.innerWidth  - wrap.offsetWidth  - 4);
      var y = clamp(clientY - drag.offY, 4, window.innerHeight - wrap.offsetHeight - 4);
      wrap.style.left = x + 'px';
      wrap.style.top  = y + 'px';
      wrap.style.right = 'auto';
      wrap.style.bottom = 'auto';
    }
    function endDrag(){
      if (!drag.active) return;
      drag.active = false;
      document.body.style.userSelect = '';
      try {
        var rect = wrap.getBoundingClientRect();
        localStorage.setItem(POS_KEY, JSON.stringify({ x: rect.left, y: rect.top }));
      } catch(_) {}
    }
    if (headEl) {
      headEl.addEventListener('mousedown', function(e){
        var tgt = e.target;
        if (tgt && tgt.closest && tgt.closest('.mcw-ctrls')) return;
        startDrag(e.clientX, e.clientY);
      });
      headEl.addEventListener('touchstart', function(e){
        var tgt2 = e.target;
        if (tgt2 && tgt2.closest && tgt2.closest('.mcw-ctrls')) return;
        var t = e.touches[0]; if (!t) return;
        startDrag(t.clientX, t.clientY);
      }, {passive:true});
    }
    window.addEventListener('mousemove', function(e){ doDrag(e.clientX, e.clientY); });
    window.addEventListener('mouseup', endDrag);
    window.addEventListener('touchmove', function(e){
      var t = e.touches && e.touches[0]; if (!t) return;
      doDrag(t.clientX, t.clientY);
    }, {passive:true});
    window.addEventListener('touchend', endDrag);

    // Maximize / Minimize
    var sizeBtn = wrap.querySelector('[data-act="size"]');
    if (sizeBtn) {
      sizeBtn.addEventListener('click', function () {
        var isMax = wrap.classList.toggle('mcw-max');
        sizeBtn.textContent = isMax ? 'Minimize' : 'Maximize';
        if (!isMax) {
          const rect = wrap.getBoundingClientRect();
          const x = clamp(rect.left, 4, window.innerWidth  - rect.width  - 4);
          const y = clamp(rect.top,  4, window.innerHeight - rect.height - 4);
          wrap.style.left = x + 'px';
          wrap.style.top  = y + 'px';
        }
      });
    }

    // If models failed, stop here (widget still shows with error)
    if (!modelsReady) return;

    // --- Camera & analysis ---
    let emotionCounts = {};
    let totalCount = 0;

    try {
      const stream = await navigator.mediaDevices.getUserMedia({ video: true });
      const videoEl = document.getElementById('mcwFeed');
      if (videoEl) {
        videoEl.srcObject = stream;
        try { await videoEl.play(); } catch(_) {}
        if (videoEl.readyState >= 2) {
          analyzeFrameLoop(videoEl);
        } else {
          videoEl.addEventListener('loadeddata', () => analyzeFrameLoop(videoEl), { once: true });
        }
      }
    } catch (err) {
      console.error("Camera access failed:", err);
      alert("Please allow camera access for Mood Tracker.");
    }

    async function analyzeFrameLoop(videoEl) {
      const detOpts = new faceapi.TinyFaceDetectorOptions({
        inputSize: 320,       // larger = better detection (heavier)
        scoreThreshold: 0.3   // easier to get a face
      });

      const detections = await faceapi
        .detectSingleFace(videoEl, detOpts)
        .withFaceExpressions();

      if (detections && detections.expressions) {
        // Skip weak face detections
        if (!detections.detection || detections.detection.score < 0.40) {
          requestAnimationFrame(() => analyzeFrameLoop(videoEl));
          return;
        }

        const expr = detections.expressions || {};
        const sorted = Object.entries(expr).sort((a,b) => b[1] - a[1]);
        let [dominant, domVal] = sorted[0] || ['neutral', 1];
        const [, secondVal]    = sorted[1] || ['neutral', 0];

        // Skip ultra-low-confidence frames
        if (domVal < 0.20) {
          requestAnimationFrame(() => analyzeFrameLoop(videoEl));
          return;
        }

        // De-bias neutral if it barely wins
        if (dominant === 'neutral' && (domVal - (secondVal || 0)) < 0.10 && sorted[1]) {
          dominant = sorted[1][0];
          domVal   = sorted[1][1];
        }

        const el = document.getElementById('mcwCur');
        if (el) el.textContent = dominant.charAt(0).toUpperCase() + dominant.slice(1);
        totalCount++;
        emotionCounts[dominant] = (emotionCounts[dominant] || 0) + 1;
      }

      requestAnimationFrame(() => analyzeFrameLoop(videoEl));
    }

    function summarizeEmotions() {
      const percentages = {};
      for (const [emo, count] of Object.entries(emotionCounts)) {
        percentages[emo] = (count / totalCount) * 100;
      }
      let dominant = 'neutral';
      let maxPct = 0;
      for (const [emo, pct] of Object.entries(percentages)) {
        if (pct > maxPct) { dominant = emo; maxPct = pct; }
      }
      return { dominant, percentages };
    }

    function formatPills(map, dominantKey) {
      if (!map || typeof map !== 'object') return '<div class="mcw-muted">No data</div>';
      const order = Object.keys(map).sort((a,b)=> (map[b]||0) - (map[a]||0));
      return '<div class="mcw-pills">' + order.map(k=>{
        const cls = (k===dominantKey) ? 'mcw-pill dom' : 'mcw-pill';
        const pct = (Number(map[k])||0).toFixed(2);
        return `<span class="${cls}">${k}: ${pct}%</span>`;
      }).join('') + '</div>';
    }

    // --- Send summary periodically (TEST MODE: 2s) ---
    const WINDOW_SECONDS = 300;     // change to 300 for 5 minutes, etc.
    const MIN_GOOD_FRAMES = 1;    // 1 frame is enough for testing

    async function sendSummary() {
      if (totalCount < MIN_GOOD_FRAMES) {
        // not enough signal this window; skip send
        emotionCounts = {};
        totalCount = 0;
        return;
      }

      const { dominant, percentages } = summarizeEmotions();
      const html = `
        <div><strong>Dominant emotion:</strong> ${dominant} (${(percentages[dominant]||0).toFixed(2)}%)</div>
        <div class="mcw-muted">Distribution for the last period:</div>
        ${formatPills(percentages, dominant)}
      `;
      showModal(html);

      try {
        const form = new URLSearchParams();
        form.set('emotion', dominant);
        form.set('percentage', String(percentages[dominant]||0));
        form.set('duration', String(WINDOW_SECONDS));
        form.set('distribution', JSON.stringify(percentages));

        await fetch(SAVE_MOOD_URL, {
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body: form.toString(),
          credentials: 'include'
        });
      } catch(_) {}

      // reset the counting window
      emotionCounts = {};
      totalCount = 0;
    }

    // First send after WINDOW_SECONDS, then every WINDOW_SECONDS
    setTimeout(() => {
      sendSummary();
      setInterval(sendSummary, WINDOW_SECONDS * 1000);
    }, WINDOW_SECONDS * 1000);
  }
})();

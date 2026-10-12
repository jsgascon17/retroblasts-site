/**
 * "Report a problem" button, on every page.
 *
 * The point is the context: the reporter types what went wrong, and the page
 * attaches where it happened - which page, which game, what size screen, which
 * browser, touch or not, and their username if signed in. That is the half of a
 * bug report people never think to include and we cannot guess afterwards.
 *
 * Sits bottom-LEFT: the Ko-fi button already owns bottom-right.
 */
(function () {
  'use strict';
  if (window.__reportWidget) return;          // some pages load scripts twice
  window.__reportWidget = true;

  var PAGE = location.pathname.split('/').pop() || 'index.html';
  var GAME = PAGE.replace(/\.html$/, '');

  var css = document.createElement('style');
  css.textContent = [
    '.rb-report-btn{position:fixed;bottom:20px;left:20px;z-index:998;display:flex;align-items:center;gap:7px;',
    'background:rgba(0,0,0,.75);color:#fff;border:2px solid rgba(255,215,0,.5);border-radius:30px;',
    'padding:9px 16px;font:inherit;font-size:.85rem;font-weight:bold;cursor:pointer;backdrop-filter:blur(6px)}',
    '.rb-report-btn:hover{border-color:#ffd700;color:#ffd700}',
    '.rb-report-overlay{position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:10050;display:none;',
    'align-items:center;justify-content:center;padding:16px}',
    '.rb-report-overlay.open{display:flex}',
    '.rb-report-box{background:linear-gradient(135deg,#1a1a2e,#16213e);border:3px solid #ffd700;border-radius:18px;',
    'width:100%;max-width:460px;max-height:85vh;overflow-y:auto;padding:22px;color:#fff;box-sizing:border-box}',
    '.rb-report-box h3{color:#ffd700;margin:0 0 6px;font-size:1.15rem}',
    '.rb-report-box p.rb-sub{color:#98a2b3;font-size:.85rem;margin:0 0 14px;line-height:1.5}',
    '.rb-report-box textarea{width:100%;min-height:110px;padding:11px;border-radius:10px;border:2px solid rgba(255,255,255,.2);',
    'background:rgba(0,0,0,.45);color:#fff;font:inherit;font-size:.95rem;resize:vertical;box-sizing:border-box}',
    '.rb-report-box textarea:focus{outline:none;border-color:#ffd700}',
    '.rb-report-ctx{margin:12px 0 4px;padding:10px 12px;background:rgba(255,215,0,.07);border:1px solid rgba(255,215,0,.25);',
    'border-radius:10px;font-size:.78rem;color:#c8d0dc;line-height:1.6;word-break:break-word}',
    '.rb-report-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}',
    '.rb-report-actions button{flex:1;min-width:120px;padding:11px;border:none;border-radius:10px;font:inherit;',
    'font-weight:bold;cursor:pointer}',
    '.rb-send{background:linear-gradient(135deg,#ffd700,#ff8c00);color:#000}',
    '.rb-cancel{background:rgba(255,255,255,.12);color:#fff}',
    '.rb-report-msg{margin-top:10px;font-size:.88rem;min-height:1.2em}',
    '@media (max-width:600px){.rb-report-btn{bottom:14px;left:14px;padding:8px 13px;font-size:.8rem}}'
  ].join('');
  document.head.appendChild(css);

  function context() {
    return {
      page: PAGE,
      game: GAME,
      viewport: window.innerWidth + 'x' + window.innerHeight,
      screen: (screen.width || 0) + 'x' + (screen.height || 0),
      device: navigator.userAgent.slice(0, 200),
      touch: ('ontouchstart' in window) || navigator.maxTouchPoints > 0
    };
  }

  var btn = document.createElement('button');
  btn.className = 'rb-report-btn';
  btn.type = 'button';
  btn.innerHTML = '<span aria-hidden="true">🐞</span> Report a problem';
  btn.setAttribute('aria-label', 'Report a problem with this page');

  var overlay = document.createElement('div');
  overlay.className = 'rb-report-overlay';
  overlay.innerHTML =
    '<div class="rb-report-box" role="dialog" aria-modal="true" aria-labelledby="rbReportTitle">' +
      '<h3 id="rbReportTitle">🐞 Report a problem</h3>' +
      '<p class="rb-sub">What went wrong? Anything you can tell us helps &mdash; what you were doing, what you expected, what happened instead.</p>' +
      '<textarea id="rbReportText" maxlength="2000" placeholder="e.g. the knives stop throwing after stage 4"></textarea>' +
      '<div class="rb-report-ctx" id="rbReportCtx"></div>' +
      '<div class="rb-report-actions">' +
        '<button type="button" class="rb-send" id="rbReportSend">Send report</button>' +
        '<button type="button" class="rb-cancel" id="rbReportCancel">Cancel</button>' +
      '</div>' +
      '<div class="rb-report-msg" id="rbReportMsg"></div>' +
    '</div>';

  function open() {
    var c = context();
    // Shown, not hidden: people should see exactly what they are sending.
    document.getElementById('rbReportCtx').textContent =
      'Sent with your report: page ' + c.page + ' • screen ' + c.viewport +
      ' • ' + (c.touch ? 'touch' : 'mouse') + ' • your browser version. No personal details.';
    overlay.classList.add('open');
    document.getElementById('rbReportText').focus();
  }
  function close() {
    overlay.classList.remove('open');
    document.getElementById('rbReportMsg').textContent = '';
  }

  async function send() {
    var text = document.getElementById('rbReportText').value.trim();
    var msg = document.getElementById('rbReportMsg');
    var sendBtn = document.getElementById('rbReportSend');
    if (text.length < 5) { msg.style.color = '#ff9b9b'; msg.textContent = 'Please say a little more about what happened.'; return; }
    sendBtn.disabled = true; msg.style.color = '#98a2b3'; msg.textContent = 'Sending…';
    try {
      var resp = await fetch('api/report-bug.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ message: text, context: context() })
      });
      var data = await resp.json();
      if (data.success) {
        msg.style.color = '#7ee787';
        msg.textContent = 'Thanks! Sent. We read every one of these.';
        document.getElementById('rbReportText').value = '';
        setTimeout(close, 1600);
      } else {
        msg.style.color = '#ff9b9b';
        msg.textContent = data.error || 'Could not send that. Please try again.';
      }
    } catch (e) {
      msg.style.color = '#ff9b9b';
      msg.textContent = 'Could not reach the server. Please try again.';
    }
    sendBtn.disabled = false;
  }

  function mount() {
    document.body.appendChild(btn);
    document.body.appendChild(overlay);
    btn.addEventListener('click', open);
    document.getElementById('rbReportCancel').addEventListener('click', close);
    document.getElementById('rbReportSend').addEventListener('click', send);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && overlay.classList.contains('open')) close(); });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount);
  else mount();
})();

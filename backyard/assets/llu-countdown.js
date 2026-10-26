(() => {
  'use strict';

  const countdown = document.getElementById('llu-countdown');
  if (countdown) {
    const target = Date.parse(countdown.dataset.target || '');
    const fields = {};
    ['days', 'hours', 'minutes', 'seconds'].forEach(unit => {
      fields[unit] = countdown.querySelector(`[data-unit="${unit}"]`);
    });
    const pad = n => String(n).padStart(2, '0');
    let timer = null;

    const tick = () => {
      const left = Math.max(0, Math.floor((target - Date.now()) / 1000));
      if (fields.days) fields.days.textContent = String(Math.floor(left / 86400));
      if (fields.hours) fields.hours.textContent = pad(Math.floor((left % 86400) / 3600));
      if (fields.minutes) fields.minutes.textContent = pad(Math.floor((left % 3600) / 60));
      if (fields.seconds) fields.seconds.textContent = pad(left % 60);
      if (left === 0) {
        countdown.classList.add('is-finished');
        if (timer) clearInterval(timer);
      }
    };

    if (!Number.isNaN(target)) {
      tick();
      timer = setInterval(tick, 1000);
    }
  }

  const typewriter = document.getElementById('llu-typewriter');
  const source = document.getElementById('llu-quotes');
  if (!typewriter || !source) return;

  let quotes = [];
  try {
    quotes = JSON.parse(source.textContent || '[]');
  } catch (error) {
    quotes = [];
  }
  quotes = Array.isArray(quotes) ? quotes.filter(q => typeof q === 'string' && q.trim() !== '') : [];
  if (!quotes.length) {
    typewriter.hidden = true;
    return;
  }

  const textEl = typewriter.querySelector('.llu-typewriter-text');
  const srEl = typewriter.querySelector('.llu-sr-only');
  const reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const TYPE_MS = 55;
  const DELETE_MS = 28;
  const HOLD_MS = 2800;
  const PAUSE_MS = 450;

  // Alle Sprüche einmal in zufälliger Reihenfolge, ohne denselben Spruch direkt zu wiederholen.
  let queue = [];
  let last = null;
  const nextQuote = () => {
    if (!queue.length) {
      queue = quotes.slice();
      for (let i = queue.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [queue[i], queue[j]] = [queue[j], queue[i]];
      }
      if (queue.length > 1 && queue[0] === last) queue.push(queue.shift());
    }
    last = queue.shift();
    return last;
  };

  const show = text => {
    if (textEl) textEl.textContent = text;
  };

  const run = () => {
    const quote = nextQuote();
    if (srEl) srEl.textContent = quote;

    if (reducedMotion) {
      show(quote);
      setTimeout(run, HOLD_MS + 3000);
      return;
    }

    const chars = Array.from(quote);
    let i = 0;
    const type = () => {
      i++;
      show(chars.slice(0, i).join(''));
      if (i < chars.length) {
        setTimeout(type, TYPE_MS + Math.random() * 45);
      } else {
        setTimeout(erase, HOLD_MS);
      }
    };
    const erase = () => {
      i--;
      show(chars.slice(0, Math.max(0, i)).join(''));
      if (i > 0) {
        setTimeout(erase, DELETE_MS);
      } else {
        setTimeout(run, PAUSE_MS);
      }
    };
    type();
  };

  run();
})();

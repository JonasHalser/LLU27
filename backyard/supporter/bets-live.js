(() => {
  'use strict';
  const nodes = [...document.querySelectorAll('[data-market-live]')];
  const boards = [...document.querySelectorAll('[data-live-board]')];
  if (!nodes.length && !boards.length) return;

  const element = (tag, className, value) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (value !== undefined) node.textContent = value;
    return node;
  };

  function render(container, market) {
    container.replaceChildren();
    if (!market) {
      container.append(element('p', 'mission-note', 'Pool ausgewertet oder geschlossen.'));
      return;
    }
    const head = element('div', 'bet-live-head');
    head.append(element('strong', '', `${market.total.toLocaleString('de-DE')} Coins im Pool`));
    head.append(element('small', '', `${Object.values(market.options).reduce((n, o) => n + o.bets.length, 0)} Einsätze`));
    container.append(head);

    const options = Object.values(market.options).sort((a, b) => b.stake - a.stake);
    if (!options.length) {
      container.append(element('p', 'mission-note', 'Noch keine Einsätze.'));
      return;
    }
    const graph = element('div', 'bet-live-graph');
    for (const option of options) {
      const group = element('section', 'bet-live-option');
      const label = element('div', 'bet-live-option-label');
      label.append(element('strong', '', option.label));
      label.append(element('span', '', `${option.stake.toLocaleString('de-DE')} Coins · ${option.share.toLocaleString('de-DE')} %`));
      const track = element('div', 'bet-live-track');
      const fill = element('span', 'bet-live-fill');
      fill.style.width = `${Math.max(0, Math.min(100, Number(option.share) || 0))}%`;
      track.append(fill);
      group.append(label, track);
      const bets = element('ul', 'bet-live-list');
      for (const bet of option.bets) {
        bets.append(element('li', '', `${bet.supporter} · ${bet.stake.toLocaleString('de-DE')} Coins`));
      }
      group.append(bets);
      graph.append(group);
    }
    container.append(graph);
  }

  function renderBoard(container, markets) {
    container.replaceChildren();
    if (!markets.length) {
      container.append(element('p', 'mission-note', 'Noch keine offenen Wettpools.'));
      return;
    }
    for (const market of markets) {
      const card = element('article', 'bet-live-market');
      card.append(element('h3', '', market.title));
      const pool = element('div', 'bet-live');
      render(pool, market);
      card.append(pool);
      container.append(card);
    }
  }

  async function refresh() {
    try {
      const response = await fetch(new URL('bets-live.php', window.location.href), {
        credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }
      });
      if (!response.ok) return;
      const payload = await response.json();
      const markets = new Map((payload.markets || []).map(market => [String(market.id), market]));
      for (const node of nodes) render(node, markets.get(node.dataset.marketLive));
      for (const board of boards) renderBoard(board, payload.markets || []);
    } catch (_) {
      // The last rendered pool stays visible if a refresh briefly fails.
    }
  }

  refresh();
  window.setInterval(refresh, 15000);
})();

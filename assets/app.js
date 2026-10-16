const select = (selector) => document.querySelector(selector);
const numberFormatter = new Intl.NumberFormat('sr-Latn-RS');
const percentFormatter = new Intl.NumberFormat('sr-Latn-RS', {
  maximumFractionDigits: 2,
  minimumFractionDigits: 2,
});
const state = {
  history: [],
  refreshMinutes: 10,
  selectedTime: '',
  loading: false,
  expandedTables: new Set(),
};

function escapeHtml(value) {
  const entities = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
  return String(value ?? '').replace(/[&<>"']/g, (character) => entities[character]);
}

function safeColor(value) {
  return /^#[a-f0-9]{6}$/i.test(value) ? value : '#8c99aa';
}

function formatDateTime(value) {
  return new Date(value).toLocaleString('sr-Latn-RS', {
    timeZone: 'Europe/Belgrade',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    timeZoneName: 'short',
  });
}

function dateKey(value) {
  return new Intl.DateTimeFormat('sv-SE', {
    timeZone: 'Europe/Belgrade',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(new Date(value));
}

async function fetchJson(url) {
  const response = await fetch(url, { cache: 'no-store' });
  const data = await response.json();
  if (!response.ok) {
    throw new Error(data.error || 'Podaci nisu dostupni.');
  }
  return data;
}

function renderHeader(sourceId, sourceName, mode) {
  const labels = {
    rik: { number: '01', abbreviation: 'RIK', title: 'RIK-a' },
    studenti: { number: '02', abbreviation: 'ST', title: 'Studentske liste' },
    sns: { number: '03', abbreviation: 'SNS', title: 'SNS-a' },
  };
  const label = labels[sourceId];
  const description =
    sourceId === 'rik' ? 'Podaci iz izvora RIK-a' : 'Rezultati koje prijavljuje politički akter';
  return `
    <header class="section-header">
      <span class="source-icon">${label.abbreviation}</span>
      <div>
        <h2>${label.number} / Rezultati na osnovu ${label.title}</h2>
        <p>${escapeHtml(sourceName)} · ${description}</p>
      </div>
      <span class="tag">${mode === 'demo' ? 'TEST PODACI' : 'NEZVANIČNA PROJEKCIJA'}</span>
    </header>
  `;
}

function renderStats(result) {
  const coverage = (100 * result.processedStations) / result.totalStations;
  return `
    <div class="stats">
      <div class="stat">
        <small>Obrađena biračka mesta</small>
        <strong>${percentFormatter.format(coverage)}<em>%</em></strong>
      </div>
      <div class="stat">
        <small>Prijavljen broj glasalih</small>
        <strong>${numberFormatter.format(result.ballotsCast)}</strong>
      </div>
      <div class="stat">
        <small>Nevažeći listići</small>
        <strong>${numberFormatter.format(result.invalidBallots)}</strong>
      </div>
      <div class="stat">
        <small>Većina u parlamentu</small>
        <strong>126<em>/ 250 mandata</em></strong>
      </div>
    </div>
  `;
}

function renderSeatMap(lists) {
  const seats = [];
  for (const list of lists) {
    for (let index = 0; index < list.seats; index++) {
      const description = escapeHtml(`${list.name} · mandat ${index + 1}/${list.seats}`);
      seats.push(`
        <span class="seat" style="background: ${safeColor(list.color)}"
          title="${description}"></span>
      `);
    }
  }
  while (seats.length < 250) {
    seats.push('<span class="seat" title="Neraspodeljeno mesto"></span>');
  }
  const seatRows = Array.from({ length: 14 }, () => []);
  let seatIndex = 0;

  // Fill each column from top to bottom before moving to the next column.
  for (let column = 0; column < 18; column++) {
    for (let row = 0; row < 14; row++) {
      // The centered last row has no seat in the two outermost columns.
      if (row === 13 && (column === 0 || column === 17)) {
        continue;
      }

      seatRows[row].push(seats[seatIndex]);
      seatIndex++;
    }
  }

  const rows = seatRows.map((row) => `<div class="seat-row">${row.join('')}</div>`);
  const description = lists.map((list) => `${list.name}: ${list.seats}`).join(', ');
  return `
    <div>
      <p class="visual-title">
        Projekcija sastava parlamenta <span>1 ćelija = 1 mandat</span>
      </p>
      <div class="seat-map" role="img" aria-label="250 mesta: ${escapeHtml(description)}">
        ${rows.join('')}
      </div>
      <div class="chamber-label">NARODNA SKUPŠTINA · 250 MESTA</div>
    </div>
  `;
}

function renderChart(lists, allocation) {
  let start = 0;
  const segments = lists
    .filter((list) => list.seats > 0)
    .map((list) => {
      const end = start + (list.seats / 250) * 100;
      const segment = `${safeColor(list.color)} ${start}% ${end}%`;
      start = end;
      return segment;
    });
  if (start < 100) {
    segments.push(`#e7ebef ${start}% 100%`);
  }
  const legend = lists
    .map(
      (list) => `
    <li>
      <span class="swatch" style="background: ${safeColor(list.color)}"></span>
      <span>${escapeHtml(list.name)}</span>
      <strong>${list.seats}</strong>
    </li>
  `,
    )
    .join('');
  return `
    <div class="chart-side">
      <p class="visual-title">Raspodela mandata <span>D’Ontov sistem</span></p>
      <div class="chart-content">
        <div class="donut" style="background: conic-gradient(${segments.join(',')})"
          role="img" aria-label="Raspodela mandata; vrednosti su u legendi i tabeli">
          <div class="donut-center">
            <strong>${250 - allocation.unallocated}</strong><span>MANDATA</span>
          </div>
        </div>
        <ul class="legend">${legend}</ul>
      </div>
    </div>
  `;
}

function renderTable(lists, ballotsCast, sourceId) {
  const rows = lists
    .map((list) => {
      const percentage = ballotsCast ? (100 * list.votes) / ballotsCast : 0;
      return `
      <tr>
        <td>
          <span class="swatch" style="background: ${safeColor(list.color)}"></span>
          ${escapeHtml(list.name)} ${list.minority ? '<small>MANJINSKA</small>' : ''}
        </td>
        <td>${numberFormatter.format(list.votes)}</td>
        <td>
          ${percentFormatter.format(percentage)}%
          <span class="bar">
            <i style="width: ${percentage}%; background: ${safeColor(list.color)}"></i>
          </span>
        </td>
        <td class="mandates">${list.seats}</td>
      </tr>
    `;
    })
    .join('');
  return `
    <details class="results-table" ${state.expandedTables.has(sourceId) ? 'open' : ''}>
      <summary>
        <span class="table-label-closed">Prikaži rezultate po listama</span>
        <span class="table-label-open">Sakrij rezultate po listama</span>
        <span class="table-list-count">${lists.length} lista</span>
        <span class="table-chevron" aria-hidden="true">⌄</span>
      </summary>
      <div class="table-scroll">
        <table>
        <thead>
          <tr>
            <th>IZBORNA LISTA</th><th>GLASOVI</th><th>UDEO GLASOVA</th><th>MANDATI</th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
        </table>
      </div>
    </details>
  `;
}

function renderSourceFooter(snapshot, status, mode) {
  const stale =
    !state.selectedTime &&
    mode === 'live' &&
    Date.now() - new Date(snapshot.capturedAt).getTime() > state.refreshMinutes * 120000;
  const failed = mode === 'live' && !state.selectedTime && status?.ok === false;
  const links = (snapshot.evidence || [])
    .map((evidence) => {
      const url = /^https?:\/\//.test(evidence.url) ? evidence.url : '#';
      return `<a href="${escapeHtml(url)}" target="_blank" rel="noopener">Pogledaj izvor ↗</a>`;
    })
    .join(' · ');
  return `
    <div class="source-footer">
      <span>
        Objava izvora: ${formatDateTime(snapshot.result.reportedAt)} ·
        Sačuvano: ${formatDateTime(snapshot.capturedAt)}
        ${stale ? ' · ⚠ Podaci nisu skoro promenjeni' : ''}
        ${failed ? ' · ⚠ Poslednja provera izvora nije uspela' : ''}
      </span>
      <span>${links || 'Izmišljeni podaci za testiranje dizajna'}</span>
    </div>
  `;
}

function renderSourceSection(sourceId, sourceName, snapshot, status, mode) {
  const header = renderHeader(sourceId, sourceName, mode);
  if (!snapshot) {
    const missing = state.selectedTime
      ? 'sačuvanih podataka za izabrano vreme'
      : 'podataka iz ovog izvora';
    const instruction =
      mode === 'demo'
        ? 'Pokrenite generator test podataka u administraciji.'
        : 'Izvori se podešavaju u administraciji.';
    return `
      <section class="result-section">
        ${header}
        <div class="empty">Još nema ${missing}.<br>${instruction}</div>
      </section>
    `;
  }
  const result = snapshot.result;
  const lists = snapshot.registry
    .map((list) => ({
      ...list,
      votes: result.lists.find((row) => row.id === list.id)?.votes || 0,
      seats: result.allocation.seats[list.id] || 0,
    }))
    .sort((first, second) => second.votes - first.votes);
  const warning = result.allocation.warning
    ? `<p class="alert error">${escapeHtml(result.allocation.warning)}</p>`
    : '';
  return `
    <section class="result-section" id="${sourceId}">
      ${header}
      ${renderStats(result)}
      <div class="visuals">
        ${renderSeatMap(lists)}
        ${renderChart(lists, result.allocation)}
      </div>
      ${warning}
      ${renderTable(lists, result.ballotsCast, sourceId)}
      ${renderSourceFooter(snapshot, status, mode)}
    </section>
  `;
}

function updateHistoryTimes() {
  const chosenDay = select('#history-date').value;
  const dates = [
    ...new Set(
      state.history
        .filter((snapshot) => dateKey(snapshot.capturedAt) === chosenDay)
        .map((snapshot) => snapshot.capturedAt),
    ),
  ].sort((first, second) => new Date(second) - new Date(first));
  const options = dates
    .map(
      (date) => `
    <option value="${escapeHtml(date)}" ${date === state.selectedTime ? 'selected' : ''}>
      ${formatDateTime(date)}
    </option>
  `,
    )
    .join('');
  select('#history-time').innerHTML = `
    <option value="">Izaberite sačuvani presek</option>${options}
  `;
}

async function loadResults() {
  if (state.loading) {
    return;
  }
  state.loading = true;
  try {
    const query = state.selectedTime ? `?at=${encodeURIComponent(state.selectedTime)}` : '';
    const data = await fetchJson(`api.php${query}`);
    state.refreshMinutes = data.interval;
    select('#election-date').textContent = new Date(
      `${data.electionDate}T12:00:00`,
    ).toLocaleDateString('sr-Latn-RS');
    const notice = select('#mode-notice');
    notice.className = `notice${data.mode === 'live' ? ' live' : ''}`;
    notice.textContent =
      data.mode === 'demo'
        ? '◈ DEMO REŽIM · Svi prikazani rezultati su izmišljeni i služe za testiranje.' +
          ' Ne predstavljaju stvarne izborne rezultate.'
        : '● PRAĆENJE IZVORA · Prikazane vrednosti su prijavljeni rezultati pojedinačnih izvora.' +
          ' Mandati su nezvanična projekcija.';
    select('#results').innerHTML = Object.entries(data.sources)
      .map(([sourceId, name]) =>
        renderSourceSection(
          sourceId,
          name,
          data.snapshots[sourceId],
          data.status[sourceId],
          data.mode,
        ),
      )
      .join('');
    select('#view-label').textContent = state.selectedTime
      ? `Istorijski presek · ${formatDateTime(state.selectedTime)}`
      : 'Najnoviji rezultati';
    select('#refresh-status').textContent = state.selectedTime
      ? 'Istorijski prikaz: poslednje sačuvano stanje svakog izvora do izabranog trenutka.'
      : `Provera prikaza: ${formatDateTime(new Date())} · Preuzimanje svakih ${state.refreshMinutes} min.`;
    select('#error').hidden = true;
    state.history = await fetchJson('api.php?action=history');
    const days = state.history.map((snapshot) => dateKey(snapshot.capturedAt)).sort();
    if (days.length > 0) {
      const dateInput = select('#history-date');
      dateInput.min = days[0];
      dateInput.max = days.at(-1);
      dateInput.value ||= days.at(-1);
    }
    updateHistoryTimes();
  } catch (error) {
    select('#error').textContent = `Prikaz nije osvežen: ${error.message}`;
    select('#error').hidden = false;
  } finally {
    state.loading = false;
  }
}

select('#results').addEventListener(
  'toggle',
  (event) => {
    if (!event.target.matches('.results-table')) {
      return;
    }

    const sourceId = event.target.closest('.result-section').id;
    if (event.target.open) {
      state.expandedTables.add(sourceId);
    } else {
      state.expandedTables.delete(sourceId);
    }
  },
  true,
);

select('#history-date').addEventListener('change', updateHistoryTimes);
select('#history-time').addEventListener('change', (event) => {
  if (event.target.value) {
    state.selectedTime = event.target.value;
    loadResults();
  }
});
select('#live-button').addEventListener('click', () => {
  state.selectedTime = '';
  loadResults();
});
loadResults();
setInterval(() => {
  if (!state.selectedTime && !document.hidden) {
    loadResults();
  }
}, 30000);

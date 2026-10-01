<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Журнал уведомлений</title>
    <style>
        :root {
            --bg: #f4f6f8;
            --card: #ffffff;
            --ink: #1e293b;
            --muted: #64748b;
            --line: #e2e8f0;
            --blue: #2563eb;
            --blue-dark: #1d4ed8;
            --green-bg: #dcfce7;
            --green: #166534;
            --red-bg: #fee2e2;
            --red: #b91c1c;
            --yellow-bg: #fef9c3;
            --yellow: #854d0e;
            --shadow: 0 1px 2px rgba(15, 23, 42, .06), 0 8px 24px rgba(15, 23, 42, .04);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: var(--ink);
        }
        button, input, select { font: inherit; }
        .topbar {
            background: #0f172a;
            color: #fff;
            padding: 16px 24px;
        }
        .topbar-inner, .page {
            max-width: 1180px;
            margin: 0 auto;
        }
        .topbar h1 {
            margin: 0 0 12px;
            font-size: 20px;
            font-weight: 650;
        }
        .token-row {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .token-row label { font-size: 13px; color: #cbd5e1; }
        .token-row input[type="text"] {
            flex: 1 1 320px;
            min-width: 220px;
            background: #1e293b;
            color: #f8fafc;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 9px 12px;
        }
        .token-meta { font-size: 13px; color: #94a3b8; }
        .token-meta strong { color: #e2e8f0; font-weight: 600; }
        .page { padding: 24px; }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 12px;
            box-shadow: var(--shadow);
        }
        .filters { padding: 16px; margin-bottom: 16px; }
        .filters-grid {
            display: grid;
            grid-template-columns: 160px 1.4fr 160px 160px auto;
            gap: 12px;
            align-items: end;
        }
        label.field { display: flex; flex-direction: column; gap: 6px; font-size: 13px; color: var(--muted); }
        input, select {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 9px 10px;
            background: #fff;
            color: var(--ink);
        }
        .actions { display: flex; gap: 8px; }
        button {
            border: 0;
            border-radius: 8px;
            padding: 9px 14px;
            cursor: pointer;
        }
        button:disabled { opacity: .45; cursor: not-allowed; }
        .btn-primary { background: var(--blue); color: #fff; }
        .btn-primary:hover:not(:disabled) { background: var(--blue-dark); }
        .btn-ghost { background: #e2e8f0; color: #0f172a; }
        .btn-danger { background: #dc2626; color: #fff; }
        .banner {
            display: none;
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 10px;
            background: #fff7ed;
            color: #9a3412;
            border: 1px solid #fed7aa;
        }
        .banner.error { background: var(--red-bg); color: var(--red); border-color: #fecaca; }
        .banner.ok { background: var(--green-bg); color: var(--green); border-color: #bbf7d0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 12px 14px; border-bottom: 1px solid var(--line); font-size: 14px; }
        th { font-size: 12px; letter-spacing: .04em; text-transform: uppercase; color: var(--muted); background: #f8fafc; }
        tbody tr { cursor: pointer; }
        tbody tr:hover { background: #f8fafc; }
        .badge {
            display: inline-block;
            border-radius: 999px;
            padding: 3px 10px;
            font-size: 12px;
            font-weight: 650;
        }
        .badge.sent { background: var(--green-bg); color: var(--green); }
        .badge.failed { background: var(--red-bg); color: var(--red); }
        .badge.pending { background: var(--yellow-bg); color: var(--yellow); }
        .empty, .loading { padding: 36px 16px; text-align: center; color: var(--muted); }
        .pager {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
        }
        .pager-buttons { display: flex; gap: 8px; }
        .modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .45);
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .modal-backdrop.open { display: flex; }
        .modal {
            width: min(720px, 100%);
            max-height: min(86vh, 820px);
            overflow: auto;
            background: #fff;
            border-radius: 14px;
            box-shadow: var(--shadow);
        }
        .modal header, .modal .body, .modal footer { padding: 16px 18px; }
        .modal header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--line);
        }
        .modal h2 { margin: 0; font-size: 18px; }
        .modal footer { border-top: 1px solid var(--line); display: flex; justify-content: flex-end; gap: 8px; }
        pre {
            margin: 0;
            background: #0f172a;
            color: #e2e8f0;
            border-radius: 10px;
            padding: 12px;
            overflow: auto;
            font-size: 12px;
            line-height: 1.5;
        }
        .meta-line { margin: 0 0 12px; color: var(--muted); }
        .meta-line strong { color: var(--ink); }
        @media (max-width: 900px) {
            .filters-grid { grid-template-columns: 1fr 1fr; }
            .actions { grid-column: 1 / -1; }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <h1>Журнал уведомлений</h1>
            <div class="token-row">
                <label for="jwt">JWT</label>
                <input id="jwt" type="text" placeholder="Вставьте access-токен Admin или Analyst" autocomplete="off" spellcheck="false">
                <button id="save-token" class="btn-primary" type="button">Сохранить</button>
                <span id="token-meta" class="token-meta">Токен не задан</span>
            </div>
        </div>
    </header>

    <main class="page">
        <form id="filters" class="card filters">
            <div class="filters-grid">
                <label class="field">Статус
                    <select id="status" name="status">
                        <option value="">Все</option>
                        <option value="pending">pending</option>
                        <option value="sent">sent</option>
                        <option value="failed">failed</option>
                    </select>
                </label>
                <label class="field">Email получателя
                    <input id="recipient" name="recipient" type="search" placeholder="user@example.com">
                </label>
                <label class="field">Дата от
                    <input id="date-from" name="date_from" type="date">
                </label>
                <label class="field">Дата до
                    <input id="date-to" name="date_to" type="date">
                </label>
                <div class="actions">
                    <button class="btn-primary" type="submit">Применить</button>
                    <button id="reset" class="btn-ghost" type="button">Сбросить</button>
                </div>
            </div>
        </form>

        <div id="banner" class="banner" role="status"></div>

        <section class="card">
            <div id="loading" class="loading" hidden>Загрузка…</div>
            <div id="empty" class="empty" hidden>Записей нет. Сохраните JWT с ролью Admin или Analyst и примените фильтры.</div>
            <div id="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Дата / время</th>
                            <th>Событие</th>
                            <th>Получатель</th>
                            <th>Статус</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="rows"></tbody>
                </table>
                <div class="pager">
                    <span id="page-info">Страница 1 из 1</span>
                    <div class="pager-buttons">
                        <button id="prev" class="btn-ghost" type="button">Назад</button>
                        <button id="next" class="btn-ghost" type="button">Далее</button>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <div id="modal" class="modal-backdrop" hidden>
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
            <header>
                <h2 id="modal-title">Уведомление</h2>
                <button id="modal-close" class="btn-ghost" type="button">Закрыть</button>
            </header>
            <div class="body">
                <p class="meta-line">Попыток: <strong id="modal-attempts">0</strong></p>
                <p id="modal-error-wrap" class="meta-line">Ошибка: <strong id="modal-error"></strong></p>
                <pre id="modal-payload"></pre>
            </div>
            <footer>
                <button id="modal-replay" class="btn-danger" type="button" hidden>Повторить отправку</button>
            </footer>
        </div>
    </div>

    <script>
        const TOKEN_KEY = 'notification_jwt';
        const state = { page: 1, lastPage: 1, items: [], selected: null };

        const jwtInput = document.getElementById('jwt');
        const tokenMeta = document.getElementById('token-meta');
        const banner = document.getElementById('banner');
        const rows = document.getElementById('rows');
        const empty = document.getElementById('empty');
        const loading = document.getElementById('loading');
        const tableWrap = document.getElementById('table-wrap');
        const pageInfo = document.getElementById('page-info');
        const prevBtn = document.getElementById('prev');
        const nextBtn = document.getElementById('next');
        const modal = document.getElementById('modal');

        jwtInput.value = localStorage.getItem(TOKEN_KEY) || '';
        renderTokenMeta();

        document.getElementById('save-token').addEventListener('click', () => {
            localStorage.setItem(TOKEN_KEY, jwtInput.value.trim());
            renderTokenMeta();
            state.page = 1;
            load();
        });

        document.getElementById('filters').addEventListener('submit', (event) => {
            event.preventDefault();
            state.page = 1;
            load();
        });

        document.getElementById('reset').addEventListener('click', () => {
            document.getElementById('status').value = '';
            document.getElementById('recipient').value = '';
            document.getElementById('date-from').value = '';
            document.getElementById('date-to').value = '';
            state.page = 1;
            load();
        });

        prevBtn.addEventListener('click', () => {
            if (state.page > 1) {
                state.page -= 1;
                load();
            }
        });

        nextBtn.addEventListener('click', () => {
            if (state.page < state.lastPage) {
                state.page += 1;
                load();
            }
        });

        document.getElementById('modal-close').addEventListener('click', closeModal);
        modal.addEventListener('click', (event) => {
            if (event.target === modal) closeModal();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeModal();
        });
        document.getElementById('modal-replay').addEventListener('click', () => {
            if (state.selected) replay(state.selected.id);
        });

        load();

        function token() {
            return (localStorage.getItem(TOKEN_KEY) || '').trim();
        }

        function renderTokenMeta() {
            const value = jwtInput.value.trim();
            if (!value) {
                tokenMeta.textContent = 'Токен не задан';
                return;
            }
            const roles = readRoles(value);
            tokenMeta.innerHTML = roles.length
                ? 'Роли в токене: <strong>' + escapeHtml(roles.join(', ')) + '</strong>'
                : 'Токен сохранён, роли не найдены';
        }

        function readRoles(value) {
            try {
                const part = value.split('.')[1];
                const json = JSON.parse(atob(part.replace(/-/g, '+').replace(/_/g, '/')));
                if (Array.isArray(json.roles)) return json.roles.map(String);
                if (json.role) return [String(json.role)];
            } catch (e) {}
            return [];
        }

        function filters() {
            return {
                status: document.getElementById('status').value,
                recipient: document.getElementById('recipient').value.trim(),
                date_from: document.getElementById('date-from').value,
                date_to: document.getElementById('date-to').value,
            };
        }

        async function load() {
            showBanner('', '');
            if (!token()) {
                state.items = [];
                renderRows();
                showBanner('Вставьте access-токен и нажмите «Сохранить».', '');
                return;
            }

            const params = new URLSearchParams({ page: String(state.page), per_page: '15' });
            const current = filters();
            Object.entries(current).forEach(([key, value]) => {
                if (value) params.set(key, value);
            });

            loading.hidden = false;
            tableWrap.hidden = true;

            try {
                const response = await fetch('/api/notifications?' + params.toString(), {
                    headers: {
                        Accept: 'application/json',
                        Authorization: 'Bearer ' + token(),
                    },
                });
                const body = await response.json().catch(() => ({}));
                if (!response.ok) {
                    state.items = [];
                    renderRows();
                    showBanner(errorText(response.status, body), 'error');
                    return false;
                }

                state.items = body.data || [];
                state.lastPage = body.meta?.last_page || 1;
                state.page = body.meta?.current_page || state.page;
                renderRows();
                return true;
            } catch (e) {
                showBanner('Не удалось связаться с сервисом уведомлений.', 'error');
                return false;
            } finally {
                loading.hidden = true;
                tableWrap.hidden = false;
            }
        }

        function renderRows() {
            rows.replaceChildren();
            const hasItems = state.items.length > 0;
            empty.hidden = hasItems;
            document.querySelector('table').hidden = !hasItems;
            state.items.forEach((item) => rows.appendChild(renderRow(item)));
            pageInfo.textContent = 'Страница ' + state.page + ' из ' + state.lastPage;
            prevBtn.disabled = state.page <= 1;
            nextBtn.disabled = state.page >= state.lastPage;
        }

        function renderRow(item) {
            const tr = document.createElement('tr');
            tr.append(
                cell(formatDate(item.created_at)),
                cell(item.event || '—'),
                cell(item.recipient || '—'),
                badgeCell(item.status),
                actionCell(item),
            );
            tr.addEventListener('click', () => openModal(item));
            return tr;
        }

        function cell(text) {
            const td = document.createElement('td');
            td.textContent = text;
            return td;
        }

        function badgeCell(status) {
            const td = document.createElement('td');
            const badge = document.createElement('span');
            badge.className = 'badge ' + (status || '');
            badge.textContent = status || '—';
            td.appendChild(badge);
            return td;
        }

        function actionCell(item) {
            const td = document.createElement('td');
            if (item.status === 'failed') {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn-danger';
                button.textContent = 'Повторить отправку';
                button.addEventListener('click', (event) => {
                    event.stopPropagation();
                    replay(item.id);
                });
                td.appendChild(button);
            }
            return td;
        }

        function openModal(item) {
            state.selected = item;
            document.getElementById('modal-title').textContent = item.event || 'Уведомление';
            document.getElementById('modal-attempts').textContent = String(item.attempts ?? 0);
            const errorWrap = document.getElementById('modal-error-wrap');
            const error = document.getElementById('modal-error');
            if (item.error_message) {
                error.textContent = item.error_message;
                errorWrap.hidden = false;
            } else {
                error.textContent = '';
                errorWrap.hidden = true;
            }
            document.getElementById('modal-payload').textContent = JSON.stringify(item.payload ?? {}, null, 2);
            document.getElementById('modal-replay').hidden = item.status !== 'failed';
            modal.hidden = false;
            modal.classList.add('open');
        }

        function closeModal() {
            modal.classList.remove('open');
            modal.hidden = true;
            state.selected = null;
        }

        async function replay(id) {
            const button = document.getElementById('modal-replay');
            button.disabled = true;
            try {
                const response = await fetch('/api/notifications/' + encodeURIComponent(id) + '/replay', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        Authorization: 'Bearer ' + token(),
                    },
                });
                const body = await response.json().catch(() => ({}));
                if (!response.ok) {
                    showBanner(body.message || body.error || 'Повторная отправка не выполнена.', 'error');
                    return;
                }
                closeModal();
                const reloaded = await load();
                if (reloaded) {
                    showBanner('Уведомление отправлено повторно.', 'ok');
                }
            } catch (e) {
                showBanner('Не удалось повторить отправку.', 'error');
            } finally {
                button.disabled = false;
            }
        }

        function showBanner(text, kind) {
            banner.textContent = text;
            banner.className = 'banner' + (kind ? ' ' + kind : '');
            banner.style.display = text ? 'block' : 'none';
        }

        function errorText(status, body) {
            if (status === 401) return body.error || 'Токен недействителен или истёк.';
            if (status === 403) return body.error || 'Доступ только для ролей Admin и Analyst.';
            return body.message || body.error || 'Запрос отклонён.';
        }

        function formatDate(value) {
            if (!value) return '—';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return value;
            return new Intl.DateTimeFormat('ru-RU', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            }).format(date);
        }

        function escapeHtml(value) {
            return value.replace(/[&<>"']/g, (char) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
            }[char]));
        }
    </script>
</body>
</html>

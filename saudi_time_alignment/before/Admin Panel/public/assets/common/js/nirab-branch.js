(() => {
    'use strict';
    const config = window.nirabBranch;
    if (!config) return;
    const text = key => config.labels[key] || key;
    const byId = id => document.getElementById(id);
    const notice = (message, success = false, refresh = null) => {
        const el = byId('branch-message'); el.textContent = message; el.className = 'alert ' + (refresh ? 'alert-warning' : success ? 'alert-success' : 'alert-danger');
        if (refresh) el.append(button('refresh', async () => {
            try { await refresh(); notice(text('refreshed'), true); }
            catch (error) { notice(text('saved_refresh_failed') + ' ' + error.message, true, refresh); }
        }));
    };
    const successCodes = new Set(['default_200', 'default_store_200', 'default_update_200', 'default_status_update_200', 'default_delete_200', 'no_changes_found_200']);
    const api = async (path, body, method = 'GET') => {
        const url = new URL(config.base + '/' + path, window.location.origin);
        url.searchParams.set('branch_id', config.branchId);
        const response = await fetch(url, {method, credentials: 'same-origin', headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrf}, ...(body === undefined ? {} : {body: JSON.stringify(body)})});
        const result = await response.json();
        if (!response.ok || (result.response_code && !successCodes.has(result.response_code)) || Object.keys(result.errors || {}).length) {
            const errors = result.errors || result.error || {};
            const messages = Array.isArray(errors) ? errors.map(e => e.message || e) : Object.values(errors).flat();
            throw new Error(messages.join('\n') || result.message || text('failed'));
        }
        return result.content;
    };
    const submit = (form, task) => form?.addEventListener('submit', async event => {
        event.preventDefault(); const button = event.submitter; if (button) button.disabled = true;
        try { const result = await task(Object.fromEntries(new FormData(form)), form); notice(result?.message || text('saved'), true, result?.refresh); }
        catch (error) { notice(error.message); }
        finally { if (button) button.disabled = false; }
    });
    const saveThenRefresh = async (path, data, method, refresh) => {
        await api(path, data, method);
        try { await refresh(); }
        catch (error) { return {message: text('saved_refresh_failed') + ' ' + error.message, refresh}; }
    };
    document.querySelectorAll('.branch-action').forEach(form => submit(form, async data => { await api(form.dataset.endpoint, data, 'POST'); window.location.reload(); }));
    // Prevent accidental double submissions while the server enforces idempotency and state locks.
    document.querySelectorAll('.nirab-branch form[method="post"]').forEach(form => form.addEventListener('submit', () => setTimeout(() => form.querySelectorAll('button').forEach(button => button.disabled = true), 0)));
    const table = (host, rows, columns, action) => {
        host.replaceChildren(); const el = document.createElement('table'); el.className = 'table';
        const header = el.createTHead().insertRow();
        for (const [key, label] of columns) { const th = document.createElement('th'); th.scope = 'col'; th.textContent = text(label || key); header.append(th); }
        if (action) { const th = document.createElement('th'); th.textContent = text('actions'); header.append(th); }
        const body = el.createTBody();
        for (const record of rows) { const row = body.insertRow(); for (const [key] of columns) row.insertCell().textContent = record[key] ?? '—'; if (action) action(row.insertCell(), record); }
        if (!rows.length) { const cell = body.insertRow().insertCell(); cell.colSpan = columns.length + (action ? 1 : 0); cell.textContent = text('empty'); }
        host.append(el);
    };
    const button = (label, task) => { const el = document.createElement('button'); el.type = 'button'; el.className = 'btn btn-sm btn-outline-primary'; el.textContent = text(label); el.onclick = async () => { el.disabled = true; try { await task(); } catch (error) { notice(error.message); } finally { el.disabled = false; } }; return el; };
    let quoteId = null;
    const showQuote = async id => {
        const record = await api('quotes/' + id); quoteId = id;
        byId('quote-detail').hidden = false;
        const description = byId('quote-description'); description.replaceChildren();
        for (const value of [record.service?.name, record.customer?.first_name, text(record.quote_status), record.description]) { if (value) { const p = document.createElement('p'); p.textContent = value; description.append(p); } }
        for (const value of record.field_values || []) { const p = document.createElement('p'); p.textContent = (value.field?.label || value.field_id || '') + ': ' + (typeof value.value === 'object' ? JSON.stringify(value.value) : value.value ?? ''); description.append(p); }
        for (const attachment of record.attachments || []) {
            if (!attachment.url) continue;
            const url = new URL(attachment.url, window.location.origin); if (!['https:', 'http:'].includes(url.protocol)) continue;
            const link = document.createElement('a'); link.href = url; link.target = '_blank'; link.rel = 'noopener'; link.textContent = attachment.original_name || text('attachment'); link.className = 'btn btn-outline-secondary'; description.append(link);
        }
        table(byId('quote-versions'), (record.quotations || []).map(q => ({...q, status: text(q.status)})), [['quote_number','invoice'],['revision','versions'],['offered_price','amount'],['status'],['issued_at','date'],['expires_at','expires'],['accepted_at','customer_acceptance'],['provider_note','note'],['terms']]);
        const locked = record.is_booked || record.quote_status === 'accepted';
        table(byId('quote-inspections'), (record.inspections || []).map(i => ({...i, display_status: text(i.status)})), [['scheduled_at','schedule'],['fee_type'],['amount'],['display_status','status'],['paid_at','paid'],['note']], (cell, inspection) => {
            if (locked) return;
            const form = document.createElement('form'), select = document.createElement('select'); select.className = 'form-control'; select.name = 'status';
            for (const state of ['scheduled','paid','completed','canceled']) { const option = document.createElement('option'); option.value = state; option.textContent = text(state); option.selected = state === inspection.status; select.append(option); }
            select.setAttribute('aria-label', text('status'));
            const save = document.createElement('button'); save.className = 'btn btn-outline-primary'; save.textContent = text('save'); form.append(select, save); cell.append(form);
            submit(form, data => saveThenRefresh('quotes/inspections/' + inspection.id, data, 'PUT', () => showQuote(id)));
        });
        byId('quote-issue').closest('details').hidden = locked;
        byId('quote-inspection').closest('details').hidden = locked;
    };
    const loadQuotes = async (page = 1) => {
        const params = new URLSearchParams(new FormData(byId('quote-filter'))); params.set('page', page);
        const result = await api('quotes?' + params);
        table(byId('branch-quotes'), result.data.map(p => ({...p, service_name: p.service?.name, customer_name: p.customer?.first_name, display_status: text(p.quote_status)})), [['service_name','service'],['customer_name','customer'],['display_status','status'],['created_at','date']], (cell, row) => cell.append(button('details', () => showQuote(row.id))));
        byId('quote-pages').replaceChildren();
        for (const [label, pageNumber] of [['previous', result.current_page - 1], ['next', result.current_page + 1]]) { const el = button(label, () => loadQuotes(pageNumber)); el.disabled = pageNumber < 1 || pageNumber > result.last_page; byId('quote-pages').append(el); }
    };
    if (byId('quote-filter')) {
        submit(byId('quote-filter'), () => loadQuotes());
        const refreshQuote = async () => { await showQuote(quoteId); await loadQuotes(); };
        submit(byId('quote-issue'), data => saveThenRefresh('quotes/' + quoteId + '/issue', data, 'POST', refreshQuote));
        submit(byId('quote-inspection'), data => saveThenRefresh('quotes/' + quoteId + '/inspections', data, 'POST', refreshQuote));
        loadQuotes().catch(error => notice(error.message));
    }
})();

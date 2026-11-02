document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-sidebar-toggle]').forEach(btn => btn.addEventListener('click', () => document.body.classList.toggle('sidebar-open')));
    document.querySelectorAll('[data-print]').forEach(btn => btn.addEventListener('click', () => window.print()));

    document.addEventListener('change', (e) => {
        const el = e.target;
        if (el.matches('select[name="subsidiary_id"]')) {
            filterProductOptions(el.value);
        }
        if (el.matches('[data-product-select]')) {
            const option = el.options[el.selectedIndex];
            const row = el.closest('[data-line-row]');
            if (!row || !option) return;
            const desc = row.querySelector('[data-description]');
            const price = row.querySelector('[data-unit-price]');
            const tax = row.querySelector('[data-tax-rate]');
            if (desc && !desc.value) desc.value = option.dataset.name || '';
            if (price && option.dataset.price !== undefined) price.value = option.dataset.price || 0;
            if (tax && option.dataset.tax !== undefined) tax.value = option.dataset.tax || 0;
            recalcDocument();
        }
    });

    document.addEventListener('input', (e) => {
        if (e.target.matches('[data-quantity], [data-unit-price], [data-tax-rate]')) recalcDocument();
    });

    document.addEventListener('click', (e) => {
        const remove = e.target.closest('[data-remove-line]');
        if (remove) {
            const rows = document.querySelectorAll('[data-line-row]');
            if (rows.length > 1) remove.closest('[data-line-row]').remove();
            recalcDocument();
        }
        const add = e.target.closest('[data-add-line]');
        if (add) addDocumentLine(add.dataset.target);
    });

    const subsidiarySelector = document.querySelector('select[name="subsidiary_id"]');
    if (subsidiarySelector) filterProductOptions(subsidiarySelector.value);
    recalcDocument();
});

function filterProductOptions(subsidiaryId) {
    document.querySelectorAll('option[data-subsidiary]').forEach(option => {
        const mismatch = subsidiaryId && option.dataset.subsidiary && option.dataset.subsidiary !== String(subsidiaryId);
        option.disabled = !!mismatch;
        option.hidden = !!mismatch;
        if (mismatch && option.selected) option.parentElement.value = '';
    });
}

function addDocumentLine(targetSelector) {
    const container = document.querySelector(targetSelector);
    const template = document.querySelector('[data-line-template]');
    if (!container || !template) return;
    const index = 'n' + Date.now().toString() + Math.floor(Math.random() * 1000).toString();
    const html = template.innerHTML.replaceAll('__INDEX__', index);
    container.insertAdjacentHTML('beforeend', html);
    const subsidiarySelector = document.querySelector('select[name="subsidiary_id"]');
    if (subsidiarySelector) filterProductOptions(subsidiarySelector.value);
    recalcDocument();
}

function recalcDocument() {
    let subtotal = 0, taxTotal = 0;
    document.querySelectorAll('[data-line-row]').forEach(row => {
        const qty = parseFloat(row.querySelector('[data-quantity]')?.value || 0);
        const price = parseFloat(row.querySelector('[data-unit-price]')?.value || 0);
        const tax = parseFloat(row.querySelector('[data-tax-rate]')?.value || 0);
        const base = qty * price;
        const taxAmount = base * tax / 100;
        subtotal += base;
        taxTotal += taxAmount;
        const totalCell = row.querySelector('[data-line-total]');
        if (totalCell) totalCell.textContent = formatMoney(base + taxAmount);
    });
    const s = document.querySelector('[data-subtotal]');
    const t = document.querySelector('[data-tax-total]');
    const g = document.querySelector('[data-grand-total]');
    if (s) s.textContent = formatMoney(subtotal);
    if (t) t.textContent = formatMoney(taxTotal);
    if (g) g.textContent = formatMoney(subtotal + taxTotal);
}

function formatMoney(value) {
    return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 }).format(value || 0) + ' GNF';
}

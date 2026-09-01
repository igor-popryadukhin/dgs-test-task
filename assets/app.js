import './styles/app.css';

const catalog = document.querySelector('[data-catalog]');
const catalogToggle = document.querySelector('[data-catalog-toggle]');
const setCatalog = (open) => {
    if (!catalog || !catalogToggle) return;
    catalog.hidden = !open;
    catalogToggle.setAttribute('aria-expanded', String(open));
    document.body.style.overflow = open ? 'hidden' : '';
};
catalogToggle?.addEventListener('click', () => setCatalog(catalog.hidden));
catalog?.addEventListener('click', (event) => {
    if (event.target === catalog) setCatalog(false);
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') setCatalog(false);
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        document.querySelector('[data-search]')?.focus();
    }
});
document.querySelectorAll('[data-category]').forEach((button) => button.addEventListener('click', () => {
    document.querySelectorAll('[data-category]').forEach((item) => item.classList.remove('active'));
    button.classList.add('active');
    const title = document.querySelector('[data-category-title]');
    if (title) title.textContent = button.dataset.category;
}));
const searchInput = document.querySelector('[data-search]');
const filterState = document.querySelector('[data-filter-state]');
const filterLabel = document.querySelector('[data-filter-label]');
const applyProductFilter = (query, label = '') => {
    const normalized = query.trim().toLocaleLowerCase('ru');
    document.querySelectorAll('[data-product]').forEach((card) => {
        card.hidden = normalized !== '' && !card.dataset.name.includes(normalized);
    });
    if (filterState && filterLabel) {
        filterState.hidden = normalized === '';
        filterLabel.textContent = label;
    }
};
document.querySelectorAll('[data-catalog-filter]').forEach((link) => link.addEventListener('click', (event) => {
    event.preventDefault();
    applyProductFilter(link.dataset.catalogFilter, link.textContent.trim());
    if (searchInput) searchInput.value = '';
    setCatalog(false);
    document.querySelector('#popular')?.scrollIntoView({behavior: 'smooth'});
}));
document.querySelector('[data-filter-reset]')?.addEventListener('click', () => applyProductFilter(''));

const slides = [...document.querySelectorAll('[data-slide]')];
const dots = [...document.querySelectorAll('[data-dot]')];
let currentSlide = 0;
const showSlide = (index) => {
    currentSlide = (index + slides.length) % slides.length;
    slides.forEach((slide, position) => {
        slide.classList.toggle('active', position === currentSlide);
        slide.setAttribute('aria-hidden', String(position !== currentSlide));
    });
    dots.forEach((dot, position) => dot.classList.toggle('active', position === currentSlide));
};
document.querySelector('[data-carousel-prev]')?.addEventListener('click', () => showSlide(currentSlide - 1));
document.querySelector('[data-carousel-next]')?.addEventListener('click', () => showSlide(currentSlide + 1));
dots.forEach((dot) => dot.addEventListener('click', () => showSlide(Number(dot.dataset.dot))));
if (slides.length > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) setInterval(() => showSlide(currentSlide + 1), 6000);

document.querySelectorAll('[data-currency-option]').forEach((button) => button.addEventListener('click', () => {
    document.querySelectorAll('[data-currency-option]').forEach((option) => {
        const active = option === button;
        option.classList.toggle('active', active);
        option.setAttribute('aria-pressed', String(active));
    });
}));

searchInput?.addEventListener('input', (event) => {
    applyProductFilter(event.currentTarget.value);
});
document.querySelectorAll('.favorite').forEach((button) => button.addEventListener('click', () => {
    button.classList.toggle('on');
    button.setAttribute('aria-label', button.classList.contains('on') ? 'Удалить из избранного' : 'Добавить в избранное');
}));

const modal = document.querySelector('[data-order-modal]');
const result = document.querySelector('[data-order-result]');
const formView = document.querySelector('[data-order-form-view]');
let selectedSku = null;
document.querySelectorAll('[data-buy]').forEach((button) => button.addEventListener('click', () => {
    selectedSku = button.dataset.sku;
    document.querySelector('[data-order-title]').textContent = button.dataset.name;
    formView.hidden = false;
    result.hidden = true;
    modal.showModal();
}));
document.querySelector('[data-modal-close]')?.addEventListener('click', () => modal.close());
modal?.addEventListener('click', (event) => {
    const rect = modal.getBoundingClientRect();
    if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) modal.close();
});

const request = async (url, options = {}) => {
    const response = await fetch(url, {headers: {'Content-Type': 'application/json'}, ...options});
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || 'Ошибка запроса');
    return data;
};
const pollOrder = async (id) => {
    for (let attempt = 0; attempt < 30; attempt += 1) {
        const order = await request(`/api/orders/${id}`);
        if (order.status === 'delivered') return order;
        if (['payment_failed', 'out_of_stock', 'delivery_failed'].includes(order.status)) throw new Error(`Заказ требует внимания: ${order.status}`);
        await new Promise((resolve) => setTimeout(resolve, 400));
    }
    throw new Error('Обработка заняла больше обычного. Проверьте заказ позже.');
};
document.querySelector('[data-create-order]')?.addEventListener('click', async (event) => {
    const button = event.currentTarget;
    if (!selectedSku || button.disabled) return;
    button.disabled = true;
    try {
        const promo = document.querySelector('[data-promo]').value.trim();
        const order = await request('/api/orders', {method: 'POST', body: JSON.stringify({sku: selectedSku, client_request_id: crypto.randomUUID(), promo_code: promo || null})});
        formView.hidden = true;
        result.hidden = false;
        result.innerHTML = '<div class="spinner"></div><strong>Подтверждаем оплату…</strong><p>Заказ создан, ожидаем выдачу ключа.</p>';
        await request(`/api/orders/${order.id}/simulate-payment`, {method: 'POST', body: '{}'});
        const delivered = await pollOrder(order.id);
        result.innerHTML = `<strong>Готово!</strong><p>Ключ выдан ровно один раз.</p><code>${delivered.issued_code}</code><a href="/orders/${order.id}">Открыть заказ</a>`;
    } catch (error) {
        result.hidden = false;
        formView.hidden = true;
        result.innerHTML = `<strong>Не удалось оформить заказ</strong><p>${error.message}</p>`;
    } finally {
        button.disabled = false;
    }
});

document.querySelector('[data-topup-form]')?.addEventListener('submit', (event) => {
    event.preventDefault();
    const amount = Math.min(15000, Math.max(100, Number(new FormData(event.currentTarget).get('amount')) || 500));
    const match = [...document.querySelectorAll('[data-buy]')].find((button) => button.dataset.sku === `STEAM-TOPUP-${amount}`) || document.querySelector('[data-buy][data-sku^="STEAM-TOPUP"]');
    match?.click();
});

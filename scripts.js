document.addEventListener('DOMContentLoaded', () => {
  const yearEl = document.getElementById('year');
  if (yearEl) yearEl.textContent = new Date().getFullYear();

  // Productos con imágenes
  const products = [
    { id: 1, name: 'Camiseta Eco', price: 199.99, desc: 'Algodón orgánico, corte moderno.', img: 'assets/camiseta.svg', alt: 'Camiseta Eco' },
    { id: 2, name: 'Chaqueta Urbana', price: 299.99, desc: 'Corte impermeable y ligero.', img: 'assets/chaqueta.svg', alt: 'Chaqueta Urbana' },
    { id: 3, name: 'Pantalón Slim', price: 149.99, desc: 'Tejido elástico y cómodo.', img: 'assets/pantalon.svg', alt: 'Pantalón Slim' },
    { id: 4, name: 'Vestido Casual', price: 159.99, desc: 'Diseño atemporal para todo momento.', img: 'assets/vestido.svg', alt: 'Vestido Casual' }
  ];

  const productsGrid = document.getElementById('productsGrid');
  const cartBtn = document.getElementById('cartBtn');

  // Cart stored as object { [id]: qty }
  function loadCart() {
    try {
      const raw = localStorage.getItem('sf_cart');
      return raw ? JSON.parse(raw) : {};
    } catch (e) {
      console.error('Error leyendo carrito', e);
      return {};
    }
  }

  function saveCart(cart) {
    localStorage.setItem('sf_cart', JSON.stringify(cart));
  }

  function cartCount(cart) {
    return Object.values(cart).reduce((s, q) => s + q, 0);
  }

  function updateCartUI() {
    const cart = loadCart();
    const count = cartCount(cart);
    if (cartBtn) cartBtn.textContent = `Carrito (${count})`;
  }

  // Render products with images and lazy-loading
  function renderProducts() {
    if (!productsGrid) return;
    const cart = loadCart();
    productsGrid.innerHTML = '';
    products.forEach(p => {
      const el = document.createElement('article');
      el.className = 'product';
      el.innerHTML = `
        <img src="${p.img}" loading="lazy" alt="${p.alt}" data-id="${p.id}" class="product-img"/>
        <h3><button class="link-like" data-id="${p.id}">${p.name}</button></h3>
        <p>${p.desc}</p>
        <div class="meta">
          <strong>$${p.price.toFixed(2)}</strong>
          <div class="qty-controls">
            <button class="dec-btn" data-id="${p.id}" aria-label="Disminuir cantidad">-</button>
            <span class="prod-qty" data-id="${p.id}">${cart[p.id] || 0}</span>
            <button class="inc-btn" data-id="${p.id}" aria-label="Aumentar cantidad">+</button>
            <button class="add-btn" data-id="${p.id}">Añadir</button>
          </div>
        </div>
      `;
      productsGrid.appendChild(el);
    });
  }

  renderProducts();
  updateCartUI();

  // Add to cart
  function addToCart(id, qty = 1) {
    const cart = loadCart();
    cart[id] = (cart[id] || 0) + qty;
    saveCart(cart);
    updateCartUI();
  }

  // Remove from cart
  function removeFromCart(id) {
    const cart = loadCart();
    if (cart[id]) delete cart[id];
    saveCart(cart);
    updateCartUI();
  }

  // Decrease quantity of a product in the cart by `qty` (default 1).
  // If quantity reaches 0, the product is removed from the cart.
  function decreaseFromCart(id, qty = 1) {
    const cart = loadCart();
    if (!cart[id]) return;
    cart[id] = Number(cart[id]) - Number(qty || 1);
    if (cart[id] <= 0) delete cart[id];
    saveCart(cart);
    updateCartUI();
  }

  // Delegación para botones dentro de productsGrid
  if (productsGrid) {
    productsGrid.addEventListener('click', e => {
      const decBtn = e.target.closest('.dec-btn');
      if (decBtn) {
        const id = decBtn.dataset.id;
        decreaseFromCart(id, 1);
        // actualizar contador visible en la tarjeta
        const qtySpan = productsGrid.querySelector(`.prod-qty[data-id="${id}"]`);
        if (qtySpan) {
          const cart = loadCart();
          qtySpan.textContent = cart[id] || 0;
        }
        if (typeof renderCheckout === 'function') try { renderCheckout(); } catch (e) {}
        return;
      }

      const incBtn = e.target.closest('.inc-btn');
      if (incBtn) {
        const id = incBtn.dataset.id;
        addToCart(id, 1);
        // actualizar contador visible en la tarjeta
        const qtySpan = productsGrid.querySelector(`.prod-qty[data-id="${id}"]`);
        if (qtySpan) {
          const cart = loadCart();
          qtySpan.textContent = cart[id] || 0;
        }
        // si existe mini-checkout en la página, re-renderizarlo
        if (typeof renderCheckout === 'function') try { renderCheckout(); } catch (e) {}
        return;
      }

      const addBtn = e.target.closest('.add-btn');
      if (addBtn) {
        const id = addBtn.dataset.id;
        addToCart(id, 1);
        addBtn.textContent = 'Añadido';
        addBtn.disabled = true;
        // actualizar contador visible en la tarjeta
        const qtySpan = productsGrid.querySelector(`.prod-qty[data-id="${id}"]`);
        if (qtySpan) {
          const cart = loadCart();
          qtySpan.textContent = cart[id] || 0;
        }
        if (typeof renderCheckout === 'function') try { renderCheckout(); } catch (e) {}
        return;
      }

      // Open modal when clicking image or title button
      const img = e.target.closest('img[data-id]');
      const titleBtn = e.target.closest('button[data-id]');
      const id = img ? img.dataset.id : (titleBtn ? titleBtn.dataset.id : null);
      if (id) {
        openProductModal(Number(id));
        return;
      }
    });
  }

  // Nav toggle for small screens
  const navToggle = document.getElementById('navToggle');
  const nav = document.getElementById('nav');
  if (navToggle && nav) {
    navToggle.addEventListener('click', () => {
      if (nav.style.display === 'flex') nav.style.display = '';
      else nav.style.display = 'flex';
    });
  }

  // Modal logic
  const modal = document.getElementById('productModal');
  const modalImg = document.getElementById('modalImg');
  const modalTitle = document.getElementById('modalTitle');
  const modalDesc = document.getElementById('modalDesc');
  const modalPrice = document.getElementById('modalPrice');
  const modalAdd = document.getElementById('modalAdd');
  const modalClose = document.getElementById('modalClose');
  let modalProductId = null;

  function openProductModal(id) {
    const p = products.find(x => x.id === Number(id));
    if (!p || !modal) return;
    modalProductId = p.id;
    modalImg.src = p.img;
    modalImg.alt = p.alt;
    modalTitle.textContent = p.name;
    modalDesc.textContent = p.desc;
    modalPrice.textContent = `$${p.price.toFixed(2)}`;
    modal.setAttribute('aria-hidden', 'false');
    modal.focus && modal.focus();
  }

  function closeModal() {
    if (!modal) return;
    modal.setAttribute('aria-hidden', 'true');
    modalProductId = null;
  }

  if (modalClose) modalClose.addEventListener('click', closeModal);
  if (modal) modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

  if (modalAdd) modalAdd.addEventListener('click', () => {
    if (modalProductId) {
      addToCart(modalProductId, 1);
      // actualizar contador visible en la tarjeta si existe
      const qtySpan = productsGrid && productsGrid.querySelector(`.prod-qty[data-id="${modalProductId}"]`);
      if (qtySpan) {
        const cart = loadCart();
        qtySpan.textContent = cart[modalProductId] || 0;
      }
      // re-render checkout si está presente
      if (typeof renderCheckout === 'function') try { renderCheckout(); } catch (e) {}
      closeModal();
    }
  });

 
  

  // If on checkout page, render items
  const checkoutList = document.getElementById('checkoutItems');
  if (checkoutList) {
    function renderCheckout() {
      const cart = loadCart();
      checkoutList.innerHTML = '';
      const ids = Object.keys(cart);
      if (ids.length === 0) {
        checkoutList.innerHTML = '<p>Tu carrito está vacío.</p>';
        document.getElementById('checkoutTotal').textContent = '$0.00';
        return;
      }
      let total = 0;
      ids.forEach(id => {
        const p = products.find(x => x.id === Number(id));
        const qty = cart[id];
        if (!p) return;
        const li = document.createElement('div');
        li.className = 'line-item';
        li.innerHTML = `
          <img src="${p.img}" alt="${p.alt}"/>
          <div class="meta">
            <div><strong>${p.name}</strong></div>
            <div>Cantidad: ${qty}</div>
            <div>$${(p.price * qty).toFixed(2)} </div>
          </div>
          <div class="actions">
            <button class="remove-btn" data-id="${p.id}">Eliminar</button>
          </div>
        `;
        checkoutList.appendChild(li);
        total += p.price * qty;
      });
      document.getElementById('checkoutTotal').textContent = `$${total.toFixed(2)}`;
    }

    // Delegación para acciones en checkout: eliminar
    checkoutList.addEventListener('click', e => {
      const btn = e.target.closest('.remove-btn');
      if (!btn) return;
      const id = btn.dataset.id;
      removeFromCart(id);
      renderCheckout();
    });

    // Clear and finalize
    const clearBtn = document.getElementById('clearCart');
    if (clearBtn) clearBtn.addEventListener('click', () => { localStorage.removeItem('sf_cart'); updateCartUI(); renderCheckout(); });
    const finishBtn = document.getElementById('finishOrder');
    if (finishBtn) finishBtn.addEventListener('click', () => {
      const cart = loadCart();
      const ids = Object.keys(cart);
      if (ids.length === 0) {
        alert('Tu carrito está vacío. Añade productos antes de finalizar la compra.');
        return;
      }
      alert('Compra Finalizada. ¡Gracias!');
      localStorage.removeItem('sf_cart');
      updateCartUI();
      renderCheckout();
    });

    renderCheckout();
  }

});

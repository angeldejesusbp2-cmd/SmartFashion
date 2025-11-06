# SmartFashion — Sitio estático

Este pequeño proyecto contiene una página web estática para la tienda "SmartFashion".

Archivos principales:

- `index.html` — Página principal.
- `styles.css` — Estilos.
- `scripts.js` — JavaScript mínimo (renderizado de productos, carrito simulado, modal y persistencia del carrito).

Cómo probarlo:

1. Abrir `c:\SmartFashion\index.html` en tu navegador (doble clic) o servir la carpeta localmente para evitar problemas con rutas de archivos.

Comandos útiles (PowerShell):

```powershell
# Servir con Python (si está instalado)
python -m http.server 8000

# Servir con npx (si tienes Node.js)
npx http-server -c-1

# Alternativa: instalar live-server para desarrollo con reload
npx live-server
```

2. En la página principal puedes:
	- Ver la colección (imágenes placeholder en `assets/` con lazy-loading).
	- Abrir el modal al pulsar la imagen o el nombre del producto para ver más detalles.
	- Añadir productos al carrito; el contador usa `localStorage` y persiste entre páginas.
	- Contactar a la tienda mediante los datos públicos (teléfono, WhatsApp, Facebook y email) en la sección "Contacto" — hemos eliminado el formulario para proteger la privacidad de los visitantes.
3. Ir a `checkout.html` (enlace "Ver carrito") para ver los items, eliminar, vaciar o finalizar la compra (simulado).

Notas sobre las mejoras implementadas:

- Persistencia del carrito usando `localStorage` (clave `sf_cart`).
- Modal de detalle de producto con botón "Añadir al carrito".
- Páginas adicionales: `checkout.html`.
- Imágenes placeholder en `assets/` (SVG). Reemplázalas por fotos reales manteniendo el mismo nombre o actualizando `scripts.js`.
- Mejoras de accesibilidad: focus outline, roles ARIA, aria-live para el contador del carrito.


Notas:

- Es un sitio estático pensado como punto de partida. Puedes reemplazar las imágenes "placeholder" por imágenes reales en una carpeta `assets/`.
- Si quieres, puedo añadir un sistema de carrito persistente (localStorage), plantillas de producto, o exportar a un pequeño servidor con Node.js.

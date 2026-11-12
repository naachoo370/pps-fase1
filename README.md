# 🛍️ Maktub — Tienda online + API común

Proyecto de **Puesta en Producción Segura (UT1)**. Tienda online de ropa y complementos
construida con **Laravel 12** y **SQLite**, que incorpora la **API común** de seis rutas,
un cliente web estático y una batería de pruebas automáticas con **PHPUnit**.

> **Equipo:** Rafa · Nacho · Víctor · Darío · Daniel
>
> Proyecto adaptado a partir de la tienda del curso anterior. La autoría y la documentación
> se han actualizado a los cinco miembros actuales del grupo.

---

## 📌 Tecnologías y versiones

| Capa | Tecnología / versión |
|---|---|
| Lenguaje del servidor | PHP 8.3.36 (compatible con PHP 8.2+) |
| Framework | Laravel 12.72.0 |
| Base de datos | SQLite 3 (`database/database.sqlite`) |
| Pruebas | PHPUnit 11.5 (integrado en Laravel) |
| Cliente web | HTML5 + CSS3 + JavaScript vanilla (`fetch`) |
| Contenedor | Docker + Docker Compose (Laravel Sail runtime 8.5) |
| Servidor local | `php artisan serve --port=8080` |
| Servidor en contenedor | Apache del runtime Sail, publicado en el puerto **8080** |

---

## ✨ Funcionalidades

### Tienda web (Blade)
- Página de inicio con productos destacados por género.
- Catálogo con filtros por género y categoría.
- Ficha de producto con imágenes, tallas y stock.
- Registro/login de clientes, carrito, descuentos, pasarela de pago e historial de pedidos.
- Panel de administración: pedidos, stock y usuarios.

### API común (JSON)
- Registro y login de usuarios.
- CRUD de elementos privados: listar, crear, modificar y borrar.
- Aislamiento total entre usuarios: cada uno solo ve y modifica lo suyo.
- Logout (ruta extra) para invalidar el token.

### Cliente web estático
- `public/cliente/index.html`: registro, login, logout y CRUD de elementos.
- Habla con la API mediante `fetch`, `Content-Type: application/json` y `JSON.stringify`.
- Pinta los datos con `textContent` (nunca `innerHTML`) para evitar XSS.
- Diseño adaptable a **360 px** de ancho (móvil) y a escritorio.

---

## 🔐 API común

Prefijo automático de Laravel: **`/api`**. Todas las respuestas son JSON.

| Método | Ruta | Autenticación | Códigos esperados |
|---|---|---|---|
| `POST` | `/api/register` | No | `201` creado, `400` datos no válidos, `409` ya existe |
| `POST` | `/api/login` | No | `200` correcto, `401` credenciales no válidas |
| `GET` | `/api/items` | Token Bearer | `200`, `401` sin sesión |
| `POST` | `/api/items` | Token Bearer | `201`, `400` datos no válidos, `401` sin sesión |
| `PUT` | `/api/items/{id}` | Token Bearer | `200`, `400`, `401`, `403`/`404` si es de otro o no existe |
| `DELETE` | `/api/items/{id}` | Token Bearer | `204`, `401`, `403`/`404` si es de otro o no existe |
| `POST` | `/api/logout` | Token Bearer | `204` (ruta extra para cerrar sesión) |

### Campos admitidos

- **Registro/login:** `username` y `password`. También se aceptan los alias `usuario` y
  `clave` para mantener compatibilidad con los ejemplos de la guía de inicio.
- **Elementos:** `title` y `text`. También se aceptan `titulo` y `texto`.
- **Longitudes máximas:** `username` 50 caracteres, `password` 100 (mínimo 8),
  `title` 100 y `text` 1000.

### Credencial de sesión elegida

Se usa un **token opaco Bearer** (no una cookie de sesión):

1. `POST /api/login` devuelve un token de 64 caracteres hexadecimales (256 bits de
   entropía, generado con `random_bytes(32)`).
2. El cliente lo envía en cada petición protegida:

   ```http
   Authorization: Bearer <token>
   ```

3. En la tabla `api_tokens` **solo se guarda el hash SHA-256** del token, nunca el token
   en claro. El token caduca a los **7 días** y `POST /api/logout` lo elimina.
4. El cliente estático guarda el token en `localStorage` y lo elimina al cerrar sesión.

Esta elección evita depender de cookies/CSRF en la API y permite que el cliente estático
funcione igual en local y dentro del contenedor. El riesgo de XSS se reduce usando
`textContent` para todos los datos que vienen del servidor.

### Ejemplos rápidos

```bash
# Registro
curl -X POST http://localhost:8080/api/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"username":"ana","password":"Clave-1234"}'

# Login (guarda el token de la respuesta)
curl -X POST http://localhost:8080/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"username":"ana","password":"Clave-1234"}'

# Listar mis elementos
curl http://localhost:8080/api/items \
  -H "Accept: application/json" \
  -H "Authorization: Bearer TOKEN"

# Crear un elemento
curl -X POST http://localhost:8080/api/items \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{"title":"Mi elemento","text":"Texto del elemento"}'
```

> **Importante:** la validación se hace a mano con `Validator::make()` para devolver
> **`400`** en los datos inválidos. La validación automática de Laravel devolvería `422`,
> que no forma parte de la API común.

---

## 🛡️ Seguridad: riesgos de PHP/Laravel y mitigaciones

| Riesgo | Cómo se ha mitigado en Maktub |
|---|---|
| **Inyección SQL** | Se usa Eloquent/Query Builder, que genera consultas preparadas con parámetros. No se concatenan datos del usuario en SQL. Las migraciones definen las tablas y restricciones. |
| **XSS (Cross-Site Scripting)** | Blade escapa automáticamente con `{{ }}`. El cliente estático usa `textContent` y `createElement`, nunca `innerHTML`, para pintar datos de la API. |
| **CSRF** | Los formularios web de Laravel llevan `@csrf`. La API no usa cookies de sesión: autentica con `Authorization: Bearer`, por lo que no es vulnerable a CSRF basado en cookies. |
| **Contraseñas en claro** | `Hash::make()` (bcrypt, coste 12) y el cast `hashed` del modelo `User`. La contraseña está en `$hidden` y nunca se devuelve en JSON. |
| **Asignación masiva** | Todos los modelos usan listas blancas `$fillable`; no se usa `$guarded = []`. |
| **Control de acceso roto** | Cada consulta de elementos filtra por `user_id`. Modificar o borrar un elemento ajeno devuelve `403` (o `404` si no existe). Hay pruebas automáticas que lo verifican. |
| **Fuga de información en errores** | `APP_DEBUG=false`; un manejador de excepciones para `/api/*` devuelve mensajes genéricos en JSON, sin trazas, rutas internas ni SQL. |
| **Robo de credenciales de sesión** | Token aleatorio de 256 bits, guardado solo como hash SHA-256, con caducidad de 7 días e invalidación en el logout. |
| **Fuerza bruta en login/registro** | Limitación de peticiones con `throttle:60,1` (60 intentos por minuto y IP). |
| **Datos sensibles en el repositorio** | `.env` está ignorado; `.env.example` no contiene claves reales. `.gitignore` excluye `vendor/`, `node_modules/`, `venv/`, `__pycache__/` y `*.sqlite`. |
| **Dependencias desactualizadas** | `composer.lock` fija versiones y se actualizó el árbol de dependencias (Laravel 12.72.0, Guzzle, Symfony, etc.). `composer audit` no encuentra avisos de seguridad en la fecha de la entrega. Se recomienda repetirlo en cada fase. |
| **Exposición del contenedor** | El contenedor publica únicamente el puerto 8080. En producción real habría que añadir HTTPS, cabeceras de seguridad y un servidor de base de datos dedicado. |

**Mejoras pendientes para fases posteriores:** CSP estricta, HTTPS obligatorio, rotación de
tokens por dispositivo, registro de auditoría y revisión con ASVS/Top Ten (UT3).

---

## 📦 Instalación y arranque en el puerto 8080

### Requisitos

| Recurso | Versión |
|---|---|
| PHP | 8.2 o superior, con las extensiones `pdo_sqlite` y `sqlite3` |
| Composer | 2.x |
| Node.js (solo para recompilar assets) | 20 o superior |
| Docker (opcional) | Docker Desktop + Docker Compose |

Comprueba SQLite:

```bash
php -m | findstr sqlite     # Windows
php -m | grep sqlite        # Linux/Mac
```

Deben aparecer `pdo_sqlite` y `sqlite3`. Si no aparecen, descomenta
`extension=pdo_sqlite` y `extension=sqlite3` en tu `php.ini` y reinicia la terminal.

### Arranque local (sin Docker)

```bash
cd D:\Clase\PPS\Proyecto\Maktub

composer install
copy .env.example .env          # Linux/Mac: cp .env.example .env
php artisan key:generate
php artisan migrate --seed      # crea tablas, admin, productos y usuarios de prueba
php artisan storage:link        # necesario para las imágenes de productos

php artisan serve --port=8080
```

Direcciones:

- Tienda: <http://localhost:8080>
- Cliente de la API común: <http://localhost:8080/cliente/index.html>
- API: <http://localhost:8080/api>

### Arranque con Docker (puerto 8080)

```bash
cd D:\Clase\PPS\Proyecto\Maktub

copy .env.example .env          # Linux/Mac: cp .env.example .env
php artisan key:generate

docker compose up -d
docker compose exec laravel.test php artisan migrate --seed --force
docker compose exec laravel.test php artisan storage:link
```

La aplicación responde en **<http://localhost:8080>**. El puerto se controla con
`APP_PORT=8080` en `.env` y en `compose.yaml` (`'${APP_PORT:-8080}:80'`).

También puedes usar los scripts incluidos:

- `iniciar-docker.bat` (Windows CMD)
- `iniciar-docker.ps1` (PowerShell)

### Persistencia de datos

`compose.yaml` monta el proyecto completo como volumen bind (`.:/var/www/html`). La base de
datos `database/database.sqlite` vive en el host, así que **los datos se conservan** al
parar o reiniciar el contenedor (`docker compose stop`, `docker compose restart` o
`docker compose down`). Solo se pierden si se borra el fichero SQLite a mano.

Los seeders usan `updateOrCreate`, por lo que se pueden ejecutar varias veces sin duplicar
usuarios ni elementos.

---

## 👤 Usuarios de prueba

Contraseña común para los cinco usuarios de la API: **`Maktub-2026`**
(guardada con hash bcrypt en la base de datos).

| Usuario | Contraseña | Elementos propios |
|---|---|---|
| `rafa` | `Maktub-2026` | Chaqueta Maktub Denim · Camiseta algodón orgánico |
| `nacho` | `Maktub-2026` | Sudadera Maktub Logo · Pantalón cargo beige |
| `victor` | `Maktub-2026` | Gorra Maktub · Bufanda de lana |
| `dario` | `Maktub-2026` | Mochila Maktub Urban · Gafas de sol polarizadas |
| `daniel` | `Maktub-2026` | Zapatillas Maktub Street · Cinturón de cuero |

Además, el seeder mantiene el administrador de la tienda web:

| Rol | Email | Contraseña |
|---|---|---|
| Administrador | `admin@tienda.test` | `password` |

> El administrador entra por la tienda web (`/login`), no por la API: su usuario no tiene
> `username`. Los usuarios de prueba de la API se crean en `ApiDemoSeeder`.

---

## 🗂️ Estructura relevante

```text
Maktub/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   ├── AuthController.php        # register, login, logout
│   │   │   └── ItemController.php        # CRUD de elementos privados
│   │   └── Middleware/
│   │       └── AuthenticateApiToken.php  # valida Authorization: Bearer
│   └── Models/
│       ├── Item.php                      # elemento (user_id, title, text)
│       └── ApiToken.php                  # hash del token + caducidad
├── database/
│   ├── migrations/                       # usuarios, items y api_tokens
│   ├── seeders/ApiDemoSeeder.php         # 4 usuarios + 8 elementos de prueba
│   └── database.sqlite                   # BD local (ignorada por Git)
├── public/cliente/                       # cliente estático HTML/CSS/JS
│   ├── index.html
│   ├── styles.css
│   └── app.js
├── routes/
│   ├── api.php                           # 6 rutas de la API común + logout
│   └── web.php                           # tienda web
├── tests/Feature/Api/                    # pruebas automáticas de la API
│   ├── AuthApiTest.php
│   ├── ItemsApiTest.php
│   └── ApiTestHelpers.php
├── PRUEBAS.md                            # plan de pruebas y resultados
├── compose.yaml                          # Docker en el puerto 8080
└── README.md
```

---

## 🌐 Rutas principales

| Método | URI | Acceso |
|---|---|---|
| `GET` | `/` | Público |
| `GET` | `/catalogo/{genero?}/{categoria?}` | Público |
| `GET` | `/producto/{id}` | Público |
| `GET/POST` | `/login` y `/registro` | Invitados |
| `POST` | `/logout` | Autenticado en la web |
| `GET` | `/cliente/index.html` | Público (cliente estático) |
| `GET/POST` | `/api/register` y `/api/login` | Público (JSON) |
| `GET/POST` | `/api/items` | Token Bearer |
| `PUT/DELETE` | `/api/items/{id}` | Token Bearer |
| `POST` | `/api/logout` | Token Bearer |

---

## Cómo ejecutar las pruebas

Todas las pruebas se ejecutan con **un único comando**:

```bash
php artisan test
```

### Pasos exactos para que otro grupo las repita

1. Instala **PHP 8.2+** con `pdo_sqlite` y `sqlite3` habilitados, y **Composer 2.x**.
   Comprueba las extensiones:

   ```bash
   php -m | findstr sqlite     # Windows
   php -m | grep sqlite        # Linux/Mac
   ```

2. Clona/entra en el proyecto:

   ```bash
   cd D:\Clase\PPS\Proyecto\Maktub
   ```

3. Instala las dependencias PHP:

   ```bash
   composer install
   ```

4. Si no existe `.env`, créalo y genera la clave:

   ```bash
   copy .env.example .env      # Linux/Mac: cp .env.example .env
   php artisan key:generate
   ```

5. Ejecuta la suite:

   ```bash
   php artisan test
   ```

Resultado esperado:

```text
Tests:    37 passed (147 assertions)
```

Notas:

- `phpunit.xml` configura **SQLite en memoria** (`DB_DATABASE=:memory:`). No hace falta
  migrar ni sembrar la base de datos local antes de probar.
- Cada prueba crea sus propios usuarios con nombres aleatorios únicos y usa
  `RefreshDatabase`, así que **se puede ejecutar muchas veces seguidas** sin que falle por
  «usuario ya existe».
- Todas las peticiones de prueba envían `Accept: application/json`.
- También se puede usar `composer test` (limpia la caché de configuración y ejecuta
  `php artisan test`).
- Para ejecutar solo un grupo: `php artisan test --filter=ItemsApiTest`.

El detalle de cada caso (entrada, resultado esperado, qué comprueba y resultado obtenido)
está en **[`PRUEBAS.md`](PRUEBAS.md)**.

---

## 🧪 Resumen del plan de pruebas

`PRUEBAS.md` cubre:

- Las seis rutas de la API con casos correctos.
- Valores límite: `username` de 50/51 caracteres, `title` de 100/101 y `text` de 1000/1001.
- Datos no válidos: campos vacíos, tipos incorrectos, textos demasiado largos, registro
  duplicado (`409`) y login incorrecto (`401`).
- Acceso ajeno: un usuario intenta ver, modificar y borrar elementos de otro
  (`403`/`404`).
- Rutas de elementos sin sesión (`401`).
- Comprobación de que los errores no incluyen trazas, rutas internas ni SQL.

---

## 🧰 Solución de problemas

| Problema | Solución |
|---|---|
| `could not find driver` (SQLite) | Habilita `extension=pdo_sqlite` y `extension=sqlite3` en `php.ini`. |
| `No application encryption key` | Ejecuta `php artisan key:generate`. |
| Las imágenes de productos no se ven | Ejecuta `php artisan storage:link`. |
| El puerto 8080 está ocupado | Para el proceso que lo usa o cambia `APP_PORT` (Docker) / `--port` (artisan). |
| Cambios de configuración no se aplican | Ejecuta `php artisan config:clear`. |
| Docker no arranca | Comprueba que Docker Desktop está abierto y ejecuta `docker compose config` para validar el YAML. |

---

## 📄 Licencia y autoría

Proyecto académico de **Puesta en Producción Segura**. Licencia MIT.

**Autores:** Rafa · Nacho · Víctor · Darío · Daniel.

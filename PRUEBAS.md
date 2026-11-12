# 🧪 PRUEBAS — API común de Maktub

Plan de pruebas de la API común y resultados obtenidos. Proyecto de **Rafa, Nacho,
Víctor, Darío y Daniel**.

## 1. Entorno de ejecución

| Dato | Valor |
|---|---|
| Fecha de ejecución | 12/11/2026 |
| Sistema | Windows, PHP 8.3.36 |
| Framework | Laravel 12.72.0 |
| Base de datos de pruebas | SQLite en memoria (`:memory:`) |
| Herramienta | PHPUnit 11.5 integrado en Laravel |
| Comando único | `php artisan test` |
| Resultado | **37 pruebas superadas, 147 aserciones, 0 fallos** |
| Repetición | Ejecutado dos veces seguidas con el mismo resultado |

También se hizo0 una verificación manual de extremo a extremo contra
`http://127.0.0.1:8080` (servidor `php artisan serve --port=8080`), cubriendo registro,
login, listado, creación, edición, borrado, acceso ajeno, logout y ausencia de trazas.

---

## 2. Plan de pruebas y resultados

Las longitudes máximas definidas por la aplicación son:

- `username`: **50** caracteres.
- `password`: **100** caracteres (mínimo 8).
- `title`: **100** caracteres.
- `text`: **1000** caracteres.

| Número | Ruta | Entrada | Resultado esperado | Qué se comprueba | Resultado (Pasa / Qué fallo se encontró y cómo se corrigió) |
|---:|---|---|---|---|---|
| 1 | `POST /api/register` | `{"username":"registro_xxx","password":"Clave-1234"}` | `201` | Crea el usuario, guarda la contraseña con hash y no la devuelve en la respuesta. | **Pasa.** |
| 2 | `POST /api/register` | `{"usuario":"alias_xxx","clave":"Clave-1234"}` | `201` | Los alias `usuario`/`clave` de la guía también funcionan. | **Pasa.** |
| 3 | `POST /api/register` | `{}` | `400` | Faltan campos obligatorios. | **Pasa.** |
| 4 | `POST /api/register` | `{"username":"","password":""}` | `400` | Campos vacíos. | **Pasa.** |
| 5 | `POST /api/register` | `{"username":["no","es","texto"],"password":["tampoco"]}` | `400` | Tipos incorrectos. | **Pasa.** |
| 6 | `POST /api/register` | `username` de **50** caracteres | `201` | Valor justo en el límite máximo. | **Pasa.** |
| 7 | `POST /api/register` | `username` de **51** caracteres | `400` | Un carácter por encima del límite. | **Pasa.** |
| 8 | `POST /api/register` | Mismo `username` ya registrado | `409` | Registro duplicado. | **Pasa.** |
| 9 | `POST /api/login` | Credenciales correctas | `200` + token | Devuelve token Bearer, tipo y caducidad; el token se guarda hasheado. | **Pasa.** |
| 10 | `POST /api/login` | Contraseña incorrecta | `401` | Credenciales no válidas. | **Pasa.** |
| 11 | `POST /api/login` | `{}` (sin credenciales) | `401` | Login sin datos. | **Pasa.** |
| 12 | `GET /api/items` | `Authorization: Bearer token-invalido` | `401` | Token que no existe en la base de datos. | **Pasa.** |
| 13 | `GET /api/items` | Token caducado (`expires_at` en el pasado) | `401` | Caducidad del token y rechazo de sesión. | **Pasa.** |
| 14 | `POST /api/logout` | Token válido, después `GET /api/items` con el mismo token | `204` y luego `401` | El logout invalida el token. | **Pasa.** |
| 15 | `GET /api/items` | Sin cabecera `Authorization` | `401` | Ruta de elementos sin sesión. | **Pasa.** |
| 16 | `GET /api/items` | Usuario A autenticado; existen elementos de A y de B | `200` | Solo devuelve los elementos de A; no aparece ninguno de B. | **Pasa.** |
| 17 | `POST /api/items` | `{"title":"Nuevo elemento","text":"Contenido"}` con token | `201` | Crea el elemento asociado al usuario autenticado. | **Pasa.** |
| 18 | `POST /api/items` | `title` de **100** caracteres | `201` | Valor justo en el límite máximo del título. | **Pasa.** |
| 19 | `POST /api/items` | `title` de **101** caracteres | `400` | Un carácter por encima del límite del título. | **Pasa.** |
| 20 | `POST /api/items` | `text` de **1000** caracteres | `201` | Valor justo en el límite máximo del texto. | **Pasa.** |
| 21 | `POST /api/items` | `text` de **1001** caracteres | `400` | Un carácter por encima del límite del texto. | **Pasa.** |
| 22 | `POST /api/items` | `{"title":"","text":""}` con token | `400` | Campos obligatorios vacíos. | **Pasa.** |
| 23 | `POST /api/items` | `{"title":["array"],"text":["array"]}` con token | `400` | Tipos incorrectos. | **Pasa.** |
| 24 | `POST /api/items` | Datos válidos pero sin `Authorization` | `401` | Crear sin sesión. | **Pasa.** |
| 25 | `PUT /api/items/{id}` | Título y texto nuevos sobre un elemento propio | `200` | Modifica el elemento y persiste los cambios. | **Pasa.** |
| 26 | `PUT /api/items/{id}` | `{"title":"","text":"<1001 caracteres>"}` | `400` | Datos no válidos en la modificación. | **Pasa.** |
| 27 | `PUT /api/items/{id}` | Usuario B intenta modificar un elemento de A | `403` o `404` | Regla de oro: no se puede modificar lo ajeno; el elemento no cambia. | **Pasa.** (la implementación devuelve `403`) |
| 28 | `PUT /api/items/999999` | Elemento inexistente | `404` | Recurso no encontrado. | **Pasa.** |
| 29 | `PUT /api/items/{id}` | Sin `Authorization` | `401` | Modificar sin sesión. | **Pasa.** |
| 30 | `DELETE /api/items/{id}` | Elemento propio | `204` | Borra el elemento y desaparece de la base de datos. | **Pasa.** |
| 31 | `DELETE /api/items/{id}` | Usuario B intenta borrar un elemento de A | `403` o `404` | Regla de oro: no se puede borrar lo ajeno; el elemento sigue existiendo. | **Pasa.** (la implementación devuelve `403`) |
| 32 | `DELETE /api/items/999999` | Elemento inexistente | `404` | Recurso no encontrado. | **Pasa.** |
| 33 | `DELETE /api/items/{id}` | Sin `Authorization` | `401` | Borrar sin sesión. | **Pasa.** |
| 34 | Varias rutas (`/api/register`, `/api/items`, `/api/items/999999`) | Peticiones inválidas, sin sesión y recurso inexistente | Respuestas JSON sin detalles internos | No aparecen `SQLSTATE`, `Stack trace`, `vendor/`, `#0` ni `Exception`; `Content-Type` es `application/json`. | **Pasa.** |
| 35 | `GET /cliente/index.html` | Navegador / petición HTTP | `200` | El cliente estático se sirve correctamente y usa `fetch` + `textContent`. | **Pasa.** (verificado también con `ClientWebTest`) |
| 36 | `GET /` | Petición a la portada de la tienda | `200` | La tienda web sigue funcionando con las migraciones nuevas. | **Pasa.** |

---

## 3. Correspondencia con las pruebas automáticas

| Casos | Fichero | Métodos principales |
|---|---|---|
| 1–8 | `tests/Feature/Api/AuthApiTest.php` | `test_register_creates_user_and_saves_password_hashed`, `test_register_accepts_spanish_alias_fields`, `test_register_rejects_missing_fields_with_400`, `test_register_rejects_empty_fields_with_400`, `test_register_rejects_wrong_types_with_400`, `test_register_accepts_username_at_max_length`, `test_register_rejects_username_one_character_over_max_length`, `test_register_rejects_duplicate_username_with_409` |
| 9–14 | `tests/Feature/Api/AuthApiTest.php` | `test_login_returns_token_for_valid_credentials`, `test_login_rejects_invalid_credentials_with_401`, `test_login_rejects_missing_credentials_with_401`, `test_invalid_token_returns_401`, `test_expired_token_returns_401`, `test_logout_invalidates_the_token` |
| 15–34 | `tests/Feature/Api/ItemsApiTest.php` | `test_get_items_without_session_returns_401`, `test_get_items_returns_only_the_authenticated_users_items`, `test_create_item_returns_201_and_persists`, casos de límites, `test_update_*`, `test_delete_*`, `test_error_responses_do_not_leak_internal_details` |
| 35 | `tests/Feature/ClientWebTest.php` | `test_static_api_client_exists_and_uses_safe_rendering` |
| 36 | `tests/Feature/ExampleTest.php` | `test_the_application_returns_a_successful_response` |

Todas las pruebas:

- Envían `Accept: application/json`.
- Crean sus propios usuarios con nombres únicos aleatorios.
- Usan `RefreshDatabase` y SQLite en memoria.
- Pueden ejecutarse en cualquier orden y repetirse sin depender de datos previos.

---

## 4. Fallos encontrados durante el desarrollo y correcciones

| Fallo encontrado | Cómo se detectó | Corrección aplicada |
|---|---|---|
| PHP no tenía el driver de SQLite (`could not find driver`) | Primera ejecución de `php artisan test` | Se habilitaron `extension=pdo_sqlite` y `extension=sqlite3` en `php.ini` y se comprobó con `php -m`. |
| La prueba de la portada fallaba con `no such table: products` | Primera ejecución de la suite | Se añadió `RefreshDatabase` a `ExampleTest` para migrar la base de datos en memoria antes de la petición. |
| Laravel devolvía `422` en las validaciones automáticas | Pruebas iniciales de datos no válidos | Se sustituyó la validación automática por `Validator::make()` manual y se devuelve `400`, como exige la API común. |
| La verificación manual inicial con `curl` desde PowerShell daba `400` con datos correctos | Comprobación manual de las rutas | Era un problema de escapado de argumentos de PowerShell, no de la API. Se repitió la batería con un script Python/`urllib` y todas las respuestas fueron las esperadas. |

---

## 5. Cómo reproducir estas pruebas

```bash
cd D:\Clase\PPS\Proyecto\Maktub
composer install
copy .env.example .env      # Linux/Mac: cp .env.example .env
php artisan key:generate
php artisan test
```

Resultado esperado:

```text
Tests:    37 passed (147 assertions)
```

No es necesario migrar ni sembrar la base de datos local: `phpunit.xml` usa SQLite en
memoria y `RefreshDatabase` crea las tablas en cada ejecución.

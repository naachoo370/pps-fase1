/* Cliente web estático de la API común de Maktub.
 * - Habla siempre con fetch y JSON.
 * - Guarda el token de sesión en localStorage.
 * - Pinta los datos con textContent (nunca HTML crudo) para evitar XSS.
 */
(() => {
    "use strict";

    const TOKEN_KEY = "maktub_api_token";
    const USER_KEY = "maktub_api_user";

    let token = localStorage.getItem(TOKEN_KEY) || "";
    let currentUser = localStorage.getItem(USER_KEY) || "";
    let currentItems = [];

    const $ = (id) => document.getElementById(id);

    const authCard = $("auth-card");
    const appCard = $("app-card");
    const authMessage = $("auth-message");
    const appMessage = $("app-message");
    const currentUserEl = $("current-user");
    const itemsList = $("items-list");
    const emptyState = $("empty-state");

    const tabLogin = $("tab-login");
    const tabRegister = $("tab-register");
    const loginForm = $("login-form");
    const registerForm = $("register-form");

    const itemForm = $("item-form");
    const itemIdInput = $("item-id");
    const itemTitle = $("item-title");
    const itemText = $("item-text");
    const itemSubmit = $("item-submit");
    const itemCancel = $("item-cancel");

    function setMessage(element, text, type) {
        element.textContent = text || "";
        element.classList.remove("ok", "error");
        if (type) {
            element.classList.add(type);
        }
    }

    function saveSession(newToken, username) {
        token = newToken;
        currentUser = username || "";
        localStorage.setItem(TOKEN_KEY, token);
        localStorage.setItem(USER_KEY, currentUser);
    }

    function clearSession() {
        token = "";
        currentUser = "";
        localStorage.removeItem(TOKEN_KEY);
        localStorage.removeItem(USER_KEY);
    }

    function showAuth() {
        authCard.classList.remove("is-hidden");
        appCard.classList.add("is-hidden");
        itemsList.replaceChildren();
        currentItems = [];
    }

    function showApp() {
        authCard.classList.add("is-hidden");
        appCard.classList.remove("is-hidden");
        currentUserEl.textContent = currentUser || "—";
        loadItems();
    }

    function resetItemForm() {
        itemForm.reset();
        itemIdInput.value = "";
        itemSubmit.textContent = "Añadir elemento";
        itemCancel.classList.add("is-hidden");
    }

    async function api(path, options = {}) {
        const headers = Object.assign(
            { Accept: "application/json" },
            options.headers || {}
        );

        if (options.body !== undefined) {
            headers["Content-Type"] = "application/json";
        }

        if (token) {
            headers.Authorization = "Bearer " + token;
        }

        const response = await fetch(path, Object.assign({}, options, { headers }));
        const raw = await response.text();
        let data = null;

        if (raw) {
            try {
                data = JSON.parse(raw);
            } catch (error) {
                data = null;
            }
        }

        if (response.status === 401 && path !== "/api/login") {
            clearSession();
            showAuth();
            setMessage(authMessage, "La sesión ha caducado. Vuelve a iniciar sesión.", "error");
        }

        return { response, data };
    }

    /* ------------------------- Pestañas login / registro ------------------------- */

    function activateTab(which) {
        const loginActive = which === "login";

        tabLogin.classList.toggle("is-active", loginActive);
        tabRegister.classList.toggle("is-active", !loginActive);
        tabLogin.setAttribute("aria-selected", String(loginActive));
        tabRegister.setAttribute("aria-selected", String(!loginActive));

        loginForm.classList.toggle("is-hidden", !loginActive);
        registerForm.classList.toggle("is-hidden", loginActive);
        setMessage(authMessage, "", null);
    }

    tabLogin.addEventListener("click", () => activateTab("login"));
    tabRegister.addEventListener("click", () => activateTab("register"));

    /* ---------------------------------- Login ---------------------------------- */

    loginForm.addEventListener("submit", async (event) => {
        event.preventDefault();

        const username = $("login-username").value.trim();
        const password = $("login-password").value;

        if (!username || !password) {
            setMessage(authMessage, "Escribe el usuario y la contraseña.", "error");
            return;
        }

        setMessage(authMessage, "Entrando…", null);

        try {
            const { response, data } = await api("/api/login", {
                method: "POST",
                body: JSON.stringify({ username, password }),
            });

            if (response.ok && data && data.token) {
                saveSession(data.token, data.username || username);
                loginForm.reset();
                setMessage(authMessage, "", null);
                showApp();
                setMessage(appMessage, "Sesión iniciada. Estos son tus elementos.", "ok");
            } else {
                setMessage(
                    authMessage,
                    (data && data.error) || "No se pudo iniciar sesión.",
                    "error"
                );
            }
        } catch (error) {
            setMessage(authMessage, "No hay conexión con el servidor.", "error");
        }
    });

    /* -------------------------------- Registro -------------------------------- */

    registerForm.addEventListener("submit", async (event) => {
        event.preventDefault();

        const username = $("register-username").value.trim();
        const password = $("register-password").value;
        const repeat = $("register-password-repeat").value;

        if (!username || !password) {
            setMessage(authMessage, "Escribe un usuario y una contraseña.", "error");
            return;
        }

        if (password.length < 8) {
            setMessage(authMessage, "La contraseña debe tener al menos 8 caracteres.", "error");
            return;
        }

        if (password !== repeat) {
            setMessage(authMessage, "Las contraseñas no coinciden.", "error");
            return;
        }

        setMessage(authMessage, "Creando la cuenta…", null);

        try {
            const { response, data } = await api("/api/register", {
                method: "POST",
                body: JSON.stringify({ username, password }),
            });

            if (response.status === 201) {
                // Registro correcto: entramos automáticamente con las mismas credenciales.
                const login = await api("/api/login", {
                    method: "POST",
                    body: JSON.stringify({ username, password }),
                });

                if (login.response.ok && login.data && login.data.token) {
                    saveSession(login.data.token, login.data.username || username);
                    registerForm.reset();
                    setMessage(authMessage, "", null);
                    showApp();
                    setMessage(appMessage, "Cuenta creada y sesión iniciada.", "ok");
                } else {
                    activateTab("login");
                    $("login-username").value = username;
                    setMessage(authMessage, "Cuenta creada. Inicia sesión.", "ok");
                }
            } else if (response.status === 409) {
                setMessage(authMessage, "Ese usuario ya existe. Prueba con otro.", "error");
            } else {
                setMessage(
                    authMessage,
                    (data && data.error) || "No se pudo crear la cuenta.",
                    "error"
                );
            }
        } catch (error) {
            setMessage(authMessage, "No hay conexión con el servidor.", "error");
        }
    });

    /* -------------------------------- Elementos -------------------------------- */

    async function loadItems() {
        try {
            const { response, data } = await api("/api/items");

            if (!response.ok) {
                if (response.status !== 401) {
                    setMessage(appMessage, (data && data.error) || "No se pudieron cargar los elementos.", "error");
                }
                return;
            }

            currentItems = Array.isArray(data) ? data : [];
            renderItems(currentItems);
        } catch (error) {
            setMessage(appMessage, "No hay conexión con el servidor.", "error");
        }
    }

    function renderItems(items) {
        itemsList.replaceChildren();
        emptyState.classList.toggle("is-hidden", items.length > 0);

        for (const item of items) {
            const li = document.createElement("li");
            li.className = "item";

            const title = document.createElement("h3");
            title.textContent = item.title;

            const text = document.createElement("p");
            text.textContent = item.text;

            const meta = document.createElement("span");
            meta.className = "meta";
            meta.textContent = "Elemento #" + item.id;

            const actions = document.createElement("div");
            actions.className = "actions";

            const edit = document.createElement("button");
            edit.type = "button";
            edit.className = "btn ghost";
            edit.textContent = "Editar";
            edit.addEventListener("click", () => startEdit(item));

            const remove = document.createElement("button");
            remove.type = "button";
            remove.className = "btn danger";
            remove.textContent = "Borrar";
            remove.addEventListener("click", () => deleteItem(item));

            actions.append(edit, remove);
            li.append(title, text, meta, actions);
            itemsList.append(li);
        }
    }

    function startEdit(item) {
        itemIdInput.value = String(item.id);
        itemTitle.value = item.title;
        itemText.value = item.text;
        itemSubmit.textContent = "Guardar cambios";
        itemCancel.classList.remove("is-hidden");
        setMessage(appMessage, "Editando el elemento #" + item.id + ".", null);
        itemTitle.focus();
    }

    itemCancel.addEventListener("click", () => {
        resetItemForm();
        setMessage(appMessage, "", null);
    });

    itemForm.addEventListener("submit", async (event) => {
        event.preventDefault();

        const title = itemTitle.value.trim();
        const text = itemText.value.trim();

        if (!title || !text) {
            setMessage(appMessage, "El título y el texto son obligatorios.", "error");
            return;
        }

        if (title.length > 100) {
            setMessage(appMessage, "El título no puede superar 100 caracteres.", "error");
            return;
        }

        if (text.length > 1000) {
            setMessage(appMessage, "El texto no puede superar 1000 caracteres.", "error");
            return;
        }

        const id = itemIdInput.value;
        const editing = id !== "";

        try {
            const { response, data } = await api(editing ? "/api/items/" + id : "/api/items", {
                method: editing ? "PUT" : "POST",
                body: JSON.stringify({ title, text }),
            });

            if (response.ok) {
                resetItemForm();
                setMessage(appMessage, editing ? "Elemento actualizado." : "Elemento creado.", "ok");
                await loadItems();
            } else {
                setMessage(appMessage, (data && data.error) || "No se pudo guardar el elemento.", "error");
            }
        } catch (error) {
            setMessage(appMessage, "No hay conexión con el servidor.", "error");
        }
    });

    async function deleteItem(item) {
        if (!window.confirm("¿Seguro que quieres borrar «" + item.title + "»?")) {
            return;
        }

        try {
            const { response, data } = await api("/api/items/" + item.id, { method: "DELETE" });

            if (response.status === 204) {
                if (itemIdInput.value === String(item.id)) {
                    resetItemForm();
                }
                setMessage(appMessage, "Elemento borrado.", "ok");
                await loadItems();
            } else {
                setMessage(appMessage, (data && data.error) || "No se pudo borrar el elemento.", "error");
            }
        } catch (error) {
            setMessage(appMessage, "No hay conexión con el servidor.", "error");
        }
    }

    /* --------------------------------- Logout --------------------------------- */

    $("logout").addEventListener("click", async () => {
        try {
            await api("/api/logout", { method: "POST" });
        } catch (error) {
            // Aunque falle la red, cerramos la sesión en el navegador.
        }

        clearSession();
        resetItemForm();
        setMessage(appMessage, "", null);
        showAuth();
        setMessage(authMessage, "Sesión cerrada.", "ok");
    });

    /* --------------------------------- Arranque -------------------------------- */

    if (token) {
        showApp();
    } else {
        showAuth();
    }
})();

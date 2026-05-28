(() => {
    const formatter = new Intl.NumberFormat("es-VE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

    const parseNumber = (value) => {
        const numeric = Number.parseFloat(value ?? "0");
        return Number.isFinite(numeric) ? numeric : 0;
    };

    const roundMoney = (value) => Math.round((parseNumber(value) + Number.EPSILON) * 100) / 100;
    const formatNumber = (value) => formatter.format(parseNumber(value));
    const normalizeCurrency = (value) => String(value || "").trim().toUpperCase();
    const isBolivarCurrency = (currency) => ["VES", "VEF", "BS", "BS.S", "BSS", "BOLIVARES"].includes(normalizeCurrency(currency));
    const todayLocalYmd = () => {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, "0");
        const day = String(now.getDate()).padStart(2, "0");
        return `${year}-${month}-${day}`;
    };
    const addDaysToYmd = (value, days) => {
        const source = String(value || "").trim();
        const date = source ? new Date(`${source}T00:00:00`) : new Date();
        if (Number.isNaN(date.getTime())) {
            return source || todayLocalYmd();
        }

        const safeDays = Number.parseInt(days ?? 0, 10);
        date.setDate(date.getDate() + (Number.isFinite(safeDays) ? safeDays : 0));
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const day = String(date.getDate()).padStart(2, "0");
        return `${year}-${month}-${day}`;
    };

    const convertCurrencyAmount = (amount, fromCurrency, toCurrency, rate) => {
        const numericAmount = parseNumber(amount);
        const numericRate = parseNumber(rate);
        const from = normalizeCurrency(fromCurrency);
        const to = normalizeCurrency(toCurrency);

        if (numericAmount === 0 || !from || !to || from === to) {
            return numericAmount;
        }

        if (numericRate <= 0) {
            return 0;
        }

        if (isBolivarCurrency(from) && !isBolivarCurrency(to)) {
            return numericAmount / numericRate;
        }

        if (!isBolivarCurrency(from) && isBolivarCurrency(to)) {
            return numericAmount * numericRate;
        }

        return numericAmount;
    };

    const convertToBolivars = (amount, currency, rate) => {
        return convertCurrencyAmount(amount, currency, "VES", rate);
    };

    const queryScope = (scope) => scope && typeof scope.querySelector === "function" ? scope : document;

    const setText = (selector, value, scope) => {
        const element = queryScope(scope).querySelector(selector);
        if (element) {
            element.textContent = formatNumber(value);
        }
    };

    const setNodeText = (selector, value, scope) => {
        const element = queryScope(scope).querySelector(selector);
        if (element) {
            element.textContent = String(value ?? "");
        }
    };

    const resetSubmittingButtons = () => {
        document.querySelectorAll("button.is-loading").forEach((button) => {
            button.disabled = false;
            button.classList.remove("is-loading");
            if (button.dataset.originalText) {
                button.textContent = button.dataset.originalText;
            }
        });
    };
    const SCROLL_RESTORE_KEY = "inventario.scroll.restore";
    const normalizeLocationPath = (value) => {
        try {
            const url = new URL(String(value || window.location.href), window.location.origin);
            return `${url.pathname}${url.search}`;
        } catch (error) {
            return `${window.location.pathname}${window.location.search}`;
        }
    };
    const persistScrollRestore = (targetUrl) => {
        try {
            const payload = {
                path: normalizeLocationPath(targetUrl),
                x: window.scrollX || 0,
                y: window.scrollY || 0,
                storedAt: Date.now()
            };
            window.sessionStorage.setItem(SCROLL_RESTORE_KEY, JSON.stringify(payload));
        } catch (error) {
        }
    };
    const restorePersistedScroll = () => {
        try {
            const raw = window.sessionStorage.getItem(SCROLL_RESTORE_KEY);
            if (!raw) {
                return;
            }

            const payload = JSON.parse(raw);
            const currentPath = normalizeLocationPath(window.location.href);
            const isFresh = Math.abs(Date.now() - Number(payload?.storedAt || 0)) < 120000;
            if (!payload || payload.path !== currentPath || !isFresh) {
                window.sessionStorage.removeItem(SCROLL_RESTORE_KEY);
                return;
            }

            window.sessionStorage.removeItem(SCROLL_RESTORE_KEY);
            const x = Number(payload.x || 0);
            const y = Number(payload.y || 0);
            const restore = () => window.scrollTo({ left: x, top: y, behavior: "auto" });

            window.requestAnimationFrame(() => {
                restore();
                window.setTimeout(restore, 60);
                window.setTimeout(restore, 180);
            });
        } catch (error) {
            try {
                window.sessionStorage.removeItem(SCROLL_RESTORE_KEY);
            } catch (cleanupError) {
            }
        }
    };

    restorePersistedScroll();

    const menuTriggers = Array.from(document.querySelectorAll("[data-menu-open]"));
    const sidebarToggleButton = document.querySelector("[data-sidebar-toggle]");
    const sidebarToggleLabel = document.querySelector("[data-sidebar-toggle-label]");
    const sidebarToggleIcon = document.querySelector("[data-sidebar-toggle-icon]");
    const userMenuShell = document.querySelector("[data-user-menu-shell]");
    const userMenuToggle = document.querySelector("[data-user-menu-toggle]");
    const userMenuPanel = document.querySelector("[data-user-menu]");
    const modalShells = Array.from(document.querySelectorAll(".modal-shell"));
    const SIDEBAR_STATE_KEY = "inventario.sidebar.collapsed";

    modalShells.forEach((modal) => {
        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
    });

    const syncMenuState = () => {
        const isOpen = document.body.classList.contains("menu-open");
        menuTriggers.forEach((button) => {
            button.setAttribute("aria-expanded", String(isOpen));
        });
    };

    const isDesktopViewport = () => window.innerWidth > 1024;

    const syncSidebarState = () => {
        if (!sidebarToggleButton) {
            return;
        }

        const collapsed = isDesktopViewport() && document.body.classList.contains("sidebar-collapsed");
        const actionLabel = collapsed ? "Expandir menu lateral" : "Compactar menu lateral";
        sidebarToggleButton.setAttribute("aria-pressed", String(collapsed));
        sidebarToggleButton.setAttribute("title", actionLabel);
        sidebarToggleButton.setAttribute("aria-label", actionLabel);

        if (sidebarToggleLabel) {
            sidebarToggleLabel.textContent = collapsed ? "Expandir" : "Compactar";
        }

        if (sidebarToggleIcon) {
            sidebarToggleIcon.classList.remove("bi-chevron-double-left", "bi-chevron-double-right");
            sidebarToggleIcon.classList.add(collapsed ? "bi-chevron-double-right" : "bi-chevron-double-left");
        }
    };

    const applySidebarPreference = () => {
        if (!sidebarToggleButton) {
            return;
        }

        if (!isDesktopViewport()) {
            document.body.classList.remove("sidebar-collapsed");
            syncSidebarState();
            return;
        }

        const saved = window.localStorage.getItem(SIDEBAR_STATE_KEY);
        document.body.classList.toggle("sidebar-collapsed", saved === "1");
        syncSidebarState();
    };

    const closeMenu = () => {
        document.body.classList.remove("menu-open");
        syncMenuState();
    };

    const openMenu = () => {
        document.body.classList.add("menu-open");
        syncMenuState();
    };

    const closeUserMenu = () => {
        if (!userMenuToggle || !userMenuPanel) {
            return;
        }

        userMenuToggle.setAttribute("aria-expanded", "false");
        userMenuPanel.hidden = true;
    };

    const openUserMenu = () => {
        if (!userMenuToggle || !userMenuPanel) {
            return;
        }

        userMenuToggle.setAttribute("aria-expanded", "true");
        userMenuPanel.hidden = false;
    };

    let activeModal = null;

    const closeModal = (modal) => {
        if (!modal) {
            return;
        }

        modal.classList.remove("is-open");
        modal.setAttribute("aria-hidden", "true");
        document.body.classList.remove("modal-open");
        activeModal = null;
    };

    const openModal = (name) => {
        const modal = document.querySelector(`[data-modal="${name}"]`);
        if (!modal) {
            return;
        }

        modal.classList.add("is-open");
        modal.setAttribute("aria-hidden", "false");
        document.body.classList.add("modal-open");
        activeModal = modal;

        const firstInput = modal.querySelector("input, select, textarea, button");
        if (firstInput) {
            window.setTimeout(() => firstInput.focus(), 40);
        }
    };

    document.querySelectorAll("textarea").forEach((field) => {
        if (!field.getAttribute("rows")) {
            field.setAttribute("rows", "3");
        }
    });

    document.addEventListener("wheel", (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement) || target.type !== "number") {
            return;
        }

        if (document.activeElement !== target) {
            return;
        }

        event.preventDefault();
    }, { passive: false });

    document.addEventListener("keydown", (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement) || target.type !== "number") {
            return;
        }

        if (event.key !== "ArrowUp" && event.key !== "ArrowDown") {
            return;
        }

        event.preventDefault();
    });

    document.querySelectorAll("[data-nav-link]").forEach((link) => {
        link.addEventListener("click", () => closeMenu());
    });

    const ALERT_CENTER_SEEN_KEY = "inventario.alertCenter.seen";
    let alertCenterSeenThisSession = false;
    try {
        alertCenterSeenThisSession = window.sessionStorage.getItem(ALERT_CENTER_SEEN_KEY) === "1";
    } catch (error) {
        alertCenterSeenThisSession = false;
    }

    const markAlertCenterAsSeen = (toggle) => {
        if (!toggle) return;
        toggle.setAttribute("data-seen", "1");
        if (!alertCenterSeenThisSession) {
            alertCenterSeenThisSession = true;
            try {
                window.sessionStorage.setItem(ALERT_CENTER_SEEN_KEY, "1");
            } catch (error) {
                // sin sessionStorage seguimos solo con el flag en memoria
            }
        }
    };

    document.querySelectorAll("[data-alert-center]").forEach((shell) => {
        const toggle = shell.querySelector("[data-alert-center-toggle]");
        const panel = shell.querySelector("[data-alert-center-panel]");
        if (!toggle || !panel) {
            return;
        }

        if (alertCenterSeenThisSession) {
            toggle.setAttribute("data-seen", "1");
        }

        const closePanel = () => {
            panel.hidden = true;
            toggle.setAttribute("aria-expanded", "false");
        };
        const openPanel = () => {
            panel.hidden = false;
            toggle.setAttribute("aria-expanded", "true");
            markAlertCenterAsSeen(toggle);
        };

        toggle.addEventListener("click", (event) => {
            event.stopPropagation();
            if (panel.hidden) {
                openPanel();
            } else {
                closePanel();
            }
        });

        document.addEventListener("click", (event) => {
            if (!shell.contains(event.target)) {
                closePanel();
            }
        });

        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape" && !panel.hidden) {
                closePanel();
            }
        });
    });

    if (userMenuToggle && userMenuPanel && userMenuShell) {
        userMenuToggle.addEventListener("click", (event) => {
            event.stopPropagation();
            const expanded = userMenuToggle.getAttribute("aria-expanded") === "true";
            if (expanded) {
                closeUserMenu();
                return;
            }

            openUserMenu();
        });

        document.addEventListener("click", (event) => {
            if (!userMenuShell.contains(event.target)) {
                closeUserMenu();
            }
        });
    }

    const restoreSubmitButton = (button) => {
        if (!button) {
            return;
        }
        if (button.dataset.originalText) {
            button.textContent = button.dataset.originalText;
            delete button.dataset.originalText;
        }
        button.disabled = false;
        button.classList.remove("is-loading");
    };

    const setSubmitButtonLoading = (button) => {
        if (!button || button.classList.contains("is-loading")) {
            return;
        }
        button.dataset.originalText = button.textContent;
        button.classList.add("is-loading");
        button.textContent = "Procesando...";
        button.disabled = true;
    };

    const showFormAlert = (title, message, icon = "warning") => {
        const safeMessage = String(message || "Revisa los datos e intenta de nuevo.");
        if (window.Swal) {
            window.Swal.fire({
                title: String(title || "Atencion"),
                text: safeMessage,
                icon,
                confirmButtonText: "Entendido",
                confirmButtonColor: "#2f6f68",
            });
        } else {
            window.alert(safeMessage);
        }
    };

    const DOCUMENT_PROMPT_KEY = "inventario.documentPrompt";
    const stashDocumentPrompt = (prompt) => {
        if (!prompt || !prompt.url) {
            return;
        }
        try {
            window.sessionStorage.setItem(DOCUMENT_PROMPT_KEY, JSON.stringify(prompt));
        } catch (error) {
            // Si no hay sessionStorage solo seguimos sin guardar el prompt
        }
    };

    document.querySelectorAll("form").forEach((form) => {
        form.addEventListener("submit", (event) => {
            const skipSubmitLoading = form.dataset.noSubmitLoading === "1"
                || String(form.getAttribute("target") || "").toLowerCase() === "_blank";

            if (form.dataset.ajaxForm === "1") {
                event.preventDefault();

                if (form.dataset.ajaxFormBusy === "1") {
                    return;
                }
                form.dataset.ajaxFormBusy = "1";

                const button = form.querySelector("button[type='submit'], button:not([type])");
                if (button) {
                    setSubmitButtonLoading(button);
                }

                const method = (form.method || "POST").toUpperCase();
                const url = form.action || window.location.href;
                const formData = new FormData(form);
                const fetchOptions = {
                    method,
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                    credentials: "same-origin",
                };

                if (method === "GET") {
                    const params = new URLSearchParams(formData).toString();
                    const separator = url.includes("?") ? "&" : "?";
                    fetchOptions.url = params ? `${url}${separator}${params}` : url;
                } else {
                    fetchOptions.body = formData;
                }

                fetch(fetchOptions.url || url, fetchOptions)
                    .then(async (response) => {
                        let payload = {};
                        try {
                            payload = await response.json();
                        } catch (error) {
                            payload = {};
                        }

                        if (response.ok && (payload.ok === undefined || payload.ok === true)) {
                            if (payload.document_prompt) {
                                stashDocumentPrompt(payload.document_prompt);
                            }
                            const redirectTo = payload.redirect || form.dataset.ajaxFormRedirect;
                            if (redirectTo) {
                                window.location.href = redirectTo;
                            } else {
                                window.location.reload();
                            }
                            return;
                        }

                        delete form.dataset.ajaxFormBusy;
                        restoreSubmitButton(button);
                        showFormAlert(
                            "No se pudo guardar",
                            payload.message || "Revisa los datos del formulario e intenta de nuevo.",
                            "warning",
                        );
                    })
                    .catch(() => {
                        delete form.dataset.ajaxFormBusy;
                        restoreSubmitButton(button);
                        showFormAlert(
                            "Sin conexion",
                            "No pudimos comunicarnos con el servidor. Verifica tu conexion e intenta de nuevo.",
                            "error",
                        );
                    });

                return;
            }

            if (skipSubmitLoading) {
                return;
            }

            const button = form.querySelector("button[type='submit'], button:not([type])");
            if (!button) {
                return;
            }

            setSubmitButtonLoading(button);
        });
    });

    menuTriggers.forEach((button) => {
        button.addEventListener("click", () => {
            if (document.body.classList.contains("menu-open")) {
                closeMenu();
                return;
            }

            openMenu();
        });
    });

    if (sidebarToggleButton) {
        sidebarToggleButton.addEventListener("click", () => {
            if (!isDesktopViewport()) {
                return;
            }

            const collapsed = document.body.classList.toggle("sidebar-collapsed");
            window.localStorage.setItem(SIDEBAR_STATE_KEY, collapsed ? "1" : "0");
            syncSidebarState();
        });

        applySidebarPreference();
    }

    document.querySelectorAll("[data-menu-close]").forEach((button) => {
        button.addEventListener("click", () => closeMenu());
    });

    document.querySelectorAll("[data-modal-open]").forEach((button) => {
        button.addEventListener("click", () => openModal(button.dataset.modalOpen));
    });

    document.querySelectorAll("[data-modal-close]").forEach((button) => {
        button.addEventListener("click", () => closeModal(button.closest(".modal-shell")));
    });

    document.querySelectorAll("form[data-logout-confirm]").forEach((form) => {
        form.addEventListener("submit", (event) => {
            if (form.dataset.logoutConfirmed === "1") {
                return;
            }

            event.preventDefault();

            const triggerSubmit = () => {
                form.dataset.logoutConfirmed = "1";
                if (typeof form.requestSubmit === "function") {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            };

            if (window.Swal) {
                window.Swal.fire({
                    title: "Cerrar sesion",
                    text: "Vas a salir del sistema. Tus cambios sin guardar se perderan. Quieres continuar?",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Si, cerrar sesion",
                    cancelButtonText: "Cancelar",
                    confirmButtonColor: "#b91c1c",
                    cancelButtonColor: "#64748b",
                    focusCancel: true,
                    reverseButtons: true,
                }).then((result) => {
                    if (result.isConfirmed) {
                        triggerSubmit();
                    }
                });
            } else if (window.confirm("Cerrar sesion? Tus cambios sin guardar se perderan.")) {
                triggerSubmit();
            }
        });
    });

    const buildCurrencyOptions = (documentCurrency, modal) => {
        const values = [];
        const normalizedDocument = normalizeCurrency(documentCurrency);
        const baseCurrency = normalizeCurrency(modal?.dataset.paymentCurrencyBase || "USD");
        const secondaryCurrency = normalizeCurrency(modal?.dataset.paymentCurrencySecondary || "VES");

        [normalizedDocument, secondaryCurrency, baseCurrency].forEach((value) => {
            if (!value || values.includes(value)) {
                return;
            }

            values.push(value);
        });

        return values;
    };

    document.querySelectorAll("[data-document-payment-open]").forEach((button) => {
        button.addEventListener("click", () => {
            const modalName = button.dataset.modalOpen;
            if (!modalName) {
                return;
            }

            const modal = document.querySelector(`[data-modal="${modalName}"]`);
            if (!modal) {
                return;
            }

            const form = modal.querySelector("[data-document-payment-form]");
            const title = modal.querySelector("[data-document-payment-modal-title]");
            const totalNode = modal.querySelector("[data-document-payment-total]");
            const paidNode = modal.querySelector("[data-document-payment-paid]");
            const balanceNode = modal.querySelector("[data-document-payment-balance]");
            const dueDateNode = modal.querySelector("[data-document-payment-due-date]");
            const currencySelect = modal.querySelector("[data-document-payment-currency-select]");
            const titlePrefix = button.dataset.documentPaymentVerb || modal.dataset.documentPaymentVerb || "Registrar cobro";

            if (title) {
                title.textContent = `${titlePrefix} ${button.dataset.documentPaymentTitle || ""}`.trim();
            }

            if (form) {
                form.action = button.dataset.documentPaymentAction || "";
                const amountInput = form.querySelector("[name='amount_original']");
                const referenceInput = form.querySelector("[name='reference']");
                const notesInput = form.querySelector("[name='notes']");

                if (amountInput) {
                    amountInput.value = "";
                }

                if (referenceInput) {
                    referenceInput.value = "";
                }

                if (notesInput) {
                    notesInput.value = "";
                }
            }

            const currencyCode = normalizeCurrency(button.dataset.documentPaymentCurrency || "");
            const totalText = `${formatNumber(button.dataset.documentPaymentTotal || 0)} ${currencyCode}`.trim();
            const paidText = `${formatNumber(button.dataset.documentPaymentPaid || 0)} ${currencyCode}`.trim();
            const balanceText = `${formatNumber(button.dataset.documentPaymentBalance || 0)} ${currencyCode}`.trim();

            if (totalNode) {
                totalNode.textContent = totalText;
            }

            if (paidNode) {
                paidNode.textContent = paidText;
            }

            if (balanceNode) {
                balanceNode.textContent = balanceText;
            }

            if (dueDateNode) {
                dueDateNode.textContent = button.dataset.documentPaymentDueDate || "--";
            }

            if (currencySelect) {
                currencySelect.innerHTML = "";
                buildCurrencyOptions(currencyCode, modal).forEach((currency) => {
                    const option = document.createElement("option");
                    option.value = currency;
                    option.textContent = currency;
                    option.selected = currency === currencyCode;
                    currencySelect.appendChild(option);
                });
            }
        });
    });

    document.querySelectorAll("[data-confirm-delete-open]").forEach((button) => {
        button.addEventListener("click", () => {
            const modalName = button.dataset.modalOpen;
            if (!modalName) {
                return;
            }

            const modal = document.querySelector(`[data-modal="${modalName}"]`);
            if (!modal) {
                return;
            }

            const form = modal.querySelector("[data-confirm-delete-form]");
            const title = modal.querySelector("[data-confirm-delete-title]");
            const prompt = modal.querySelector("[data-confirm-delete-prompt]");
            const input = modal.querySelector("[data-confirm-delete-input]");

            if (form) {
                form.action = button.dataset.confirmDeleteAction || "";
            }

            if (title) {
                title.textContent = button.dataset.confirmDeleteTitle || "Confirmar eliminacion";
            }

            if (prompt) {
                prompt.textContent = button.dataset.confirmDeletePrompt || "";
            }

            if (input) {
                input.value = "";
                input.placeholder = button.dataset.confirmDeletePlaceholder || "";
            }
        });
    });

    const purchaseEditModal = document.querySelector("[data-modal='purchase-edit-modal']");
    const purchaseEditContainer = purchaseEditModal?.querySelector("[data-purchase-edit-container]");

    document.querySelectorAll("[data-purchase-edit-open]").forEach((button) => {
        button.addEventListener("click", async () => {
            const url = button.dataset.purchaseEditUrl;
            if (!purchaseEditContainer || !url) {
                return;
            }

            purchaseEditContainer.innerHTML = `
                <header class="modal-header">
                    <div>
                        <span class="eyebrow">Compra</span>
                        <h3>Editar compra</h3>
                    </div>
                    <button type="button" class="modal-close" data-modal-close>&times;</button>
                </header>
                <div class="empty-state">Cargando formulario...</div>
            `;

            purchaseEditContainer.querySelectorAll("[data-modal-close]").forEach((modalButton) => {
                modalButton.addEventListener("click", () => closeModal(purchaseEditModal));
            });

            try {
                const response = await fetch(url, {
                    headers: {
                        Accept: "application/json"
                    }
                });

                if (!response.ok) {
                    throw new Error("purchase_edit_failed");
                }

                const payload = await response.json();
                purchaseEditContainer.innerHTML = payload?.html || '<div class="empty-state">No se pudo cargar el formulario.</div>';

                purchaseEditContainer.querySelectorAll("[data-modal-close]").forEach((modalButton) => {
                    modalButton.addEventListener("click", () => closeModal(purchaseEditModal));
                });

                purchaseEditContainer.querySelectorAll("[data-line-items]").forEach((shell) => initLineItemsShell(shell));
                purchaseEditContainer.querySelectorAll("form[data-due-days]").forEach((form) => initDueDateForm(form));
            } catch (error) {
                purchaseEditContainer.innerHTML = `
                    <header class="modal-header">
                        <div>
                            <span class="eyebrow">Compra</span>
                            <h3>Editar compra</h3>
                        </div>
                        <button type="button" class="modal-close" data-modal-close>&times;</button>
                    </header>
                    <div class="empty-state">No se pudo cargar el formulario en este momento.</div>
                `;

                purchaseEditContainer.querySelectorAll("[data-modal-close]").forEach((modalButton) => {
                    modalButton.addEventListener("click", () => closeModal(purchaseEditModal));
                });
            }
        });
    });

    const documentPreviewCache = new Map();

    document.querySelectorAll("[data-document-preview-open]").forEach((button) => {
        button.addEventListener("click", async () => {
            const modalName = button.dataset.modalOpen;
            const detailUrl = button.dataset.documentPreviewUrl;
            if (!modalName || !detailUrl) {
                return;
            }

            const modal = document.querySelector(`[data-modal="${modalName}"]`);
            if (!modal) {
                return;
            }

            const titleNode = modal.querySelector("[data-document-preview-title]");
            const clientNode = modal.querySelector("[data-document-preview-client]");
            const dateNode = modal.querySelector("[data-document-preview-date]");
            const linesNode = modal.querySelector("[data-document-preview-lines]");
            const totalNode = modal.querySelector("[data-document-preview-total]");
            const bodyNode = modal.querySelector("[data-document-preview-body]");
            const notesNode = modal.querySelector("[data-document-preview-notes]");

            if (bodyNode) {
                bodyNode.innerHTML = '<tr><td colspan="4" class="empty-state">Cargando detalle...</td></tr>';
            }

            try {
                let detail = documentPreviewCache.get(detailUrl) || null;
                if (!detail) {
                    const response = await fetch(detailUrl, {
                        headers: {
                            Accept: "application/json"
                        }
                    });

                    if (!response.ok) {
                        throw new Error("document_preview_failed");
                    }

                    const payload = await response.json();
                    detail = payload?.detail || {};
                    documentPreviewCache.set(detailUrl, detail);
                }
                const items = Array.isArray(detail.items) ? detail.items : [];
                const currencyCode = normalizeCurrency(detail.currency_code || "");

                if (titleNode) {
                    titleNode.textContent = detail.number || "Documento";
                }

                if (clientNode) {
                    clientNode.textContent = detail.client_name || "Sin cliente";
                }

                if (dateNode) {
                    dateNode.textContent = detail.date || "--";
                }

                if (linesNode) {
                    linesNode.textContent = String(detail.line_count || items.length || 0);
                }

                if (totalNode) {
                    totalNode.textContent = `${formatNumber(detail.total_original || 0)} ${currencyCode}`.trim();
                }

                if (notesNode) {
                    notesNode.textContent = detail.notes || "Sin observaciones registradas.";
                }

                if (bodyNode) {
                    bodyNode.innerHTML = "";

                    if (items.length === 0) {
                        const row = document.createElement("tr");
                        const cell = document.createElement("td");
                        cell.colSpan = 4;
                        cell.className = "empty-state";
                        cell.textContent = detail.products_summary || "Sin productos registrados.";
                        row.appendChild(cell);
                        bodyNode.appendChild(row);
                        return;
                    }

                    items.forEach((item) => {
                        const row = document.createElement("tr");
                        const productCell = document.createElement("td");
                        const quantityCell = document.createElement("td");
                        const priceCell = document.createElement("td");
                        const totalCell = document.createElement("td");

                        productCell.textContent = item.product_name || "Producto";
                        quantityCell.textContent = formatNumber(item.quantity || 0);
                        priceCell.textContent = `${formatNumber(item.price_original || 0)} ${currencyCode}`.trim();
                        totalCell.textContent = `${formatNumber(item.total_original || 0)} ${currencyCode}`.trim();

                        row.append(productCell, quantityCell, priceCell, totalCell);
                        bodyNode.appendChild(row);
                    });
                }
            } catch (error) {
                if (titleNode) {
                    titleNode.textContent = "Detalle del documento";
                }

                if (clientNode) {
                    clientNode.textContent = "No disponible";
                }

                if (dateNode) {
                    dateNode.textContent = "--";
                }

                if (linesNode) {
                    linesNode.textContent = "0";
                }

                if (totalNode) {
                    totalNode.textContent = "0,00";
                }

                if (notesNode) {
                    notesNode.textContent = "No se pudo cargar el detalle en este momento.";
                }

                if (bodyNode) {
                    bodyNode.innerHTML = '<tr><td colspan="4" class="empty-state">No se pudo cargar el detalle.</td></tr>';
                }
            }
        });
    });

    const tablePaginationRegistry = new Map();

    const collectPaginatedRows = (tbody) => Array.from(tbody.children).filter((row) => {
        if (!(row instanceof HTMLElement) || row.tagName !== "TR") {
            return false;
        }

        return !row.querySelector(".empty-state");
    });

    const syncTablePaginations = ({ target = "", resetPage = false } = {}) => {
        tablePaginationRegistry.forEach((instance) => {
            if (target && instance.filterTarget !== target) {
                return;
            }

            if (resetPage) {
                instance.page = 1;
            }

            const visibleRows = instance.rows.filter((row) => !row.classList.contains("is-filter-hidden"));
            const totalVisible = visibleRows.length;
            const totalPages = Math.max(1, Math.ceil(totalVisible / instance.pageSize));

            if (instance.page > totalPages) {
                instance.page = totalPages;
            }

            const start = totalVisible === 0 ? 0 : ((instance.page - 1) * instance.pageSize);
            const end = totalVisible === 0 ? 0 : Math.min(start + instance.pageSize, totalVisible);

            instance.rows.forEach((row) => {
                row.classList.add("is-pagination-hidden");
            });

            visibleRows.slice(start, end).forEach((row) => {
                row.classList.remove("is-pagination-hidden");
            });

            if (!instance.metaNode || !instance.pagesNode || !instance.footer) {
                return;
            }

            if (totalVisible === 0 || totalPages <= 1) {
                instance.footer.hidden = true;
            } else {
                instance.footer.hidden = false;
            }

            instance.metaNode.textContent = totalVisible === 0
                ? "Sin registros visibles."
                : `Mostrando ${start + 1}-${end} de ${totalVisible} registros`;

            instance.pagesNode.innerHTML = "";

            if (totalPages <= 1) {
                return;
            }

            const createPageButton = (label, page, disabled = false, active = false) => {
                const button = document.createElement("button");
                button.type = "button";
                button.className = `btn btn-outline btn-sm table-pagination-button${active ? " is-active" : ""}`;
                button.textContent = label;
                button.disabled = disabled;

                if (!disabled) {
                    button.addEventListener("click", () => {
                        instance.page = page;
                        syncTablePaginations({ target: instance.filterTarget });
                    });
                }

                return button;
            };

            instance.pagesNode.appendChild(createPageButton("Anterior", Math.max(1, instance.page - 1), instance.page === 1));

            const windowStart = Math.max(1, instance.page - 1);
            const windowEnd = Math.min(totalPages, windowStart + 2);
            const adjustedStart = Math.max(1, windowEnd - 2);

            for (let page = adjustedStart; page <= windowEnd; page += 1) {
                instance.pagesNode.appendChild(createPageButton(String(page), page, false, page === instance.page));
            }

            instance.pagesNode.appendChild(createPageButton("Siguiente", Math.min(totalPages, instance.page + 1), instance.page === totalPages));
        });
    };

    document.querySelectorAll("[data-table-pagination]").forEach((tbody, index) => {
        if (!(tbody instanceof HTMLElement)) {
            return;
        }

        const rows = collectPaginatedRows(tbody);
        if (rows.length === 0) {
            return;
        }

        const pageSize = Math.max(1, Number.parseInt(tbody.dataset.tablePaginationSize || "15", 10) || 15);
        const footer = document.createElement("div");
        footer.className = "table-pagination";
        footer.hidden = true;

        const metaNode = document.createElement("small");
        metaNode.className = "table-pagination-meta";

        const pagesNode = document.createElement("div");
        pagesNode.className = "table-pagination-pages";

        footer.append(metaNode, pagesNode);

        const container = tbody.closest(".table-wrap") || tbody.parentElement;
        if (container && container.parentNode) {
            container.parentNode.insertBefore(footer, container.nextSibling);
        }

        tablePaginationRegistry.set(`pagination-${index}`, {
            rows,
            page: 1,
            pageSize,
            filterTarget: String(tbody.dataset.tablePaginationFilterTarget || "").trim(),
            footer,
            metaNode,
            pagesNode,
        });
    });

    document.querySelectorAll("[data-table-filter-input]").forEach((input) => {
        const target = input.dataset.tableFilterTarget;
        if (!target) {
            return;
        }

        const rows = Array.from(document.querySelectorAll(`[data-table-filter-rows="${target}"] tr[data-filter-search]`));
        const emptyState = document.querySelector(`[data-table-filter-empty="${target}"]`);
        const countNode = document.querySelector(`[data-table-filter-count][data-table-filter-target="${target}"]`);
        const total = rows.length;
        const label = String(countNode?.dataset.tableFilterLabel || "registros").trim();

        const syncFilter = () => {
            const term = String(input.value || "").trim().toLowerCase();
            let visible = 0;

            rows.forEach((row) => {
                const haystack = String(row.dataset.filterSearch || "").toLowerCase();
                const matches = term === "" || haystack.includes(term);
                row.classList.toggle("is-filter-hidden", !matches);

                if (matches) {
                    visible += 1;
                }
            });

            if (emptyState) {
                emptyState.hidden = visible !== 0;
            }

            if (countNode) {
                countNode.textContent = term === ""
                    ? `${total} ${label}`
                    : `${visible} de ${total} ${label}`;
            }

            syncTablePaginations({ target, resetPage: true });
        };

        input.addEventListener("input", syncFilter);
        syncFilter();
    });

    syncTablePaginations();

    const renderClientOption = (client) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "client-option";
        button.dataset.clientOption = "1";
        button.dataset.id = String(client.id ?? "");
        button.dataset.name = client.name ?? "";
        button.dataset.document = client.document ?? "";
        button.dataset.phone = client.phone ?? "";
        button.dataset.email = client.email ?? "";

        const title = document.createElement("strong");
        title.textContent = client.name ?? "Cliente";

        const documentLine = document.createElement("span");
        documentLine.textContent = client.document ? client.document : "Sin documento";

        const meta = document.createElement("small");
        const invoicesCount = Number.parseInt(client.invoices_count ?? 0, 10);
        const activityCount = Number.isFinite(invoicesCount) ? invoicesCount : 0;
        const activityText = activityCount === 1 ? "1 registro reciente" : `${activityCount} registros recientes`;
        const lastDate = client.last_invoice_date ? ` | Ultimo movimiento: ${client.last_invoice_date}` : "";
        meta.textContent = `${activityText}${lastDate}`;

        button.append(title, documentLine, meta);
        return button;
    };

    document.querySelectorAll("[data-client-picker]").forEach((picker) => {
        const hiddenInput = picker.querySelector("[name='client_id']");
        const searchInput = picker.querySelector("[data-client-search]");
        const panel = picker.querySelector("[data-client-panel]");
        const results = picker.querySelector("[data-client-results]");
        const status = picker.querySelector("[data-client-status]");
        const selectedCard = picker.querySelector("[data-client-selected]");
        const selectedName = picker.querySelector("[data-client-selected-name]");
        const selectedMeta = picker.querySelector("[data-client-selected-meta]");
        const selectedState = picker.querySelector("[data-client-selected-state]");
        const clearButton = picker.querySelector("[data-client-clear]");
        const searchUrl = picker.dataset.searchUrl || "/clients/search";
        const createModalName = picker.dataset.clientCreateModal || "";

        if (!hiddenInput || !searchInput || !panel || !results || !status || !selectedCard || !selectedName || !selectedMeta) {
            return;
        }

        let debounceId = 0;
        const defaultStatusText = "Escribe 2+ letras o numeros para buscar.";
        const emptyNameText = selectedCard.dataset.emptyName || "Sin cliente seleccionado";
        const emptyMetaText = selectedCard.dataset.emptyMeta || "Busca y elige un cliente desde la lista.";
        const pendingLabel = selectedCard.dataset.pendingLabel || "Pendiente";
        const selectedLabel = selectedCard.dataset.selectedLabel || "Seleccionado";

        const hidePanel = () => {
            panel.hidden = true;
        };

        const showPanel = () => {
            panel.hidden = false;
        };

        const resetSelectionState = ({ clearQuery = false } = {}) => {
            hiddenInput.value = "";
            selectedName.textContent = emptyNameText;
            selectedMeta.textContent = emptyMetaText;
            selectedCard.classList.remove("is-selected");

            if (selectedState) {
                selectedState.textContent = pendingLabel;
            }

            if (clearButton) {
                clearButton.hidden = true;
            }

            if (clearQuery) {
                searchInput.value = "";
            }
        };

        const selectClient = (client) => {
            hiddenInput.value = String(client.id ?? "");
            searchInput.value = client.name ?? "";
            selectedName.textContent = client.name ?? "Cliente seleccionado";
            selectedCard.classList.add("is-selected");

            if (selectedState) {
                selectedState.textContent = selectedLabel;
            }

            if (clearButton) {
                clearButton.hidden = false;
            }

            const fragments = [];
            if (client.document) {
                fragments.push(client.document);
            }
            if (client.phone) {
                fragments.push(client.phone);
            }
            if (client.email) {
                fragments.push(client.email);
            }

            selectedMeta.textContent = fragments.length > 0
                ? fragments.join(" | ")
                : "Cliente listo para usar.";

            hidePanel();
        };

        const bindOptions = () => {
            results.querySelectorAll("[data-client-option]").forEach((option) => {
                option.addEventListener("click", () => {
                    selectClient({
                        id: option.dataset.id,
                        name: option.dataset.name,
                        document: option.dataset.document,
                        phone: option.dataset.phone,
                        email: option.dataset.email
                    });
                });
            });
        };

        const setStatus = (message) => {
            status.textContent = message;
        };

        const prefillClientCreateModal = (query) => {
            if (!createModalName) {
                return;
            }

            openModal(createModalName);
            const modal = document.querySelector(`[data-modal="${createModalName}"]`);
            if (!modal) {
                return;
            }

            const nameInput = modal.querySelector("[data-client-create-name]");
            const documentInput = modal.querySelector("[data-client-create-document]");
            const normalized = String(query || "").trim();
            const looksLikeDocument = /^[\dVJEGP\-\.]+$/i.test(normalized);

            if (nameInput) {
                nameInput.value = looksLikeDocument ? "" : normalized;
            }

            if (documentInput) {
                documentInput.value = looksLikeDocument ? normalized : "";
            }
        };

        const loadResults = async (query) => {
            setStatus(query.length < 2
                ? defaultStatusText
                : "Buscando clientes...");
            showPanel();

            try {
                const url = `${searchUrl}?q=${encodeURIComponent(query)}`;
                const response = await fetch(url, { headers: { Accept: "application/json" } });
                if (!response.ok) {
                    throw new Error("search_failed");
                }

                const payload = await response.json();
                const items = Array.isArray(payload.results) ? payload.results : [];

                results.innerHTML = "";
                if (items.length === 0) {
                    setStatus("Ese cliente no existe todavia.");
                    if (createModalName && query.length >= 2) {
                        const createButton = document.createElement("button");
                        createButton.type = "button";
                        createButton.className = "client-option";
                        createButton.innerHTML = `<strong>Agregar cliente nuevo</strong><span>${query}</span><small>Abre el modal y precarga lo que escribiste.</small>`;
                        createButton.addEventListener("click", () => prefillClientCreateModal(query));
                        results.appendChild(createButton);
                    }
                    return;
                }

                setStatus("Selecciona un cliente de la lista.");
                items.forEach((client) => results.appendChild(renderClientOption(client)));
                bindOptions();
            } catch (error) {
                results.innerHTML = "";
                setStatus("No se pudo buscar en este momento.");
            }
        };

        bindOptions();
        resetSelectionState();
        setStatus(defaultStatusText);

        searchInput.addEventListener("focus", () => {
            showPanel();
        });

        searchInput.addEventListener("input", () => {
            resetSelectionState();

            const query = searchInput.value.trim();
            window.clearTimeout(debounceId);
            debounceId = window.setTimeout(() => loadResults(query), 220);
        });

        if (clearButton) {
            clearButton.addEventListener("click", () => {
                resetSelectionState({ clearQuery: true });
                setStatus(defaultStatusText);
                hidePanel();
                searchInput.focus();
            });
        }

        document.addEventListener("click", (event) => {
            if (!picker.contains(event.target)) {
                hidePanel();
            }
        });
    });

    const renderProductOption = (option) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "client-option";
        button.dataset.productOption = "1";
        button.dataset.value = option.value || "";

        const title = document.createElement("strong");
        title.textContent = option.textContent.trim() || "Producto";

        const skuLine = document.createElement("span");
        skuLine.textContent = option.dataset.sku ? `SKU ${option.dataset.sku}` : "SKU no definido";

        const meta = document.createElement("small");
        const parts = [`Stock ${formatNumber(option.dataset.stock || 0)}`];
        if (parseNumber(option.dataset.price) > 0) {
            parts.push(`Precio ${formatNumber(option.dataset.price)}`);
        }
        if (parseNumber(option.dataset.cost) > 0) {
            parts.push(`Costo ${formatNumber(option.dataset.cost)}`);
        }
        meta.textContent = parts.join(" | ");

        button.append(title, skuLine, meta);
        return button;
    };

    const initProductPicker = (picker) => {
        if (!picker || picker.dataset.productPickerReady === "1") {
            return;
        }

        picker.dataset.productPickerReady = "1";
        const select = picker.querySelector("[data-product-select]");
        const searchInput = picker.querySelector("[data-product-search]");
        const panel = picker.querySelector("[data-product-panel]");
        const results = picker.querySelector("[data-product-results]");
        const status = picker.querySelector("[data-product-status]");
        const selectedCard = picker.querySelector("[data-product-selected]");
        const selectedName = picker.querySelector("[data-product-selected-name]");
        const selectedMeta = picker.querySelector("[data-product-selected-meta]");
        const selectedState = picker.querySelector("[data-product-selected-state]");
        const clearButton = picker.querySelector("[data-product-clear]");
        const lineRow = picker.closest("[data-line-item]");
        const linePriceInput = lineRow?.querySelector("[data-line-price-input]");
        const lineSourceCurrencyInput = lineRow?.querySelector("[data-line-source-currency]");
        const form = picker.closest("form");

        if (!select || !searchInput || !panel || !results || !status || !selectedCard || !selectedName || !selectedMeta) {
            return;
        }

        const options = Array.from(select.options).filter((option) => option.value !== "");
        const defaultStatusText = "Escribe SKU o nombre para filtrar productos.";
        const emptyNameText = selectedCard.dataset.emptyName || "Sin producto seleccionado";
        const emptyMetaText = selectedCard.dataset.emptyMeta || "Busca y elige un producto.";
        const pendingLabel = selectedCard.dataset.pendingLabel || "Pendiente";
        const selectedLabel = selectedCard.dataset.selectedLabel || "Seleccionado";
        let currentMatches = [];

        const showPanel = () => {
            panel.hidden = false;
        };

        const hidePanel = () => {
            panel.hidden = true;
        };

        const setStatus = (message) => {
            status.textContent = message;
        };

        const updateSelectionCard = (option) => {
            if (!option) {
                selectedCard.classList.remove("is-selected");
                selectedName.textContent = emptyNameText;
                selectedMeta.textContent = emptyMetaText;
                if (lineSourceCurrencyInput) {
                    lineSourceCurrencyInput.value = form?.dataset.referenceCurrency || "USD";
                }
                if (selectedState) {
                    selectedState.textContent = pendingLabel;
                }
                if (clearButton) {
                    clearButton.hidden = true;
                }
                return;
            }

            const metaParts = [];
            if (option.dataset.sku) {
                metaParts.push(`SKU ${option.dataset.sku}`);
            }
            metaParts.push(`Stock ${formatNumber(option.dataset.stock || 0)}`);
            if (parseNumber(option.dataset.price) > 0) {
                metaParts.push(`Precio ${formatNumber(option.dataset.price)}`);
            }
            if (parseNumber(option.dataset.cost) > 0) {
                metaParts.push(`Costo ${formatNumber(option.dataset.cost)}`);
            }

            selectedCard.classList.add("is-selected");
            selectedName.textContent = option.textContent.trim() || "Producto seleccionado";
            selectedMeta.textContent = metaParts.join(" | ");
            if (lineSourceCurrencyInput) {
                lineSourceCurrencyInput.value = option.dataset.currency || form?.dataset.referenceCurrency || "USD";
            }
            if (selectedState) {
                selectedState.textContent = selectedLabel;
            }
            if (clearButton) {
                clearButton.hidden = options.length <= 1;
            }
        };

        const resetProduct = ({ fireChange = true } = {}) => {
            select.value = "";
            searchInput.value = "";
            if (linePriceInput) {
                linePriceInput.value = "0";
            }
            updateSelectionCard(null);
            setStatus(defaultStatusText);
            hidePanel();

            if (fireChange) {
                select.dispatchEvent(new Event("change", { bubbles: true }));
            }
        };

        const selectProduct = (option, { keepQuery = true, fireChange = true } = {}) => {
            if (!option) {
                return;
            }

            select.value = option.value;
            if (linePriceInput && parseNumber(option.dataset.price) > 0) {
                linePriceInput.value = option.dataset.price;
            }
            updateSelectionCard(option);

            if (keepQuery) {
                searchInput.value = option.textContent.trim();
            } else {
                searchInput.value = "";
            }

            if (fireChange) {
                select.dispatchEvent(new Event("change", { bubbles: true }));
            }

            hidePanel();
        };

        const bindOptions = () => {
            results.querySelectorAll("[data-product-option]").forEach((button) => {
                button.addEventListener("click", () => {
                    const option = options.find((item) => item.value === button.dataset.value);
                    selectProduct(option);
                });
            });
        };

        const renderMatches = (query) => {
            const normalizedQuery = String(query || "").trim().toLowerCase();
            const matches = options.filter((option) => {
                const haystack = `${option.dataset.sku || ""} ${option.textContent || ""}`.toLowerCase();
                return normalizedQuery === "" || haystack.includes(normalizedQuery);
            }).slice(0, 12);
            currentMatches = matches;

            results.innerHTML = "";

            if (matches.length === 0) {
                setStatus("No se encontraron productos.");
                return;
            }

            setStatus("Selecciona un producto de la lista.");
            matches.forEach((option) => results.appendChild(renderProductOption(option)));
            bindOptions();
        };

        searchInput.addEventListener("focus", () => {
            renderMatches("");
            showPanel();
            searchInput.select();
        });

        searchInput.addEventListener("input", () => {
            renderMatches(searchInput.value);
            showPanel();
        });

        searchInput.addEventListener("keydown", (event) => {
            if (event.key === "Enter") {
                event.preventDefault();

                const first = currentMatches[0] || null;
                if (first) {
                    selectProduct(first);
                }
            }
        });

        select.addEventListener("change", () => {
            const current = select.options[select.selectedIndex] || null;
            updateSelectionCard(current);
        });

        selectedCard.addEventListener("click", () => {
            searchInput.focus();
            renderMatches("");
            showPanel();
        });

        if (clearButton) {
            clearButton.addEventListener("click", () => {
                resetProduct();
                searchInput.focus();
            });
        }

        document.addEventListener("click", (event) => {
            if (!picker.contains(event.target)) {
                hidePanel();
            }
        });

        const initiallySelected = select.options[select.selectedIndex] || null;
        if (initiallySelected && initiallySelected.value !== "") {
            selectProduct(initiallySelected, { fireChange: false });
            setStatus(defaultStatusText);
        } else {
            resetProduct({ fireChange: false });
        }
    };

    document.querySelectorAll("[data-product-picker]").forEach((picker) => initProductPicker(picker));

    const initLineItemsShell = (shell) => {
        if (!shell || shell.dataset.lineItemsReady === "1") {
            return;
        }

        shell.dataset.lineItemsReady = "1";
        const list = shell.querySelector("[data-line-items-list]");
        const template = shell.querySelector("[data-line-item-template]");
        const addButton = shell.querySelector("[data-line-item-add]");
        const form = shell.closest("form");
        const summaryTitle = shell.querySelector("[data-line-summary-title]");
        const summaryMeta = shell.querySelector("[data-line-summary-meta]");
        const summaryList = shell.querySelector("[data-line-summary-list]");
        const catalogSelect = shell.querySelector("[data-line-product-catalog]");
        const catalogSearch = shell.querySelector("[data-line-catalog-search]");
        const catalogResults = shell.querySelector("[data-line-catalog-results]");
        const catalogStatus = shell.querySelector("[data-line-catalog-status]");
        const catalogMinChars = 2;
        const rawLineValueKey = String(shell.dataset.lineValueKey || "").trim().toLowerCase();
        const lineValueKey = rawLineValueKey === "cost"
            ? "cost"
            : (rawLineValueKey === "price" ? "price" : "none");
        const lineValueLabel = shell.dataset.lineValueLabel || (lineValueKey === "cost" ? "Costo" : "Precio");

        if (!list || !template || !form || !summaryTitle || !summaryMeta || !summaryList || !catalogSelect || !catalogSearch || !catalogResults || !catalogStatus) {
            return;
        }

        const catalogOptions = Array.from(catalogSelect.options).filter((option) => option.value !== "");
        let nextIndex = list.querySelectorAll("[data-line-item]").length;

        const dispatchRefresh = () => {
            form.dispatchEvent(new Event("input", { bubbles: true }));
        };

        const getQuantityStepValue = (input) => {
            const quickStep = String(input?.dataset.quickStep || "").trim().toLowerCase();
            const rawStep = quickStep || String(input?.getAttribute("step") || input?.step || "1").trim().toLowerCase();
            if (rawStep === "" || rawStep === "any") {
                return 1;
            }

            const parsed = Number.parseFloat(rawStep);
            return Number.isFinite(parsed) && parsed > 0 ? parsed : 1;
        };

        const getQuantityPrecision = (input) => {
            const rawStep = String(input?.getAttribute("step") || input?.step || "1").trim().toLowerCase();
            if (rawStep === "" || rawStep === "any" || !rawStep.includes(".")) {
                return 0;
            }

            return rawStep.split(".")[1]?.length || 0;
        };

        const formatQuantityInputValue = (value, input) => {
            const precision = getQuantityPrecision(input);
            const numeric = parseNumber(value);

            if (precision <= 0) {
                return String(Math.round(numeric));
            }

            return numeric.toFixed(precision).replace(/\.?0+$/, "");
        };

        const setQuantityValue = (row, value, { dispatchChange = false } = {}) => {
            if (!row) {
                return;
            }

            const qtyInput = row.querySelector("[data-line-qty-input]");
            const quickInput = row.querySelector("[data-line-qty-quick]");
            if (!qtyInput) {
                return;
            }

            qtyInput.value = String(value ?? "");

            if (quickInput && quickInput !== document.activeElement) {
                quickInput.value = qtyInput.value;
            }

            qtyInput.dispatchEvent(new Event("input", { bubbles: true }));

            if (dispatchChange) {
                qtyInput.dispatchEvent(new Event("change", { bubbles: true }));
            }
        };

        const syncQuickQuantityControl = (row) => {
            if (!row) {
                return;
            }

            const qtyInput = row.querySelector("[data-line-qty-input]");
            const quickInput = row.querySelector("[data-line-qty-quick]");
            const minusButton = row.querySelector("[data-line-qty-step='decrease']");
            const plusButton = row.querySelector("[data-line-qty-step='increase']");
            if (!qtyInput || !quickInput) {
                return;
            }

            const minValue = qtyInput.getAttribute("min");
            const stepValue = qtyInput.getAttribute("step");
            quickInput.min = minValue !== null ? minValue : "";
            quickInput.step = stepValue !== null ? stepValue : "1";

            if (qtyInput.dataset.manualDecimalInput === "1" || qtyInput.hasAttribute("data-manual-decimal-input")) {
                quickInput.setAttribute("data-manual-decimal-input", "1");
            } else {
                quickInput.removeAttribute("data-manual-decimal-input");
            }

            if (quickInput !== document.activeElement) {
                quickInput.value = qtyInput.value;
            }

            const qtyLabel = row.querySelector("[data-line-qty-label]")?.textContent?.trim() || "Cantidad";
            quickInput.setAttribute("aria-label", `${qtyLabel} rapida`);

            const hasValue = String(qtyInput.value || "").trim() !== "";
            if (minusButton) {
                const numericMin = minValue === null || minValue === "" ? Number.NEGATIVE_INFINITY : parseNumber(minValue);
                const current = hasValue ? parseNumber(qtyInput.value) : numericMin;
                minusButton.disabled = Number.isFinite(numericMin) && current <= numericMin;
            }

            if (plusButton) {
                plusButton.disabled = false;
            }
        };

        const ensureQuickQuantityControl = (row) => {
            if (!row || row.dataset.quickQtyReady === "1") {
                syncQuickQuantityControl(row);
                return;
            }

            const qtyInput = row.querySelector("[data-line-qty-input]");
            const actions = row.querySelector(".line-item-actions");
            if (!qtyInput || !actions) {
                return;
            }

            row.dataset.quickQtyReady = "1";

            const shell = document.createElement("div");
            shell.className = "line-item-quick-qty";
            shell.setAttribute("data-line-qty-quick-wrap", "1");

            const label = document.createElement("span");
            label.textContent = "Cant.";

            const controls = document.createElement("div");
            controls.className = "line-item-quick-qty-controls";

            const minusButton = document.createElement("button");
            minusButton.type = "button";
            minusButton.className = "line-item-quick-qty-btn";
            minusButton.textContent = "-";
            minusButton.setAttribute("data-line-qty-step", "decrease");
            minusButton.setAttribute("aria-label", "Disminuir cantidad");

            const quickInput = document.createElement("input");
            quickInput.type = "number";
            quickInput.className = "line-item-quick-qty-input";
            quickInput.setAttribute("data-line-qty-quick", "1");
            quickInput.setAttribute("inputmode", "decimal");
            quickInput.autocomplete = "off";

            const plusButton = document.createElement("button");
            plusButton.type = "button";
            plusButton.className = "line-item-quick-qty-btn";
            plusButton.textContent = "+";
            plusButton.setAttribute("data-line-qty-step", "increase");
            plusButton.setAttribute("aria-label", "Aumentar cantidad");

            controls.append(minusButton, quickInput, plusButton);
            shell.append(label, controls);
            actions.insertBefore(shell, actions.firstChild);

            const handleStepClick = (direction) => {
                const step = getQuantityStepValue(qtyInput);
                const precision = getQuantityPrecision(qtyInput);
                const minRaw = qtyInput.getAttribute("min");
                const minimum = minRaw === null || minRaw === "" ? Number.NEGATIVE_INFINITY : parseNumber(minRaw);
                const current = String(qtyInput.value || "").trim() === ""
                    ? (Number.isFinite(minimum) ? minimum : 0)
                    : parseNumber(qtyInput.value);
                const next = Math.max(minimum, current + (direction * step));
                const normalized = precision > 0 ? Number(next.toFixed(precision)) : Math.round(next);
                setQuantityValue(row, formatQuantityInputValue(normalized, qtyInput), { dispatchChange: true });
                syncQuickQuantityControl(row);
            };

            minusButton.addEventListener("click", () => handleStepClick(-1));
            plusButton.addEventListener("click", () => handleStepClick(1));

            quickInput.addEventListener("input", () => {
                setQuantityValue(row, quickInput.value);
                syncQuickQuantityControl(row);
            });

            quickInput.addEventListener("change", () => {
                setQuantityValue(row, quickInput.value, { dispatchChange: true });
                syncQuickQuantityControl(row);
            });

            qtyInput.addEventListener("input", () => syncQuickQuantityControl(row));
            qtyInput.addEventListener("change", () => syncQuickQuantityControl(row));

            syncQuickQuantityControl(row);
        };

        const renderSummary = () => {
            const items = Array.from(list.querySelectorAll("[data-line-item]")).map((row) => {
                const productId = String(row.querySelector("[data-line-product-id]")?.value || "").trim();
                if (productId === "") {
                    return null;
                }

                return {
                    name: row.querySelector("[data-line-product-name]")?.textContent?.trim() || "Producto",
                    quantity: parseNumber(row.querySelector("[data-line-qty-input]")?.value)
                };
            }).filter(Boolean);

            summaryList.innerHTML = "";

            if (items.length === 0) {
                summaryTitle.textContent = "Sin productos agregados";
                summaryMeta.textContent = "Busca un producto y agregalo sin salir de esta vista.";
                return;
            }

            const totalQuantity = items.reduce((carry, item) => carry + item.quantity, 0);
            summaryTitle.textContent = items.length === 1 ? "1 producto listo" : `${items.length} productos listos`;
            summaryMeta.textContent = `${formatNumber(totalQuantity)} unidades acumuladas en el documento.`;

            items.slice(0, 4).forEach((item) => {
                const pill = document.createElement("div");
                pill.className = "line-summary-pill";
                const title = document.createElement("strong");
                title.textContent = item.name;
                const qty = document.createElement("span");
                qty.textContent = `x ${formatNumber(item.quantity)}`;
                pill.append(title, qty);
                summaryList.appendChild(pill);
            });

            if (items.length > 4) {
                const extra = document.createElement("div");
                extra.className = "line-summary-pill";
                const extraTitle = document.createElement("strong");
                extraTitle.textContent = `+${items.length - 4}`;
                const extraMeta = document.createElement("span");
                extraMeta.textContent = "productos mas";
                extra.append(extraTitle, extraMeta);
                summaryList.appendChild(extra);
            }
        };

        const setCatalogStatus = (message) => {
            catalogStatus.textContent = message;
        };

        const syncQuantityInputStep = (row) => {
            if (!row) {
                return;
            }

            const qtyInput = row.querySelector("[data-line-qty-input]");
            if (!qtyInput) {
                return;
            }

            if (!row.dataset.defaultQtyStep) {
                row.dataset.defaultQtyStep = qtyInput.getAttribute("step") || "1";
            }

            if (!row.dataset.defaultQtyMin) {
                row.dataset.defaultQtyMin = qtyInput.getAttribute("min") || "1";
            }

            const productType = String(row.dataset.productType || "").trim();
            const useDecimalStep = form.dataset.calc === "purchase" && productType === "raw_material";

            qtyInput.step = useDecimalStep ? "0.01" : row.dataset.defaultQtyStep;
            qtyInput.min = useDecimalStep ? "0.01" : row.dataset.defaultQtyMin;
            syncQuickQuantityControl(row);
        };

        const setRowExpanded = (row, expanded) => {
            if (!row) {
                return;
            }

            row.classList.toggle("is-collapsed", !expanded);
            const details = row.querySelector(".line-item-grid");
            if (details) {
                details.hidden = !expanded;
            }

            const toggleButton = row.querySelector("[data-line-toggle]");
            if (toggleButton) {
                toggleButton.textContent = expanded ? "Ocultar" : "Detalles";
                toggleButton.setAttribute("aria-expanded", expanded ? "true" : "false");
            }
        };

        const updateRowPreview = (row) => {
            if (!row) {
                return;
            }

            const metaNode = row.querySelector("[data-line-head-meta]");
            if (!metaNode) {
                return;
            }

            if (!metaNode.dataset.emptyText) {
                metaNode.dataset.emptyText = metaNode.textContent.trim();
            }

            const productId = String(row.querySelector("[data-line-product-id]")?.value || "").trim();
            if (productId === "") {
                metaNode.textContent = metaNode.dataset.emptyText || "Producto agregado al documento. Abre el detalle si necesitas editarlo.";
                return;
            }

            const name = row.querySelector("[data-line-product-name]")?.textContent?.trim() || "Producto";
            const quantity = parseNumber(row.querySelector("[data-line-qty-input]")?.value);
            const priceInput = row.querySelector("[data-line-price-input]");

            if (lineValueKey === "none" || !priceInput) {
                metaNode.textContent = `${name} | Cant. ${formatNumber(quantity)}`;
                return;
            }

            const price = parseNumber(priceInput.value);
            metaNode.textContent = `${name} | Cant. ${formatNumber(quantity)} | ${lineValueLabel} ${formatNumber(price)}`;
        };

        const bindRow = (row) => {
            if (!row || row.dataset.lineBound === "1") {
                return;
            }

            row.dataset.lineBound = "1";
            syncQuantityInputStep(row);
            ensureQuickQuantityControl(row);
            setRowExpanded(row, !!row.querySelector("[data-line-toggle]") ? false : true);
            updateRowPreview(row);

            const removeButton = row.querySelector("[data-line-remove]");
            if (removeButton) {
                removeButton.addEventListener("click", () => {
                    row.remove();
                    syncRowState();
                    renderSummary();
                    dispatchRefresh();
                });
            }

            const toggleButton = row.querySelector("[data-line-toggle]");
            if (toggleButton) {
                toggleButton.addEventListener("click", () => {
                    const isExpanded = toggleButton.getAttribute("aria-expanded") === "true";
                    setRowExpanded(row, !isExpanded);
                });
            }

            row.addEventListener("input", () => updateRowPreview(row));
            row.addEventListener("change", () => updateRowPreview(row));
        };

        const syncRowState = () => {
            const rows = Array.from(list.querySelectorAll("[data-line-item]"));
            rows.forEach((row, index) => {
                const label = row.querySelector("[data-line-label]");
                if (label) {
                    label.textContent = `Renglon ${index + 1}`;
                }

                const removeButton = row.querySelector("[data-line-remove]");
                if (removeButton) {
                    removeButton.disabled = false;
                }
            });
        };

        const createRow = () => {
            const fragment = template.content.cloneNode(true);
            fragment.querySelectorAll("[name]").forEach((field) => {
                field.name = field.name.replace(/\[__INDEX__\]/g, `[${nextIndex}]`);
            });

            nextIndex += 1;
            list.appendChild(fragment);

            const rows = list.querySelectorAll("[data-line-item]");
            const row = rows[rows.length - 1];
            bindRow(row);
            return row || null;
        };

        const hydrateRow = (row, option) => {
            if (!row || !option) {
                return;
            }

            const productIdInput = row.querySelector("[data-line-product-id]");
            const sourceCurrencyInput = row.querySelector("[data-line-source-currency]");
            const productName = row.querySelector("[data-line-product-name]");
            const productMeta = row.querySelector("[data-line-product-meta]");
            const priceInput = row.querySelector("[data-line-price-input]");
            const stock = parseNumber(option.dataset.stock || 0);
            const unitValue = lineValueKey === "none" ? 0 : parseNumber(option.dataset[lineValueKey] || 0);
            const sku = option.dataset.sku ? `SKU ${option.dataset.sku}` : "SKU no definido";
            const metaParts = [sku, `Stock ${formatNumber(stock)}`];

            if (unitValue > 0) {
                metaParts.push(`${lineValueLabel} ${formatNumber(unitValue)}`);
            }

            if (productIdInput) {
                productIdInput.value = option.value || "";
            }
            if (sourceCurrencyInput) {
                sourceCurrencyInput.value = option.dataset.currency || form.dataset.referenceCurrency || "USD";
            }
            if (productName) {
                productName.textContent = option.textContent.trim() || "Producto";
            }
            if (productMeta) {
                productMeta.textContent = metaParts.join(" | ");
            }
            if (priceInput) {
                priceInput.value = unitValue > 0 ? String(unitValue) : priceInput.value;
            }

            row.dataset.stock = String(stock);
            row.dataset.productType = String(option.dataset.productType || "").trim() || "merchandise";
            syncQuantityInputStep(row);
            updateRowPreview(row);
        };

        const addProductToDocument = (option) => {
            const row = createRow();
            if (!row) {
                return;
            }

            hydrateRow(row, option);
            syncRowState();
            renderSummary();
            dispatchRefresh();
            row.scrollIntoView({ block: "nearest", behavior: "smooth" });
        };

        const renderCatalogResults = (query = "") => {
            const normalized = String(query || "").trim().toLowerCase();
            catalogResults.innerHTML = "";

            if (normalized.length < catalogMinChars) {
                setCatalogStatus(`Escribe al menos ${catalogMinChars} caracteres, SKU o nombre para buscar.`);
                return [];
            }

            const matches = catalogOptions.filter((option) => {
                const haystack = `${option.dataset.sku || ""} ${option.textContent || ""}`.toLowerCase();
                return haystack.includes(normalized);
            }).slice(0, 16);

            if (matches.length === 0) {
                setCatalogStatus("No se encontraron productos con ese criterio.");
                return matches;
            }

            setCatalogStatus("Haz clic en un producto para agregarlo al documento.");
            matches.forEach((option) => {
                const button = renderProductOption(option);
                button.addEventListener("click", () => addProductToDocument(option));
                catalogResults.appendChild(button);
            });

            return matches;
        };

        Array.from(list.querySelectorAll("[data-line-item]")).forEach((row) => bindRow(row));
        syncRowState();
        renderSummary();
        setCatalogStatus(`Escribe al menos ${catalogMinChars} caracteres, SKU o nombre para buscar.`);
        catalogResults.innerHTML = "";

        if (addButton) {
            addButton.addEventListener("click", () => {
                catalogSearch.focus();
                catalogSearch.select();
            });
        }

        list.addEventListener("input", renderSummary);
        list.addEventListener("change", renderSummary);

        catalogSearch.addEventListener("input", () => {
            renderCatalogResults(catalogSearch.value);
        });

        catalogSearch.addEventListener("keydown", (event) => {
            if (event.key !== "Enter") {
                return;
            }

            event.preventDefault();
            const matches = renderCatalogResults(catalogSearch.value);
            const first = matches[0] || null;
            if (!first) {
                return;
            }

            addProductToDocument(first);
            catalogSearch.value = "";
            catalogResults.innerHTML = "";
            setCatalogStatus(`Producto agregado. Escribe al menos ${catalogMinChars} caracteres para buscar otro.`);
        });
    };

    document.querySelectorAll("[data-line-items]").forEach((shell) => initLineItemsShell(shell));

    const initDueDateForm = (form) => {
        if (!form || form.dataset.dueDaysReady === "1") {
            return;
        }

        form.dataset.dueDaysReady = "1";
        const days = Number.parseInt(form.dataset.dueDays || "0", 10);
        const dateInput = form.querySelector("[name='invoice_date'], [name='purchase_date'], [name='note_date']");
        const dueDisplay = form.querySelector("[data-due-date-display]");

        if (!dateInput || !dueDisplay) {
            return;
        }

        const syncDueDate = () => {
            dueDisplay.value = addDaysToYmd(dateInput.value || todayLocalYmd(), days);
        };

        dateInput.addEventListener("change", syncDueDate);
        dateInput.addEventListener("input", syncDueDate);
        syncDueDate();
    };

    document.querySelectorAll("form[data-due-days]").forEach((form) => initDueDateForm(form));

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            closeUserMenu();
        }

        if (event.key === "Escape" && activeModal) {
            closeModal(activeModal);
        }

        if (event.key === "Escape") {
            closeMenu();
        }
    });

    window.addEventListener("resize", () => {
        if (window.innerWidth > 1024) {
            closeMenu();
        }

        applySidebarPreference();
    });

    window.addEventListener("pageshow", (event) => {
        resetSubmittingButtons();

        if (event.persisted && document.querySelector("[data-refresh-on-return='1']")) {
            window.location.reload();
        }
    });

    const syncProductSelection = (form) => {
        if (form.querySelector("[data-line-items]")) {
            return null;
        }

        const select = form.querySelector("[data-product-select]");
        if (!select) {
            return null;
        }

        const option = select.options[select.selectedIndex];
        if (!option || !option.value) {
            setText("[data-selected-stock]", 0, form);
            return null;
        }

        const stock = parseNumber(option.dataset.stock);
        const price = parseNumber(option.dataset.price);
        const cost = parseNumber(option.dataset.cost);
        const optionCurrency = String(option.dataset.currency || "").trim().toUpperCase();
        const productType = String(option.dataset.productType || "").trim().toLowerCase();
        const unitLabel = String(option.dataset.unitLabel || "und").trim() || "und";
        const adjustQuantityInput = form.querySelector("[data-adjust-quantity-input]");
        const adjustQuantityLabel = form.querySelector("[data-adjust-quantity-label]");
        const adjustQuantityHint = form.querySelector("[data-adjust-quantity-hint]");

        if (form.dataset.calc === "purchase") {
            const costInput = form.querySelector("[data-cost-input]");
            if (costInput && cost > 0) {
                costInput.value = cost;
            }
        }

        if (form.dataset.calc === "invoice" || form.dataset.calc === "delivery-note") {
            const priceInput = form.querySelector("[data-price-input]");
            if (priceInput && price > 0) {
                priceInput.value = price;
            }
        }

        if (form.dataset.calc === "movement" && adjustQuantityInput) {
            const allowsDecimals = productType === "raw_material";
            adjustQuantityInput.step = allowsDecimals ? "0.01" : "1";
            if (!allowsDecimals && adjustQuantityInput.value !== "") {
                adjustQuantityInput.value = String(Math.round(parseNumber(adjustQuantityInput.value)));
            }
            if (adjustQuantityLabel) {
                adjustQuantityLabel.textContent = allowsDecimals
                    ? `Cantidad (+/-) en ${unitLabel}`
                    : `Cantidad (+/-) en ${unitLabel}`;
            }
            if (adjustQuantityHint) {
                adjustQuantityHint.textContent = allowsDecimals
                    ? `Este producto usa decimales y trabaja en ${unitLabel}.`
                    : `Este producto trabaja en enteros (${unitLabel}).`;
            }
        }

        setText("[data-selected-stock]", stock, form);
        setText("[data-selected-price]", price, form);

        return { stock, sourceCurrency: optionCurrency || "" };
    };

    document.querySelectorAll("[data-bulk-adjust-form]").forEach((form) => {
        const rows = Array.from(form.querySelectorAll("[data-bulk-adjust-row]"));
        const countNode = form.querySelector("[data-bulk-adjust-count]");
        const inNode = form.querySelector("[data-bulk-adjust-in]");
        const outNode = form.querySelector("[data-bulk-adjust-out]");
        const netNode = form.querySelector("[data-bulk-adjust-net]");

        if (rows.length === 0) {
            return;
        }

        const updateSummary = () => {
            let count = 0;
            let incoming = 0;
            let outgoing = 0;
            let net = 0;

            rows.forEach((row) => {
                const targetInput = row.querySelector("[data-bulk-target-input]");
                const differenceNode = row.querySelector("[data-bulk-difference]");
                const currentStock = parseNumber(row.dataset.stock);
                const targetValue = String(targetInput?.value || "").trim();

                if (!targetInput || !differenceNode) {
                    return;
                }

                if (targetValue === "") {
                    row.classList.remove("is-edited");
                    differenceNode.textContent = formatNumber(0);
                    differenceNode.className = "badge badge-neutral";
                    return;
                }

                const step = String(row.dataset.step || "1");
                let targetNumber = parseNumber(targetInput.value);
                if (step === "1") {
                    targetNumber = Math.round(targetNumber);
                    targetInput.value = String(targetNumber);
                }
                const difference = roundMoney(targetNumber - currentStock);

                differenceNode.textContent = formatNumber(difference);
                row.classList.toggle("is-edited", Math.abs(difference) > 0.00001);

                if (Math.abs(difference) <= 0.00001) {
                    differenceNode.className = "badge badge-neutral";
                    return;
                }

                count += 1;
                net += difference;
                if (difference > 0) {
                    incoming += difference;
                    differenceNode.className = "badge badge-ok";
                } else {
                    outgoing += Math.abs(difference);
                    differenceNode.className = "badge badge-danger";
                }
            });

            if (countNode) {
                countNode.textContent = String(count);
            }
            if (inNode) {
                inNode.textContent = formatNumber(incoming);
            }
            if (outNode) {
                outNode.textContent = formatNumber(outgoing);
            }
            if (netNode) {
                netNode.textContent = formatNumber(net);
            }
        };

        rows.forEach((row) => {
            const input = row.querySelector("[data-bulk-target-input]");
            if (!input) {
                return;
            }

            input.addEventListener("input", updateSummary);
            input.addEventListener("change", updateSummary);
        });

        updateSummary();
    });

    const collectLineItemsState = (form) => {
        const rows = Array.from(form.querySelectorAll("[data-line-item]"));
        const documentCurrency = form.querySelector("[name='currency_code']")?.value ?? "";
        const rate = parseNumber(form.querySelector("[data-rate-input]")?.value || "1");
        const fallbackSourceCurrency = form.dataset.referenceCurrency || documentCurrency || "USD";

        return rows.reduce((carry, row) => {
            const productId = String(row.querySelector("[data-line-product-id]")?.value || "").trim();
            const quantity = parseNumber(row.querySelector("[data-line-qty-input]")?.value);
            const priceReference = parseNumber(row.querySelector("[data-line-price-input]")?.value);
            const stock = parseNumber(row.dataset.stock || 0);
            const lineSourceCurrencyInput = row.querySelector("[data-line-source-currency]");
            const lineSourceCurrency = String(lineSourceCurrencyInput?.value || fallbackSourceCurrency).trim().toUpperCase();

            if (lineSourceCurrencyInput) {
                lineSourceCurrencyInput.value = lineSourceCurrency;
            }

            const isCustomRow = row.hasAttribute("data-purchase-custom-item");

            if (productId === "" && !isCustomRow) {
                setText("[data-line-stock]", 0, row);
                setText("[data-line-subtotal]", 0, row);
                return carry;
            }

            const linePrice = convertCurrencyAmount(priceReference, lineSourceCurrency, documentCurrency, rate);
            const lineSubtotal = quantity * linePrice;

            setText("[data-line-stock]", stock, row);
            setText("[data-line-subtotal]", lineSubtotal, row);

            return {
                lineCount: carry.lineCount + 1,
                quantityTotal: carry.quantityTotal + quantity,
                subtotal: carry.subtotal + lineSubtotal
            };
        }, {
            lineCount: 0,
            quantityTotal: 0,
            subtotal: 0
        });
    };

    document.querySelectorAll("form[data-rate-sync='1']").forEach((form) => {
        const dateInput = form.querySelector("input[type='date']");
        const rateInput = form.querySelector("[data-rate-input]");
        const rateUrl = form.dataset.rateUrl || "/rates/by-date";

        if (!dateInput || !rateInput) {
            return;
        }

        let controller = null;

        const syncRate = async () => {
            const dateValue = String(dateInput.value || "").trim();
            if (dateValue === "") {
                return;
            }

            if (controller) {
                controller.abort();
            }

            controller = new AbortController();

            try {
                const response = await fetch(`${rateUrl}?date=${encodeURIComponent(dateValue)}`, {
                    headers: { Accept: "application/json" },
                    signal: controller.signal
                });

                if (!response.ok) {
                    throw new Error("rate_sync_failed");
                }

                const payload = await response.json();
                rateInput.value = formatNumber(payload.rate ?? 0).replace(/\./g, "").replace(",", ".");
                rateInput.dispatchEvent(new Event("input", { bubbles: true }));
                rateInput.dispatchEvent(new Event("change", { bubbles: true }));
            } catch (error) {
                if (error.name !== "AbortError") {
                    rateInput.dispatchEvent(new Event("input", { bubbles: true }));
                }
            }
        };

        dateInput.addEventListener("change", syncRate);
        syncRate();
    });

    document.querySelectorAll("form[data-settings-rate-sync='1']").forEach((form) => {
        const modeInput = form.querySelector("[data-settings-mode]");
        const customCurrencyInput = form.querySelector("[data-settings-custom-currency]");
        const customCurrencyWrap = form.querySelector("[data-settings-custom-currency-wrap]");
        const rateInput = form.querySelector("[data-settings-rate-input]");
        const rateLabel = form.querySelector("[data-settings-rate-label]");
        const status = form.querySelector("[data-settings-rate-status]");
        const rateUrl = form.dataset.rateUrl || "/rates/by-date";

        if (!modeInput || !customCurrencyInput || !rateInput || !rateLabel || !status || !customCurrencyWrap) {
            return;
        }

        let controller = null;

        const setStatus = (message) => {
            status.textContent = message;
        };

        const updateFieldState = () => {
            const mode = String(modeInput.value || "").trim();
            const isCustom = mode === "custom";

            customCurrencyInput.disabled = !isCustom;
            customCurrencyWrap.hidden = !isCustom;
            rateInput.readOnly = !isCustom;

            if (mode === "bcv_usd") {
                customCurrencyInput.value = "USD";
                rateLabel.textContent = "Tasa";
            } else if (mode === "bcv_eur") {
                customCurrencyInput.value = "EUR";
                rateLabel.textContent = "Tasa";
            } else {
                rateLabel.textContent = "Tasa";
            }
        };

        const syncPreview = async () => {
            const mode = String(modeInput.value || "").trim();
            updateFieldState();

            if (mode === "custom") {
                setStatus("Modo personalizado activo. Define manualmente la tasa que deseas guardar.");
                return;
            }

            if (controller) {
                controller.abort();
            }

            controller = new AbortController();
            setStatus("Consultando tasa actual del BCV...");

            try {
                const params = new URLSearchParams({
                    date: todayLocalYmd(),
                    mode,
                    custom_currency: customCurrencyInput.value || "USD",
                    force_refresh: "1"
                });

                const response = await fetch(`${rateUrl}?${params.toString()}`, {
                    headers: { Accept: "application/json" },
                    signal: controller.signal
                });

                if (!response.ok) {
                    throw new Error("settings_rate_sync_failed");
                }

                const payload = await response.json();
                rateInput.value = parseNumber(payload.rate ?? 0).toFixed(4);
                setStatus(`Tasa ${mode === "bcv_eur" ? "BCV euro" : "BCV dolar"} cargada desde ${payload.source || "BCV"} (${payload.rate_date || ""}).`);
            } catch (error) {
                if (error.name === "AbortError") {
                    return;
                }

                setStatus("No se pudo consultar la tasa en este momento. Puedes intentar de nuevo o guardar mas tarde.");
            }
        };

        modeInput.addEventListener("change", syncPreview);
        customCurrencyInput.addEventListener("change", syncPreview);
        syncPreview();
    });

    document.querySelectorAll("[data-calc]").forEach((form) => {
        const paymentMethodInput = form.querySelector("[data-payment-method-select]");
        const paymentCurrencyInput = form.querySelector("[data-payment-currency-select], [data-expense-currency-select]");
        const documentCurrencyInput = form.querySelector("[data-document-currency-select], [name='currency_code']");
        const paymentAmountInput = form.querySelector("[data-payment-amount-input]");
        const quickCashShell = form.querySelector("[data-payment-quick-actions]");

        const setPaymentAmount = (value) => {
            if (!paymentAmountInput) {
                return;
            }

            const normalized = Number.isFinite(value) ? Math.max(0, roundMoney(value)) : 0;
            paymentAmountInput.value = normalized.toFixed(2);
            paymentAmountInput.dispatchEvent(new Event("input", { bubbles: true }));
            paymentAmountInput.dispatchEvent(new Event("change", { bubbles: true }));
        };

        const syncQuickCashState = (documentTotal) => {
            if (!quickCashShell) {
                return;
            }

            quickCashShell.dataset.documentTotal = String(roundMoney(documentTotal));
            quickCashShell.dataset.documentCurrency = documentCurrencyInput?.value || "";
            quickCashShell.dataset.paymentCurrency = paymentCurrencyInput?.value || "";
            quickCashShell.dataset.rate = String(parseNumber(form.querySelector("[data-rate-input]")?.value || "1"));
        };

        const syncPaymentMethod = () => {
            if (!paymentMethodInput || !paymentCurrencyInput) {
                return;
            }

            if (form.dataset.calc === "expense") {
                return;
            }

            const method = String(paymentMethodInput.value || "").trim();
            if (method === "usdt" || method === "zelle") {
                paymentCurrencyInput.value = form.dataset.referenceCurrency || "USD";
                return;
            }

            if (["point_of_sale", "bank_transfer", "mobile_payment"].includes(method)) {
                paymentCurrencyInput.value = form.dataset.secondaryCurrency || "VES";
                return;
            }

            if (paymentCurrencyInput.dataset.userSelected === "1") {
                return;
            }

            if (documentCurrencyInput) {
                paymentCurrencyInput.value = documentCurrencyInput.value || paymentCurrencyInput.value;
            }
        };

        const refresh = () => {
            const current = syncProductSelection(form) ?? { stock: 0, sourceCurrency: "" };
            const lineItemsState = form.querySelector("[data-line-items]")
                ? collectLineItemsState(form)
                : null;
            const quantity = parseNumber(form.querySelector("[data-qty-input]")?.value);
            const price = parseNumber(form.querySelector("[data-price-input]")?.value);
            const cost = parseNumber(form.querySelector("[data-cost-input]")?.value);
            const rate = parseNumber(form.querySelector("[data-rate-input]")?.value || "1");
            const initialStock = parseNumber(form.querySelector("[data-stock-input]")?.value);
            const currency = form.querySelector("[name='currency_code']")?.value ?? "";
            const sourceCurrency = form.dataset.referenceCurrency || current.sourceCurrency || currency;
            const secondaryCurrency = form.dataset.secondaryCurrency || "VES";
            const taxRate = parseNumber(form.dataset.taxRate || "16");
            const paymentAmount = parseNumber(form.querySelector("[data-payment-amount-input]")?.value);
            const paymentCurrency = paymentCurrencyInput?.value || currency;

            if (form.dataset.calc === "product") {
                setText("[data-product-margin]", price - cost, form);
                setText("[data-product-investment]", cost * initialStock, form);
            }

            if (form.dataset.calc === "purchase") {
                const referenceTotal = quantity * cost;
                const original = lineItemsState
                    ? lineItemsState.subtotal
                    : convertCurrencyAmount(referenceTotal, sourceCurrency, currency, rate);
                const baseCurrencyPurchase = form.dataset.referenceCurrency || "USD";
                const oppositeCurrencyPurchase = isBolivarCurrency(currency) ? baseCurrencyPurchase : secondaryCurrency;
                const converted = convertCurrencyAmount(original, currency, oppositeCurrencyPurchase, rate);
                const paymentApplied = convertCurrencyAmount(paymentAmount, paymentCurrency, currency, rate);
                const remaining = Math.max(0, original - paymentApplied);
                setText("[data-purchase-original]", original, form);
                setText("[data-purchase-converted]", converted, form);
                setNodeText("[data-purchase-total-currency]", normalizeCurrency(currency || secondaryCurrency), form);
                setNodeText("[data-purchase-equivalent-label]", `Equiv. ${normalizeCurrency(oppositeCurrencyPurchase)}`, form);
                setText("[data-line-count]", lineItemsState?.lineCount ?? 0, form);
                setText("[data-line-quantity-total]", lineItemsState?.quantityTotal ?? 0, form);
                setText("[data-payment-applied]", paymentApplied, form);
                setText("[data-payment-remaining]", remaining, form);
                syncQuickCashState(original);
            }

            if (form.dataset.calc === "expense") {
                const referenceAmount = parseNumber(form.querySelector("[data-expense-input]")?.value);
                const original = referenceAmount;
                const baseCurrency = form.dataset.referenceCurrency || "USD";
                const secondaryCurrency = form.dataset.secondaryCurrency || "VES";
                const oppositeCurrency = isBolivarCurrency(currency) ? baseCurrency : secondaryCurrency;
                const converted = convertCurrencyAmount(original, currency, oppositeCurrency, rate);
                const convertedLabel = isBolivarCurrency(currency)
                    ? `Referencia en ${normalizeCurrency(baseCurrency)}`
                    : `Consolidado en ${normalizeCurrency(secondaryCurrency)}`;
                setNodeText("[data-expense-original-label]", `Monto registrado en ${normalizeCurrency(currency || secondaryCurrency)}`, form);
                setNodeText("[data-expense-converted-label]", convertedLabel, form);
                setText("[data-expense-original]", original, form);
                setText("[data-expense-converted]", converted, form);
            }

            if (form.dataset.calc === "invoice") {
                const subtotalReference = quantity * price;
                const subtotal = lineItemsState
                    ? lineItemsState.subtotal
                    : convertCurrencyAmount(subtotalReference, sourceCurrency, currency, rate);
                const tax = subtotal * (taxRate / 100);
                const total = subtotal + tax;
                const baseCurrencyInvoice = form.dataset.referenceCurrency || "USD";
                const oppositeCurrencyInvoice = isBolivarCurrency(currency) ? baseCurrencyInvoice : secondaryCurrency;
                const totalEquivalent = convertCurrencyAmount(total, currency, oppositeCurrencyInvoice, rate);
                const paymentApplied = convertCurrencyAmount(paymentAmount, paymentCurrency, currency, rate);
                const remaining = Math.max(0, total - paymentApplied);
                setText("[data-invoice-subtotal]", subtotal, form);
                setText("[data-invoice-tax]", tax, form);
                setText("[data-invoice-total]", total, form);
                setText("[data-payment-applied]", paymentApplied, form);
                setText("[data-payment-remaining]", remaining, form);
                setText("[data-invoice-total-bolivars]", totalEquivalent, form);
                setNodeText("[data-invoice-equivalent-label]", `Equiv. ${normalizeCurrency(oppositeCurrencyInvoice)}`, form);
                setNodeText("[data-invoice-total-currency]", normalizeCurrency(currency || secondaryCurrency), form);
                setText("[data-line-count]", lineItemsState?.lineCount ?? 0, form);
                setText("[data-line-quantity-total]", lineItemsState?.quantityTotal ?? 0, form);
                syncQuickCashState(total);
            }

            if (form.dataset.calc === "delivery") {
                setText("[data-delivery-out]", quantity, form);
                setText("[data-delivery-left]", current.stock - quantity, form);
            }

            if (form.dataset.calc === "delivery-note") {
                const referenceTotal = quantity * price;
                const original = lineItemsState
                    ? lineItemsState.subtotal
                    : convertCurrencyAmount(referenceTotal, sourceCurrency, currency, rate);
                const baseCurrencyDelivery = form.dataset.referenceCurrency || "USD";
                const oppositeCurrencyDelivery = isBolivarCurrency(currency) ? baseCurrencyDelivery : secondaryCurrency;
                const converted = convertCurrencyAmount(original, currency, oppositeCurrencyDelivery, rate);
                const paymentApplied = convertCurrencyAmount(paymentAmount, paymentCurrency, currency, rate);
                const remaining = Math.max(0, original - paymentApplied);
                setText("[data-delivery-original]", original, form);
                setText("[data-payment-applied]", paymentApplied, form);
                setText("[data-payment-remaining]", remaining, form);
                setText("[data-delivery-converted]", converted, form);
                setNodeText("[data-delivery-equivalent-label]", `Equiv. ${normalizeCurrency(oppositeCurrencyDelivery)}`, form);
                setNodeText("[data-delivery-total-currency]", normalizeCurrency(currency || secondaryCurrency), form);
                setText("[data-line-count]", lineItemsState?.lineCount ?? 0, form);
                setText("[data-line-quantity-total]", lineItemsState?.quantityTotal ?? 0, form);
                syncQuickCashState(original);
            }
        };

        if (paymentMethodInput) {
            paymentMethodInput.addEventListener("change", () => {
                syncPaymentMethod();
                refresh();
            });
            syncPaymentMethod();
        }

        if (paymentCurrencyInput) {
            paymentCurrencyInput.addEventListener("change", () => {
                if (paymentCurrencyInput.matches("[data-payment-currency-select]")) {
                    paymentCurrencyInput.dataset.userSelected = "1";
                }
                refresh();
            });
        }

        if (documentCurrencyInput) {
            documentCurrencyInput.addEventListener("change", () => {
                syncPaymentMethod();
                refresh();
            });
        }

        if (quickCashShell) {
            quickCashShell.querySelectorAll("[data-payment-method-quick]").forEach((button) => {
                button.addEventListener("click", () => {
                    if (!paymentMethodInput) {
                        return;
                    }

                    if (paymentCurrencyInput) {
                        delete paymentCurrencyInput.dataset.userSelected;
                    }

                    paymentMethodInput.value = button.dataset.paymentMethodQuick || paymentMethodInput.value;
                    paymentMethodInput.dispatchEvent(new Event("change", { bubbles: true }));
                });
            });

            quickCashShell.querySelectorAll("[data-payment-quick]").forEach((button) => {
                button.addEventListener("click", () => {
                    const action = String(button.dataset.paymentQuick || "").trim();
                    if (action === "clear") {
                        setPaymentAmount(0);
                        return;
                    }

                    const documentTotal = parseNumber(quickCashShell.dataset.documentTotal || 0);
                    const documentCurrency = String(quickCashShell.dataset.documentCurrency || documentCurrencyInput?.value || "").trim();
                    const targetCurrency = String(paymentCurrencyInput?.value || documentCurrency).trim();
                    const rate = parseNumber(quickCashShell.dataset.rate || form.querySelector("[data-rate-input]")?.value || "1");
                    const baseAmount = action === "half" ? documentTotal / 2 : documentTotal;
                    const convertedAmount = convertCurrencyAmount(baseAmount, documentCurrency, targetCurrency, rate);

                    setPaymentAmount(convertedAmount);
                });
            });
        }

        form.addEventListener("input", refresh);
        form.addEventListener("change", refresh);
        refresh();
    });

    syncMenuState();
    syncSidebarState();

    if (!window.__documentPrompt) {
        try {
            const stored = window.sessionStorage.getItem(DOCUMENT_PROMPT_KEY);
            if (stored) {
                window.sessionStorage.removeItem(DOCUMENT_PROMPT_KEY);
                const parsed = JSON.parse(stored);
                if (parsed && parsed.url) {
                    window.__documentPrompt = parsed;
                }
            }
        } catch (error) {
            // sessionStorage no disponible o JSON invalido: ignoramos
        }
    }

    if (window.Swal && window.__documentPrompt && window.__documentPrompt.url) {
        const prompt = window.__documentPrompt;
        window.__documentPrompt = null;

        window.setTimeout(() => {
            window.Swal.fire({
                title: String(prompt.title || "Documento registrado"),
                text: String(prompt.text || "Deseas abrir el reporte?"),
                icon: "success",
                showCancelButton: true,
                confirmButtonText: String(prompt.confirm || "Abrir"),
                cancelButtonText: String(prompt.cancel || "Seguir aqui"),
                confirmButtonColor: "#2f6f68",
                cancelButtonColor: "#94a3b8"
            }).then((result) => {
                if (result.isConfirmed) {
                    window.open(String(prompt.url), "_blank", "noopener,noreferrer");
                }
            });
        }, 120);
    }
})();

// =====================================================
// POS Workspace (Facturacion rapida) - atajos y dinamica
// =====================================================
(function () {
    "use strict";

    const initPosWorkspace = () => {
        const workspace = document.querySelector("[data-pos-workspace]");
        if (!workspace) {
            return;
        }

        const form = workspace.querySelector(".pos-form");
        const itemsShell = workspace.querySelector(".pos-items");
        const itemsList = workspace.querySelector("[data-line-items-list]");
        const itemSearch = workspace.querySelector("[data-line-catalog-search]");
        const clientSearch = workspace.querySelector("[data-pos-client-input]")
            || workspace.querySelector("[data-client-search]");

        const isEditable = (el) => {
            if (!el) return false;
            const tag = (el.tagName || "").toUpperCase();
            if (tag === "INPUT" || tag === "TEXTAREA" || tag === "SELECT") return true;
            return el.isContentEditable === true;
        };

        // Foco automatico al cargar: arranca en el buscador de items para no abrir el panel del cliente.
        window.setTimeout(() => {
            if (itemSearch && !document.activeElement?.matches("input, textarea, select")) {
                itemSearch.focus();
            }
        }, 200);

        // Toggle del empty-state segun haya items o no
        if (itemsShell && itemsList) {
            const syncEmpty = () => {
                const has = itemsList.querySelectorAll("[data-line-item]").length > 0;
                itemsShell.dataset.hasItems = has ? "1" : "0";
            };
            syncEmpty();
            const observer = new MutationObserver(syncEmpty);
            observer.observe(itemsList, { childList: true });
        }

        // Atajos de teclado globales
        document.addEventListener("keydown", (event) => {
            // F2 -> guardar
            if (event.key === "F2") {
                event.preventDefault();
                if (form && typeof form.requestSubmit === "function") {
                    form.requestSubmit();
                } else if (form) {
                    form.submit();
                }
                return;
            }

            // Ctrl+K -> foco cliente
            if ((event.ctrlKey || event.metaKey) && (event.key === "k" || event.key === "K")) {
                event.preventDefault();
                if (clientSearch) {
                    clientSearch.focus();
                    clientSearch.select?.();
                }
                return;
            }

            // "/" -> foco buscador item (solo si no estamos editando)
            if (event.key === "/" && !isEditable(event.target)) {
                event.preventDefault();
                if (itemSearch) {
                    itemSearch.focus();
                    itemSearch.select?.();
                }
            }
        });
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initPosWorkspace);
    } else {
        initPosWorkspace();
    }
})();

// =====================================================
// PWA: registro del service worker
// =====================================================
(function () {
    "use strict";

    if (!("serviceWorker" in navigator)) {
        return;
    }

    const isSecure = window.isSecureContext
        || ["localhost", "127.0.0.1"].includes(window.location.hostname);
    if (!isSecure) {
        return;
    }

    const manifestLink = document.querySelector("link[rel='manifest']");
    if (!manifestLink) {
        return;
    }

    let basePath;
    try {
        const manifestUrl = new URL(manifestLink.href, window.location.href);
        basePath = manifestUrl.pathname.replace(/manifest\.webmanifest$/, "");
    } catch (error) {
        basePath = "/";
    }

    const swUrl = basePath + "sw.js";
    const scope = basePath;

    window.addEventListener("load", () => {
        navigator.serviceWorker.register(swUrl, { scope }).catch((error) => {
            console.warn("PWA: no se pudo registrar el service worker", error);
        });
    });
})();

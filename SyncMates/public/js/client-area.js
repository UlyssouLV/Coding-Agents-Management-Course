/**
 * Script frontend de l'espace client.
 *
 * Ce fichier gère:
 * - le chargement des Syncers possédés par l'Account connecté,
 * - le déclenchement d'Extension/Reactivation par Syncer,
 * - le formulaire de Claim d'un Syncer existant,
 * - la redirection vers la page de connexion Account si la session
 *   est absente ou invalide.
 */

const ownedSyncersListElement = document.getElementById("owned-syncers-list");
const ownedSyncersFeedbackElement = document.getElementById("owned-syncers-feedback");
const claimSyncerForm = document.getElementById("claim-syncer-form");
const claimSyncerFeedbackElement = document.getElementById("claim-syncer-feedback");

/**
 * Crée une erreur enrichie avec code HTTP.
 *
 * @param {string} message Message d'erreur.
 * @param {number} status Code HTTP.
 * @returns {Error & {status?: number}} Erreur enrichie.
 */
function buildHttpError(message, status) {
  const error = new Error(message);
  error.status = Number(status || 0);
  return error;
}

/**
 * Affiche un message de retour utilisateur dans la page.
 *
 * @param {HTMLElement|null} targetElement Element de destination du feedback.
 * @param {string} message Texte à afficher.
 * @param {boolean} isError Indique si le message est une erreur.
 */
function setFeedback(targetElement, message, isError) {
  if (!targetElement) {
    return;
  }

  targetElement.textContent = message;
  targetElement.style.color = isError ? "crimson" : "green";
}

/**
 * Redirige vers la page de connexion Account si la session est absente ou
 * invalide (spec ticket 009: "if the response is 401, redirect to the login
 * page").
 *
 * @param {unknown} error Erreur levée par une requête API.
 * @returns {boolean} true si redirection déclenchée.
 */
function redirectToLoginIfUnauthorized(error) {
  const status = Number(error?.status || 0);
  if (status !== 401) {
    return false;
  }

  window.location.href = new URL("account-login.html", window.location.href).toString();
  return true;
}

/**
 * Appelle une route API JSON en GET et centralise la gestion des erreurs HTTP.
 *
 * @param {string} endpoint Route API cible.
 * @param {string} actionLabel Libellé de l'action pour les messages d'erreur.
 * @returns {Promise<Object>} Réponse JSON du backend.
 * @throws {Error} Si la réponse HTTP n'est pas en succès.
 */
async function getJson(endpoint, actionLabel) {
  const response = await fetch(endpoint, {
    method: "GET",
    headers: {
      "Content-Type": "application/json",
    },
  });

  const rawResponse = await response.text();
  let data = {};
  try {
    data = rawResponse ? JSON.parse(rawResponse) : {};
  } catch (_parseError) {
    data = {};
  }

  if (!response.ok) {
    const backendMessage = data.error || "";
    const fallbackMessage = rawResponse ? rawResponse.slice(0, 180) : "";
    const details = backendMessage || fallbackMessage || "Aucun détail serveur.";
    throw buildHttpError(
      `Erreur ${actionLabel} (${response.status} ${response.statusText}) - ${details}`,
      response.status
    );
  }

  return data;
}

/**
 * Appelle une route API JSON en POST et centralise la gestion des erreurs HTTP.
 *
 * @param {string} endpoint Route API cible.
 * @param {Object} payload Données JSON envoyées.
 * @param {string} actionLabel Libellé de l'action pour les messages d'erreur.
 * @returns {Promise<Object>} Réponse JSON du backend.
 * @throws {Error} Si la réponse HTTP n'est pas en succès.
 */
async function postJson(endpoint, payload, actionLabel) {
  const response = await fetch(endpoint, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(payload),
  });

  const rawResponse = await response.text();
  let data = {};
  try {
    data = rawResponse ? JSON.parse(rawResponse) : {};
  } catch (_parseError) {
    data = {};
  }

  if (!response.ok) {
    const backendMessage = data.error || "";
    const fallbackMessage = rawResponse ? rawResponse.slice(0, 180) : "";
    const details = backendMessage || fallbackMessage || "Aucun détail serveur.";
    throw buildHttpError(
      `Erreur ${actionLabel} (${response.status} ${response.statusText}) - ${details}`,
      response.status
    );
  }

  return data;
}

/**
 * Traduit le statut technique d'un Syncer en libellé lisible.
 *
 * @param {string} status Statut brut ('active'/'archived').
 * @returns {string} Libellé français.
 */
function describeSyncerStatus(status) {
  return status === "archived" ? "Archivé" : "Actif";
}

/**
 * Rend la liste des Syncers possédés dans l'interface.
 *
 * @param {Array<Object>} syncers Liste de Syncers possédés.
 */
function renderOwnedSyncers(syncers) {
  if (!ownedSyncersListElement) {
    return;
  }

  ownedSyncersListElement.innerHTML = "";

  if (!Array.isArray(syncers) || syncers.length === 0) {
    const emptyItem = document.createElement("li");
    emptyItem.textContent = "Aucun Syncer possédé pour le moment.";
    ownedSyncersListElement.appendChild(emptyItem);
    return;
  }

  for (const syncer of syncers) {
    const syncerId = String(syncer?.id || "");
    const syncerName = String(syncer?.name || "Syncer");
    const status = String(syncer?.status || "active");
    const expiresAt = String(syncer?.expiresAt || "-");

    const item = document.createElement("li");

    const summary = document.createElement("span");
    summary.textContent = `${syncerName} — statut: ${describeSyncerStatus(status)} — expire le: ${expiresAt} `;
    item.appendChild(summary);

    const manageLink = document.createElement("a");
    const manageUrl = new URL("syncer.html", window.location.href);
    manageUrl.searchParams.set("id", syncerId);
    manageLink.href = manageUrl.toString();
    manageLink.textContent = "Gérer";
    item.appendChild(document.createTextNode(" "));
    item.appendChild(manageLink);
    item.appendChild(document.createTextNode(" "));

    const actionButton = document.createElement("button");
    actionButton.type = "button";
    actionButton.dataset.syncerId = syncerId;
    if (status === "archived") {
      actionButton.textContent = "Réactiver";
      actionButton.className = "reactivate-syncer-button";
      actionButton.dataset.action = "reactivate";
    } else {
      actionButton.textContent = "Étendre";
      actionButton.className = "extend-syncer-button";
      actionButton.dataset.action = "extend";
    }
    item.appendChild(actionButton);

    ownedSyncersListElement.appendChild(item);
  }
}

/**
 * Charge les Syncers possédés par l'Account courant et les affiche.
 * Redirige vers la page de connexion si l'Account Session est absente ou
 * invalide.
 */
async function loadOwnedSyncers() {
  try {
    const result = await getJson("/api/accounts/me/syncers", "chargement des Syncers");
    const syncers = Array.isArray(result?.syncers) ? result.syncers : [];
    renderOwnedSyncers(syncers);
  } catch (error) {
    if (redirectToLoginIfUnauthorized(error)) {
      return;
    }

    const message = error instanceof Error ? error.message : "Erreur inconnue.";
    setFeedback(ownedSyncersFeedbackElement, message, true);
  }
}

/**
 * Initie l'Extension payante d'un Syncer possédé et suit l'URL Stripe
 * Checkout renvoyée par le backend.
 *
 * @param {string} syncerId Identifiant technique du Syncer.
 */
async function extendSyncer(syncerId) {
  const result = await postJson(
    `/api/syncers/${encodeURIComponent(syncerId)}/extend`,
    {},
    "extension Syncer"
  );
  const checkoutUrl = String(result?.checkoutUrl || "");
  if (checkoutUrl) {
    window.location.href = checkoutUrl;
  }
}

/**
 * Initie la Reactivation payante d'un Syncer archivé et suit l'URL Stripe
 * Checkout renvoyée par le backend.
 *
 * @param {string} syncerId Identifiant technique du Syncer.
 */
async function reactivateSyncer(syncerId) {
  const result = await postJson(
    `/api/syncers/${encodeURIComponent(syncerId)}/reactivate`,
    {},
    "réactivation Syncer"
  );
  const checkoutUrl = String(result?.checkoutUrl || "");
  if (checkoutUrl) {
    window.location.href = checkoutUrl;
  }
}

if (ownedSyncersListElement) {
  ownedSyncersListElement.addEventListener("click", async (event) => {
    const target = event.target;
    if (!(target instanceof HTMLButtonElement)) {
      return;
    }

    const syncerId = String(target.dataset.syncerId || "");
    const action = String(target.dataset.action || "");
    if (!syncerId || !action) {
      return;
    }

    setFeedback(ownedSyncersFeedbackElement, "Redirection vers le paiement en cours...", false);

    try {
      if (action === "reactivate") {
        await reactivateSyncer(syncerId);
      } else {
        await extendSyncer(syncerId);
      }
    } catch (error) {
      if (redirectToLoginIfUnauthorized(error)) {
        return;
      }

      const message = error instanceof Error ? error.message : "Erreur inconnue.";
      setFeedback(ownedSyncersFeedbackElement, message, true);
    }
  });
}

if (claimSyncerForm) {
  claimSyncerForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    const formData = new FormData(claimSyncerForm);
    const identifier = String(formData.get("claimSyncerIdentifier") || "").trim();
    const password = String(formData.get("claimSyncerPassword") || "");

    if (!identifier || !password) {
      setFeedback(
        claimSyncerFeedbackElement,
        "Le nom/identifiant et le mot de passe sont obligatoires.",
        true
      );
      return;
    }

    setFeedback(claimSyncerFeedbackElement, "Réclamation en cours...", false);

    try {
      // handleClaimSyncer attend l'id du Syncer dans le chemin et un couple
      // identifier/password dans le corps (src/routes/syncers.php): le
      // formulaire ne collecte qu'un identifiant unique (nom ou id), réutilisé
      // pour les deux, exactement comme loginSyncer accepte l'un ou l'autre.
      await postJson(
        `/api/syncers/${encodeURIComponent(identifier)}/claim`,
        { identifier, password },
        "claim Syncer"
      );

      setFeedback(claimSyncerFeedbackElement, "Syncer réclamé avec succès.", false);
      claimSyncerForm.reset();
      await loadOwnedSyncers();
    } catch (error) {
      if (redirectToLoginIfUnauthorized(error)) {
        return;
      }

      const message = error instanceof Error ? error.message : "Erreur inconnue.";
      setFeedback(claimSyncerFeedbackElement, message, true);
    }
  });
}

loadOwnedSyncers();

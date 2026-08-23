import { account, databases, functions, query, config } from "./appwrite.js";

const state = { cart: JSON.parse(localStorage.getItem("cart") || "{}"), user: null };

const $ = (id) => document.getElementById(id);

function showFlash(message, isError = false) {
  $("flash").innerHTML = `<div class="${isError ? "error" : "ok"}">${message}</div>`;
}

function active(route) {
  for (const node of document.querySelectorAll(".view")) node.classList.add("hidden");
  $(`view-${route}`).classList.remove("hidden");
}

function saveCart() {
  localStorage.setItem("cart", JSON.stringify(state.cart));
}

function renderCart() {
  const entries = Object.values(state.cart);
  if (!entries.length) {
    $("cart").innerHTML = "<p>Panier vide.</p>";
    return;
  }
  const total = entries.reduce((sum, x) => sum + x.prix * x.quantite, 0);
  $("cart").innerHTML = entries.map((i) => `
    <div class="card">
      <strong>${i.nom}</strong><br/>
      ${i.quantite} x ${i.prix} FC
      <button data-remove="${i.id}">Supprimer</button>
    </div>`).join("") + `<p><strong>Total: ${total} FC</strong></p>`;

  document.querySelectorAll("[data-remove]").forEach((btn) => {
    btn.addEventListener("click", () => {
      delete state.cart[btn.dataset.remove];
      saveCart();
      renderCart();
    });
  });
}

async function loadProducts() {
  const res = await databases.listDocuments(config.databaseId, config.collections.products, [
    query.equal("actif", true)
  ]);
  $("products").innerHTML = res.documents.map((p) => `
    <article class="card">
      <strong>${p.nom}</strong>
      <p>${p.prix} FC / ${p.unite}</p>
      <p>Stock: ${p.stock}</p>
      <button data-add="${p.$id}">Ajouter</button>
    </article>
  `).join("");

  document.querySelectorAll("[data-add]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const product = res.documents.find((x) => x.$id === btn.dataset.add);
      state.cart[product.$id] = state.cart[product.$id]
        ? { ...state.cart[product.$id], quantite: state.cart[product.$id].quantite + 1 }
        : { id: product.$id, nom: product.nom, prix: product.prix, unite: product.unite, quantite: 1 };
      saveCart();
      showFlash("Produit ajouté au panier.");
    });
  });
}

async function refreshSession() {
  try {
    state.user = await account.get();
    $("auth-block").classList.add("hidden");
    $("profile-block").classList.remove("hidden");
    $("profile-line").textContent = `Connecté: ${state.user.name} (${state.user.email})`;
  } catch {
    state.user = null;
    $("auth-block").classList.remove("hidden");
    $("profile-block").classList.add("hidden");
  }
}

async function loadMyOrders() {
  if (!state.user) return;
  const res = await databases.listDocuments(config.databaseId, config.collections.orders, [
    query.equal("userId", state.user.$id),
    query.orderDesc("date")
  ]);
  $("my-orders").innerHTML = res.documents.map((o) =>
    `<li>${o.date} - ${o.total} FC - ${o.statut} - code: ${o.code_retrait}</li>`
  ).join("") || "<li>Aucune commande</li>";
}

async function loadAdminOrders() {
  const res = await databases.listDocuments(config.databaseId, config.collections.orders, [
    query.orderDesc("date"),
    query.limit(20)
  ]);
  $("admin-orders").innerHTML = res.documents.map((o) =>
    `<li>${o.client_nom} | ${o.total} FC | ${o.statut} | ${o.code_retrait}</li>`
  ).join("");
}

async function onCheckout(form) {
  const payload = Object.fromEntries(new FormData(form).entries());
  payload.cart = Object.values(state.cart);
  if (!payload.cart.length) {
    showFlash("Votre panier est vide.", true);
    return;
  }
  const execution = await functions.createExecution(
    config.functions.checkoutCreateOrder,
    JSON.stringify(payload),
    false
  );
  const output = JSON.parse(execution.responseBody || "{}");
  if (execution.status === "completed" && output.ok) {
    state.cart = {};
    saveCart();
    renderCart();
    showFlash(`Commande validée. Code retrait: ${output.code_retrait}`);
    await loadMyOrders();
    return;
  }
  showFlash(output.message || "Erreur de commande.", true);
}

async function main() {
  document.querySelectorAll("[data-route]").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const route = btn.dataset.route;
      active(route);
      if (route === "panier") renderCart();
      if (route === "compte") await loadMyOrders();
      if (route === "admin") await loadAdminOrders();
    });
  });

  $("register-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    const { nom, email, password } = Object.fromEntries(new FormData(e.target).entries());
    await account.create("unique()", email, password, nom);
    await account.createEmailPasswordSession(email, password);
    showFlash("Compte créé.");
    await refreshSession();
  });

  $("login-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    const { email, password } = Object.fromEntries(new FormData(e.target).entries());
    await account.createEmailPasswordSession(email, password);
    showFlash("Connexion réussie.");
    await refreshSession();
    await loadMyOrders();
  });

  $("logout-btn").addEventListener("click", async () => {
    await account.deleteSession("current");
    showFlash("Déconnecté.");
    await refreshSession();
  });

  $("checkout-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    await onCheckout(e.target);
  });

  $("refresh-admin-orders").addEventListener("click", loadAdminOrders);

  await loadProducts();
  await refreshSession();
  renderCart();
}

main().catch((err) => showFlash(err.message || "Erreur inattendue.", true));

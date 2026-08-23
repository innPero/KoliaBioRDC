const sdk = require("node-appwrite");

const pickupPoints = [
  "🏬 Boutique Gombe (Boulevard du 30 Juin)",
  "🏬 Boutique Limete (7ème Rue)",
  "🏬 Boutique Bandalungwa (Avenue Kimbondo)",
  "🏬 Boutique Kintambo (Rond-point Magasin)"
];
const mobileOperators = ["M-Pesa", "Orange Money", "Airtel Money", "Africell Cash"];

function buildClient() {
  const client = new sdk.Client()
    .setEndpoint(process.env.APPWRITE_ENDPOINT)
    .setProject(process.env.APPWRITE_PROJECT_ID)
    .setKey(process.env.APPWRITE_API_KEY);
  return {
    databases: new sdk.Databases(client),
    users: new sdk.Users(client),
    id: sdk.ID
  };
}

function parseBody(req) {
  try {
    return JSON.parse(req.body || "{}");
  } catch {
    return {};
  }
}

function ensure(cond, message, status = 400) {
  if (!cond) {
    const err = new Error(message);
    err.status = status;
    throw err;
  }
}

module.exports = async ({ req, res, log, error }) => {
  try {
    const dbId = process.env.APPWRITE_DATABASE_ID;
    const productsCol = process.env.COL_PRODUCTS || "products";
    const ordersCol = process.env.COL_ORDERS || "orders";
    const orderItemsCol = process.env.COL_ORDER_ITEMS || "order_items";
    const { databases, id } = buildClient();

    const body = parseBody(req);
    const cart = Array.isArray(body.cart) ? body.cart : [];
    ensure(cart.length > 0, "Panier vide.");
    ensure(typeof body.client_nom === "string" && body.client_nom.trim().length >= 2, "Nom invalide.");
    ensure(typeof body.client_email === "string" && body.client_email.includes("@"), "Email invalide.");
    ensure(typeof body.client_tel === "string" && body.client_tel.trim().length >= 8, "Téléphone invalide.");
    ensure(pickupPoints.includes(body.point_retrait), "Point de retrait invalide.");
    ensure(["cod", "mobile_money"].includes(body.payment_method), "Mode de paiement invalide.");

    if (body.payment_method === "mobile_money") {
      ensure(mobileOperators.includes(body.mobile_operator), "Opérateur mobile invalide.");
      ensure(typeof body.mobile_number === "string" && body.mobile_number.trim().length >= 8, "Numéro mobile invalide.");
    }

    const productIds = cart.map((x) => x.id);
    const productDocs = [];
    for (const productId of productIds) {
      const product = await databases.getDocument(dbId, productsCol, productId);
      productDocs.push(product);
    }

    let total = 0;
    for (const line of cart) {
      const product = productDocs.find((p) => p.$id === line.id);
      ensure(product, `Produit introuvable: ${line.id}`);
      ensure(product.stock >= line.quantite, `Stock insuffisant: ${product.nom}`);
      total += Number(product.prix) * Number(line.quantite);
    }

    for (const line of cart) {
      const product = productDocs.find((p) => p.$id === line.id);
      await databases.updateDocument(dbId, productsCol, product.$id, {
        stock: Number(product.stock) - Number(line.quantite)
      });
    }

    const codeRetrait = `LZL-${Math.random().toString(16).slice(2, 8).toUpperCase()}`;
    const orderId = id.unique();
    const orderDoc = await databases.createDocument(dbId, ordersCol, orderId, {
      userId: req.headers["x-appwrite-user-id"] || "guest",
      date: new Date().toISOString(),
      client_nom: body.client_nom.trim(),
      client_email: body.client_email.trim().toLowerCase(),
      client_tel: body.client_tel.trim(),
      point_retrait: body.point_retrait,
      code_retrait: codeRetrait,
      note: (body.note || "").slice(0, 500),
      total,
      statut: "en_attente",
      paiement_methode: body.payment_method,
      paiement_statut: body.payment_method === "mobile_money" ? "Payé (Simulé)" : "À payer en boutique",
      paiement_operateur: body.payment_method === "mobile_money" ? body.mobile_operator : "",
      paiement_numero: body.payment_method === "mobile_money" ? body.mobile_number : ""
    });

    for (const line of cart) {
      const product = productDocs.find((p) => p.$id === line.id);
      await databases.createDocument(dbId, orderItemsCol, id.unique(), {
        order_id: orderDoc.$id,
        product_id: product.$id,
        nom: product.nom,
        prix: Number(product.prix),
        unite: product.unite,
        quantite: Number(line.quantite)
      });
    }

    return res.json({ ok: true, order_id: orderDoc.$id, code_retrait: codeRetrait });
  } catch (e) {
    error(e.message);
    log(e.stack || "");
    return res.json({ ok: false, message: e.message }, e.status || 400);
  }
};

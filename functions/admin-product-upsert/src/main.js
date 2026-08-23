const sdk = require("node-appwrite");

function parseBody(req) {
  try {
    return JSON.parse(req.body || "{}");
  } catch {
    return {};
  }
}

function ensure(cond, message) {
  if (!cond) throw new Error(message);
}

async function assertAdmin(client, userId) {
  const teams = new sdk.Teams(client);
  const memberships = await teams.listMemberships(process.env.ADMIN_TEAM_ID || "admins");
  const isAdmin = memberships.memberships.some((m) => m.userId === userId);
  ensure(isAdmin, "Accès admin requis.");
}

function createClient() {
  return new sdk.Client()
    .setEndpoint(process.env.APPWRITE_ENDPOINT)
    .setProject(process.env.APPWRITE_PROJECT_ID)
    .setKey(process.env.APPWRITE_API_KEY);
}

module.exports = async ({ req, res, error }) => {
  try {
    const userId = req.headers["x-appwrite-user-id"];
    ensure(!!userId, "Utilisateur non authentifié.");
    const client = createClient();
    await assertAdmin(client, userId);

    const db = new sdk.Databases(client);
    const body = parseBody(req);
    const payload = {
      nom: String(body.nom || "").trim(),
      prix: Math.max(0, Number(body.prix || 0)),
      unite: String(body.unite || "").trim(),
      categorie: String(body.categorie || "").trim(),
      stock: Math.max(0, Number(body.stock || 0)),
      description: String(body.description || ""),
      image: String(body.image || ""),
      actif: body.actif !== false
    };

    ensure(payload.nom.length >= 2 && payload.nom.length <= 100, "Nom produit invalide.");
    ensure(payload.prix >= 0, "Prix invalide.");
    ensure(payload.unite.length > 0, "Unité requise.");
    ensure(payload.categorie.length > 0, "Catégorie requise.");

    const dbId = process.env.APPWRITE_DATABASE_ID;
    const col = process.env.COL_PRODUCTS || "products";
    let doc;
    if (body.product_id) {
      doc = await db.updateDocument(dbId, col, body.product_id, payload);
    } else {
      doc = await db.createDocument(dbId, col, sdk.ID.unique(), payload);
    }
    return res.json({ ok: true, product: doc });
  } catch (e) {
    error(e.message);
    return res.json({ ok: false, message: e.message }, 400);
  }
};

const sdk = require("node-appwrite");

function getClient() {
  return new sdk.Client()
    .setEndpoint(process.env.APPWRITE_ENDPOINT)
    .setProject(process.env.APPWRITE_PROJECT_ID)
    .setKey(process.env.APPWRITE_API_KEY);
}

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

module.exports = async ({ req, res, error }) => {
  try {
    const userId = req.headers["x-appwrite-user-id"];
    ensure(!!userId, "Utilisateur non authentifié.");

    const client = getClient();
    await assertAdmin(client, userId);

    const db = new sdk.Databases(client);
    const body = parseBody(req);
    const valid = ["en_attente", "prete", "retiree", "annulee"];
    ensure(valid.includes(body.statut), "Statut invalide.");
    ensure(typeof body.order_id === "string" && body.order_id !== "", "order_id invalide.");

    const updated = await db.updateDocument(
      process.env.APPWRITE_DATABASE_ID,
      process.env.COL_ORDERS || "orders",
      body.order_id,
      { statut: body.statut }
    );
    return res.json({ ok: true, order: updated });
  } catch (e) {
    error(e.message);
    return res.json({ ok: false, message: e.message }, 403);
  }
};

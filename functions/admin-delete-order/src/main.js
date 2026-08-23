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
    ensure(body.order_id, "order_id requis.");

    const dbId = process.env.APPWRITE_DATABASE_ID;
    const ordersCol = process.env.COL_ORDERS || "orders";
    const itemsCol = process.env.COL_ORDER_ITEMS || "order_items";
    const items = await db.listDocuments(dbId, itemsCol, [sdk.Query.equal("order_id", body.order_id)]);
    for (const item of items.documents) {
      await db.deleteDocument(dbId, itemsCol, item.$id);
    }
    await db.deleteDocument(dbId, ordersCol, body.order_id);

    return res.json({ ok: true });
  } catch (e) {
    error(e.message);
    return res.json({ ok: false, message: e.message }, 400);
  }
};

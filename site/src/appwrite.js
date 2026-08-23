const { Client, Account, Databases, Functions, Query, Teams } = window.Appwrite;

const cfg = window.APP_CONFIG;
const client = new Client().setEndpoint(cfg.endpoint).setProject(cfg.projectId);

export const account = new Account(client);
export const databases = new Databases(client);
export const functions = new Functions(client);
export const teams = new Teams(client);
export const query = Query;
export const config = cfg;

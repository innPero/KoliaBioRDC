window.APP_CONFIG = {
  endpoint: "https://<APPWRITE_ENDPOINT>/v1",
  projectId: "<APPWRITE_PROJECT_ID>",
  databaseId: "kolia_db",
  collections: {
    products: "products",
    orders: "orders",
    orderItems: "order_items",
    customerProfiles: "customer_profiles"
  },
  functions: {
    checkoutCreateOrder: "<FUNCTION_ID_CHECKOUT_CREATE_ORDER>",
    adminUpdateOrderStatus: "<FUNCTION_ID_ADMIN_UPDATE_ORDER_STATUS>",
    adminProductUpsert: "<FUNCTION_ID_ADMIN_PRODUCT_UPSERT>",
    adminDeleteOrder: "<FUNCTION_ID_ADMIN_DELETE_ORDER>"
  },
  adminTeamId: "admins"
};

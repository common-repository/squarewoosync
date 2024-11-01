import apiFetch from "@wordpress/api-fetch";

// Function to create batches
export const createBatches = (array, batchSize) => {
  return array.reduce(
    (acc, _, i) =>
      i % batchSize ? acc : [...acc, array.slice(i, i + batchSize)],
    []
  );
};

// Process single product
export async function sendProductToServer(
  product,
  inventory,
  controller,
  dataToImport
) {
  const inventoryMatch = inventory.find((inv) => inv.id === product.id);
  if (!inventoryMatch) {
    return { error: `Product ${product.id} not found in inventory` };
  }

  try {
    return await apiFetch({
      path: "/sws/v1/square-inventory/import",
      signal: controller?.signal,
      method: "POST",
      data: { product: [inventoryMatch], datatoimport: dataToImport },
    });
  } catch (error) {
    return { error: error };
  }
}
// Function to send log data to the backend
export const logToBackend = async (logData) => {
  try {
    return await apiFetch({
      path: "/sws/v1/logs",
      method: "POST",
      data: { log: logData },
    });
  } catch (error) {
    console.error("Error logging to backend:", error);
  }
};

export const updateInventoryTable = (results, inventory) => {
  return inventory.map((inv) => {
    const matchedItem = results.find((res) => res.square_id === inv.id);
    const importStatus =
      matchedItem && matchedItem.status === "success" ? true : false;

    return {
      ...inv,
      woocommerce_product_id:
        matchedItem?.product_id || inv.woocommerce_product_id,
      imported: matchedItem ? importStatus : inv.imported,
      status: matchedItem ? importStatus : inv.status,
      item_data: {
        ...inv.item_data,
        variations: inv.item_data.variations.map((variation) => ({
          ...variation,
          imported: matchedItem ? importStatus : inv.imported,
          status: matchedItem ? importStatus : inv.status,
        })),
      },
    };
  });
};
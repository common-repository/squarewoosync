import { useState, useCallback } from "@wordpress/element";
import { useDispatch } from "react-redux";

import {
  createBatches,
  sendProductToServer,
  updateInventoryTable,
  logToBackend,
} from "../../utils/inventoryUtils";
import { setInventory } from "../../redux/inventorySlice";

export const useImport = () => {
  const [isImporting, setIsImporting] = useState(false);
  const [progress, setProgress] = useState([]);
  const dispatch = useDispatch();

  const importProduct = useCallback(
    async (
      productsToImport,
      inventory,
      controller,
      dataToImport,
      rangeValue = 15
    ) => {
      if (isImporting) return; // Prevent concurrent imports

      setIsImporting(true);
      setProgress([]);
      const batches = createBatches(productsToImport, rangeValue);

      let results = [];
      for (const batch of batches) {
        const batchResults = await Promise.all(
          batch.map(async (product) => {
            try {
              const res = await sendProductToServer(
                product,
                inventory,
                controller,
                dataToImport
              );
              const result = res.error
                ? {
                    status: "failed",
                    product_id: "N/A",
                    square_id: product.id,
                    message: res.error,
                  }
                : { ...res[0], status: "success" };

              // Log success or error to backend
              logToBackend({
                type: result.status,
                message: result.message,
                context: {
                  product_id: result.product_id,
                  square_id: product.id,
                },
              });

              return result;
            } catch (error) {
              const errorMsg =
                error.name === "AbortError" ? "Request Aborted" : error.message;
              const errorResult = {
                status: "failed",
                product_id: "N/A",
                square_id: product.id,
                message: errorMsg,
              };

              // Log exception to backend
              logToBackend({
                type: "error",
                message: errorMsg,
                context: {
                  product_id: errorResult.product_id,
                  square_id: product.id,
                },
              });

              return errorResult;
            }
          })
        );

        results = [...results, ...batchResults];
        setProgress((prevProgress) => [
          ...prevProgress,
          ...batchResults.map((result) => result),
        ]);
      }

      const updatedInventory = updateInventoryTable(results, inventory);
      if (updatedInventory) {
        dispatch(setInventory(updatedInventory));
      }

      setIsImporting(false);
    },
    [isImporting, dispatch]
  );

  return {
    isImporting,
    progress,
    importProduct,
  };
};
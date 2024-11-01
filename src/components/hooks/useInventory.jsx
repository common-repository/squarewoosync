import { useState, useCallback } from 'react';
import { useDispatch } from 'react-redux';
import apiFetch from '@wordpress/api-fetch';
import { toast } from 'react-toastify';
import { setInventory } from '../../redux/inventorySlice';

const useInventory = () => {
  const dispatch = useDispatch();
  const [inventoryLoading, setLoading] = useState(false);

  // Fetch inventory data and handle API response
  const getInventory = useCallback(async () => {
    setLoading(true);
    dispatch(setInventory(null)); // Clear the existing inventory

    try {
      // Initial request to schedule the inventory update
      await apiFetch({
        path: "/sws/v1/square-inventory/",
      });

      // Polling function to get the inventory data from the database
      const pollForInventory = async () => {
        let retries = 0;
        const maxRetries = 100; // Max number of retries
        const delay = 5000; // Delay between retries (in milliseconds)

        while (retries < maxRetries) {
          try {
            const inventoryResponse = await apiFetch({
              path: "/sws/v1/square-inventory/saved-inventory/",
            });

            console.log(inventoryResponse)

            if (inventoryResponse && inventoryResponse.length > 0) {
              dispatch(setInventory(inventoryResponse));
              setLoading(false);
              return;
            }
          } catch (error) {
            console.error("Error fetching inventory data: ", error);
          }

          retries += 1;
          await new Promise((resolve) => setTimeout(resolve, delay));
        }
        setLoading(false);
      };

      // Start polling for inventory data
      pollForInventory();

    } catch (error) {
      console.log(error);
    }
  }, [dispatch]);

  return { getInventory, inventoryLoading };
};

export default useInventory;

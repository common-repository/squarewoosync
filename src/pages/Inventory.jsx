import { useState, useEffect } from "@wordpress/element";
import apiFetch from "@wordpress/api-fetch";
import { useDispatch, useSelector } from "react-redux";
import useMenuFix from "../components/hooks/useMenuFix";
import InvEmptyState from "../components/features/inventory/InvEmptyState";
import InvLoading from "../components/features/inventory/InvLoading";
import InventoryTable from "../components/features/inventory/table/InventoryTable";
import useInventory from '../components/hooks/useInventory';

export default function Inventory() {
  const { getInventory, inventoryLoading } = useInventory();
  const inventory = useSelector((state) => state.inventory.items);
  const [loading, setLoading] = useState(false);
  const [validToken, setValidToken] = useState(false);
  useMenuFix();

  useEffect(() => {
    const getToken = async () => {
      try {
        const response = await apiFetch({
          path: "/sws/v1/settings/access-token",
        });

        if (
          response.access_token &&
          response.access_token.length > 0 &&
          response.access_token !== "Token not set or empty"
        ) {
          setValidToken(response.access_token);
        }
      } catch (err) {
        console.error(err);
      }
    };
    getToken();
  }, []);

  useEffect(() => {
    if (validToken && inventory === null && !inventoryLoading) {
      getInventory();
    }
  }, [validToken, inventory, inventoryLoading, getInventory]);

  useEffect(() => {
    if (inventoryLoading) {
      setLoading(true);
    } else {
      setLoading(false);
    }
  }, [inventoryLoading]);

  return (
    <div>
      <div className="bg-white rounded-xl shadow-lg overflow-auto mt-10">
        {inventory === null && !loading && (
          <InvEmptyState getInventory={getInventory} validToken={validToken} />
        )}
        {loading && <InvLoading />}
        {Array.isArray(inventory) && !loading && (
          <InventoryTable getInventory={getInventory} />
        )}
      </div>
    </div>
  );
}

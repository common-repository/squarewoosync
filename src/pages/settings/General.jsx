import { useEffect, useState } from "@wordpress/element";
import { toast } from "react-toastify";
import apiFetch from "@wordpress/api-fetch";
import { Cog8ToothIcon } from "@heroicons/react/24/outline";
import AccessToken from "../../components/features/settings/general/AccessToken";
import Webhook from "../../components/features/settings/general/Webhook";
import useMenuFix from "../../components/hooks/useMenuFix";
import Locations from "../../components/features/settings/general/Locations";
import SettingsLayout from "../../components/layout/SettingsLayout";
import ExportProducts from "../../components/features/settings/general/Export";

export default function Settings() {
  useMenuFix();
  const [settingsLoading, setSettingsLoading] = useState(true);
  const [locations, setLocations] = useState([]);
  const [settings, setSettings] = useState({
    location: "",
    squareAuto: {
      isActive: false,
      stock: true,
      sku: true,
      title: true,
      description: true,
      images: true,
      price: true,
      category: true,
    },
    wooAuto: {
      isActive: false,
      stock: false,
      sku: true,
      title: false,
      description: false,
      images: false,
      category: false,
      price: false,
    },
    exportStatus: 0,
    exportSynced: 1,
    exportResults: null,
  });

  useEffect(() => {
    const getSettings = async () => {
      apiFetch({ path: "/sws/v1/settings", method: "GET" })
        .then((res) => {
          setSettings((currentSettings) => ({
            ...currentSettings,
            ...res,
          }));
          console.log(res);
          setSettingsLoading(false);
        })
        .catch((err) => {
          setSettingsLoading(false);
          toast({
            render: "Failed to update settings: " + err.message,
            type: "error",
            isLoading: false,
            autoClose: false,
            closeOnClick: true,
          });
        });
    };
    getSettings();
  }, []);

  useEffect(() => {
    const getLocations = async () => {
      apiFetch({
        path: "/sws/v1/settings/get-locations",
        method: "GET",
      })
        .then((res) => {
          setLocations(res.locations.data.locations);
          setSettingsLoading(false);
        })
        .catch((err) => {
          setSettingsLoading(false);
          toast({
            render: "Failed to get locations: " + err.message,
            type: "error",
            isLoading: false,
            autoClose: false,
            closeOnClick: true,
          });
        });
    };
    getLocations();
  }, []);

  const getLocations = async () => {
    apiFetch({ path: "/sws/v1/settings/get-locations", method: "GET" })
      .then((res) => {
        setLocations(res.locations.data.locations);
        setSettingsLoading(false);
      })
      .catch((err) => {
        setSettingsLoading(false);
        toast({
          render: "Failed to get locations: " + err.message,
          type: "error",
          isLoading: false,
          autoClose: false,
          closeOnClick: true,
        });
      });
  };

  const updateSettings = async (key, val) => {
    const id = toast.loading(`Updating setting: ${key}`);
    try {
      const result = await apiFetch({
        path: "/sws/v1/settings", // Updated path
        method: "POST",
        data: { [key]: val },
      });
      if (result) {
        toast.update(id, {
          render: "settings updated successfully",
          type: "success",
          isLoading: false,
          autoClose: 2000,
          hideProgressBar: false,
          closeOnClick: true,
        });
        setSettings({ ...settings, ...result });
      }
    } catch (err) {
      toast.update(id, {
        render: "Failed to update settings: " + err.message,
        type: "error",
        isLoading: false,
        autoClose: false,
        closeOnClick: true,
      });
    }
  };

  const exportProducts = async () => {
    setSettings((currentSettings) => ({
      ...currentSettings,
      exportResults: null,
      exportStatus: 1,
    }));
    const id = toast.loading(`Exporting products`);
    try {
      const result = await apiFetch({
        path: "/sws/v1/settings/export-to-square", // Updated path
        method: "GET",
      });
      if (result) {
        toast.update(id, {
          render: "export is currently running",
          type: "success",
          isLoading: false,
          autoClose: 2000,
          hideProgressBar: false,
          closeOnClick: true,
        });
      }
    } catch (err) {
      console.log(err);
      toast.update(id, {
        render: "Failed to run export: " + err.message,
        type: "error",
        isLoading: false,
        autoClose: false,
        closeOnClick: true,
      });
      setSettings((currentSettings) => ({
        ...currentSettings,
        exportStatus: 0,
      }));
    }
  };

  return (
    <SettingsLayout>
      {settingsLoading ? (
        <div>Loading...</div>
      ) : (
        <>
          <AccessToken
            updateSettings={updateSettings}
            setSettings={setSettings}
            settings={settings}
            setLocations={setLocations}
            getLocations={getLocations}
          />
          <Locations
            updateSettings={updateSettings}
            locations={locations}
            settings={settings}
          />
          <ExportProducts
            title="Are you sure?"
            description="This will create a new product on Square for every Woocommerce product."
            onConfirm={() => exportProducts()}
            status={settings.exportStatus}
            results={settings.exportResults}
            updateSettings={updateSettings}
            setSettings={setSettings}
            settings={settings}
          />
        </>
      )}
    </SettingsLayout>
  );
}

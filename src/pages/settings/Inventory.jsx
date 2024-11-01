import { toast } from "react-toastify";
import SquareWoo from "../../components/features/settings/general/SquareWoo";
import WooSquare from "../../components/features/settings/general/WooSquare";
import useMenuFix from "../../components/hooks/useMenuFix";
import SettingsLayout from "../../components/layout/SettingsLayout";
import apiFetch from "@wordpress/api-fetch";
import { useEffect, useState } from "@wordpress/element";
import { Switch } from "@headlessui/react";
import CronInventory from "../../components/features/settings/general/CronInventory";
import Webhook from "../../components/features/settings/general/Webhook";

export default function InventorySettings() {
  useMenuFix();

  const [settingsLoading, setSettingsLoading] = useState(true);
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
      autoCreateProduct: false,
      isActive: false,
      stock: false,
      sku: true,
      title: false,
      description: false,
      images: false,
      category: false,
      price: false,
    },
    cron: {
      enabled: false,
      source: "square",
      schedule: "hourly",
      batches: 30,
      dataToUpdate: {
        stock: false,
        sku: false,
        title: false,
        description: false,
        images: false,
        category: false,
        price: false,
      },
    },
  });

  useEffect(() => {
    const getSettings = async () => {
      apiFetch({ path: "/sws/v1/settings", method: "GET" })
        .then((res) => {
          setSettings((currentSettings) => ({
            ...currentSettings,
            ...res,
          }));
          setSettingsLoading(false);
          console.log(res);
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

  const updateSettings = async (key, val) => {
    const id = toast.loading(`Updating setting: ${key}`);
    try {
      const result = await apiFetch({
        path: "/sws/v1/settings", // Updated path
        method: "POST",
        data: { [key]: val },
      });
      console.log(result);
      if (result) {
        toast.update(id, {
          render: "settings updated successfully",
          type: "success",
          isLoading: false,
          autoClose: 2000,
          hideProgressBar: false,
          closeOnClick: true,
        });
        setSettings((currentSettings) => ({
          ...currentSettings,
          ...result,
        }));
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

  return (
    <SettingsLayout>
      <>
        {!settingsLoading && (
          <>
            <SquareWoo
              settings={settings}
              updateSettings={updateSettings}
              settingsLoading={settingsLoading}
            />
            <WooSquare
              settings={settings}
              updateSettings={updateSettings}
              settingsLoading={settingsLoading}
            />
            <Webhook />
            <div className="px-4 pb-5 sm:px-6">
              <h3 className="text-base font-semibold leading-6 text-gray-900">
                Automatic update scheduler <a className="pro-badge !relative" href="https://squaresyncforwoo.com" target="_blank">PRO ONLY</a>
              </h3>
              <div className="mt-2 max-w-xl text-sm text-gray-500 mb-4">
                <p className="mb-4">
                  The Automatic Update Scheduler allows you to set up a
                  recurring schedule for product updates, adding another level
                  of data accuracy, ensuring your information stays current
                  without manual intervention. Simply select the frequency of
                  updates—daily, weekly, or monthly—and the system will
                  automatically apply the latest updates according to your
                  chosen schedule.
                </p>
                <Switch
                  checked={false}
                  className={`${
                    settings.cron.enabled ? "bg-sky-500" : "bg-gray-200"
                  } relative inline-flex h-6 w-11 items-center rounded-full`}
                >
                  <span className="sr-only">Enable notifications</span>
                  <span
                    className={`${
                      settings.cron.enabled ? "translate-x-6" : "translate-x-1"
                    } inline-block h-4 w-4 transform rounded-full bg-white transition`}
                  />
                </Switch>
              </div>
            </div>
            <div className="px-4 pb-5 sm:px-6 mt-6">
              <h3 className="text-base font-semibold leading-6 text-gray-900">
                Automatically create Square products <a className="pro-badge !relative" href="https://squaresyncforwoo.com" target="_blank">PRO ONLY</a>
              </h3>
              <div className="mt-2 max-w-xl text-sm text-gray-500 mb-4">
                <p className="mb-4">
                  When this feature is enabled, every time you create a new
                  product in WooCommerce, it will automatically be exported and linked to
                  your Square account. This ensures that your product listings
                  are consistently updated across both platforms, saving you
                  time and maintaining synchronization between your WooCommerce
                  store and Square inventory.
                </p>
                <Switch
                  checked={false}
                  className={`${
                    settings.wooAuto.autoCreateProduct
                      ? "bg-sky-500"
                      : "bg-gray-200"
                  } relative inline-flex h-6 w-11 items-center rounded-full`}
                >
                  <span className="sr-only">Enable auto product creation</span>
                  <span
                    className={`${
                      settings.wooAuto.autoCreateProduct
                        ? "translate-x-6"
                        : "translate-x-1"
                    } inline-block h-4 w-4 transform rounded-full bg-white transition`}
                  />
                </Switch>
              </div>
            </div>
          </>
        )}
      </>
    </SettingsLayout>
  );
}

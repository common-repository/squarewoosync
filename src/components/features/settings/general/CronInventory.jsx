import { useState } from "@wordpress/element";
import { Switch } from "@headlessui/react";
import { classNames } from "../../../../utils/classHelper";
import CronScheduleInput from "./cron/CronScheduleInput";

export default function CronInventory({
  settings,
  updateSettings,
  setSettings,
}) {
  const [enabled, setEnabled] = useState(
    settings.cron.source === "square" ? true : false
  );

  return (
    <div>
      <p className="text-base font-semibold mb-2">Source of truth:</p>
      <p className="text-sm text-gray-500">
        The Source of Trust setting determines the primary source for your
        product information. Choose Square to automatically sync and update your
        product details based on data from Square. This option is ideal if
        Square is your primary platform for inventory and sales management.
        Alternatively, selecting Woocommerce means your product updates will be
        based on the information stored within your WooCommerce system, best for
        those who manage their inventory directly through WooCommerce.
      </p>
      <div className="flex gap-2 items-center my-4">
        <p className="font-semibold text-sm">Woocommerce</p>
        <Switch
          checked={enabled}
          onChange={(e) => {
            setEnabled(e);
            updateSettings("cron", {
              ...settings.cron,
              source: !e ? "woocommerce" : "square",
            });
          }}
          className={classNames(
            enabled ? "bg-slate-950" : "bg-purple-500",
            "relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2"
          )}
        >
          <span className="sr-only">Source of truth</span>
          <span
            className={classNames(
              enabled ? "translate-x-5" : "translate-x-0",
              "pointer-events-none relative inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
            )}
          >
            <span
              className={classNames(
                enabled
                  ? "opacity-0 duration-100 ease-out"
                  : "opacity-100 duration-200 ease-in",
                "absolute inset-0 flex h-full w-full items-center justify-center transition-opacity"
              )}
              aria-hidden="true"
            >
              <span className="font-semibold text-purple-500 p-0 m-0 flex items-center justify-center text-xs leading-none pb-[2px]">
                w
              </span>
            </span>
            <span
              className={classNames(
                enabled
                  ? "opacity-100 duration-200 ease-in"
                  : "opacity-0 duration-100 ease-out",
                "absolute inset-0 flex h-full w-full items-center justify-center transition-opacity"
              )}
              aria-hidden="true"
            >
              <span className="font-semibold text-slate-950 p-0 m-0 flex items-center justify-center text-xs leading-none pb-[3px]">
                s
              </span>
            </span>
          </span>
        </Switch>
        <p className="font-semibold text-sm">Square</p>
      </div>
      <p className="text-base font-semibold mb-2">Build your own schedule:</p>
      <p className="text-sm text-gray-500">
        Setup your update schedule! Please be aware that updating, particularly
        with a large product inventory, may significantly impact server
        performance. To minimize potential strain, we recommend spacing your
        updates to the maximum extent feasible and verifying that your server
        infrastructure is robust enough to manage the load smoothly. This
        approach helps ensure a seamless operation and maintains optimal system
        performance.
      </p>
      <div>
        <CronScheduleInput
          settings={settings}
          updateSettings={updateSettings}
        />
      </div>
      <p className="text-base font-semibold mt-4">Batches:</p>
      <p className="text-sm text-gray-500">
        How many products to be updated per batch. A higher number will put
        greater load on the server.
      </p>
      <p className="mt-2">
        Products will be updated in batches of:{" "}
        <span className="text-sky-500 font-bold">{settings.cron.batches}</span>
      </p>
      <div className="flex items-center gap-1 mt-2">
        <p>10</p>
        <div className="relative w-[300px]">
          <input
            id="steps-range"
            type="range"
            min="10"
            max="100"
            onChange={(e) => {
              console.log(e);
              setSettings((currentSettings) => ({
                ...currentSettings,
                batches: e.target.value,
              }));
              updateSettings("cron", {
                ...settings.cron,
                batches: e.target.value,
              });
            }}
            value={settings.cron.batches}
            step="10"
            class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
          />
        </div>
        <p>100</p>
      </div>
    </div>
  );
}

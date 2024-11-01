import { useEffect, useState } from "@wordpress/element";
import Actions from "../components/Actions";
import Contact from "../components/features/dashboard/Contact";
import SyncLog from "../components/features/dashboard/logs/SyncLog";
import useMenuFix from "../components/hooks/useMenuFix";
import apiFetch from "@wordpress/api-fetch";
import NextCron from "../components/features/dashboard/NextCron";
import WooSquare from "../components/features/dashboard/WooSquare";
import SquareAuto from "../components/features/dashboard/SquareWoo";
import AutoMatcher from "../components/features/dashboard/AutoMatcher";
import AutoOrders from "../components/features/dashboard/AutoOrders";


export default function Dashboard() {
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
    orders: {
      enabled: false,
      stage: "processing"
    },
    exportStatus: 0,
    exportResults: null,
  });
  const [cron, setCron] = useState({});

  useMenuFix();

  useEffect(() => {
    const getSettings = async () => {
      apiFetch({ path: '/sws/v1/settings', method: 'GET' })
        .then((res) => {
          setSettings(currentSettings => ({
            ...currentSettings,
            ...res
          }));
        })
        .catch((err) => {
          toast({
            render: 'Failed to update settings: ' + err.message,
            type: 'error',
            isLoading: false,
            autoClose: false,
            closeOnClick: true,
          });
        });
    };
    getSettings();
  }, []);


  return (
    <div className="dashboard-grid gap-x-6 gap-y-6">
      <div className="col-span-full grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 items-stretch gap-6">
        <WooSquare wooAuto={settings.wooAuto} />
        <SquareAuto squareAuto={settings.squareAuto} />
        <NextCron cron={cron} />
        <AutoMatcher wooAuto={settings.wooAuto} />
        <AutoOrders orders={settings.orders} />
      </div>
      <div className="flex flex-col gap-6">
        <Actions />
        <Contact />
      </div>
      <div>
        {/* <Loyalty loyalty={settings && settings.loyalty ? settings.loyalty : null} /> */}
        <SyncLog />
      </div>
    </div>
  );
}

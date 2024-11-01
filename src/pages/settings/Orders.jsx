import { useEffect, useState } from '@wordpress/element';
import { toast } from 'react-toastify';
import apiFetch from '@wordpress/api-fetch';
import useMenuFix from '../../components/hooks/useMenuFix';
import SettingsLayout from '../../components/layout/SettingsLayout';
import { Switch } from '@headlessui/react';


export default function OrdersSettings() {
    useMenuFix();
    const [settingsLoading, setSettingsLoading] = useState(true);
    const [settings, setSettings] = useState({
        orders: {
            enabled: false,
            transactions: false,
            stage: 'processing'
        }
    });

    useEffect(() => {
        const getSettings = async () => {
            apiFetch({ path: '/sws/v1/settings', method: 'GET' })
                .then((res) => {
                    setSettings(currentSettings => ({
                        ...currentSettings,
                        ...res
                    }));
                    setSettingsLoading(false);
                    console.log(res)
                })
                .catch((err) => {
                    setSettingsLoading(false);
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
        <SettingsLayout>
            {settingsLoading ? (
                <div>Loading...</div>
            ) : (
                <>
                    <div className="px-4 pb-5 sm:px-6">
                        <h3 className="text-base font-semibold leading-6 text-gray-900">
                            Automatic Order Sync <a className="pro-badge !relative" href="https://squaresyncforwoo.com" target="_blank">PRO ONLY</a>
                        </h3> 
                        <div className="mt-2 max-w-xl text-sm text-gray-500 mb-4">
                            <p className='mb-4'>
                                Streamline your business operations by synchronizing your WooCommerce orders with Square automatically.
                            </p>
                            <div className='flex items-center gap-2'>
                                <Switch
                                    checked={false}
                                    
                                    className={`${settings.orders.enabled ? 'bg-sky-500' : 'bg-gray-200'
                                        } relative inline-flex h-6 w-11 items-center rounded-full`}
                                >
                                    <span
                                        className={`${settings.orders.enabled ? 'translate-x-6' : 'translate-x-1'
                                            } inline-block h-4 w-4 transform rounded-full bg-white transition`}
                                    />
                                </Switch>
                                <p className='font-semibold text-sm'>Enable or disable automatic order sync</p>
                            </div>
                            <div className='flex items-center gap-2 mt-4'>
                                <Switch
                                    checked={false}
                                   
                                    className={`${settings.orders.transactions ? 'bg-sky-500' : 'bg-gray-200'
                                        } relative inline-flex h-6 w-11 items-center rounded-full`}
                                >
                                    <span
                                        className={`${settings.orders.transactions ? 'translate-x-6' : 'translate-x-1'
                                            } inline-block h-4 w-4 transform rounded-full bg-white transition`}
                                    />
                                </Switch>
                                <p className='font-semibold text-sm'>Enable or disable automatic transaction/receipt sync</p>
                            </div>
                            <h3 className="text-base font-semibold leading-6 text-gray-900 mt-6">
                                Woo Status
                            </h3>
                            <p className='mb-4'>
                                Select the specific stage within the WooCommerce order cycle at which the order will be synchronized with Square.
                            </p>
                            <select className="block !rounded-lg !border-0 !py-1.5 text-gray-900 !ring-1 !ring-inset !ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-sky-600 sm:text-sm !px-4 !leading-6 mt-2 !pr-10"
                                value={settings.orders.stage}>
                                <option value={'processing'}>processing</option>
                                <option value={'completed'}>completed</option>
                            </select>

                        </div>
                    </div>
                </>
            )}
        </SettingsLayout>
    );
}

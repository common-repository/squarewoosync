import { useEffect, useState } from '@wordpress/element';
import { toast } from 'react-toastify';
import apiFetch from '@wordpress/api-fetch';
import useMenuFix from '../../components/hooks/useMenuFix';
import SettingsLayout from '../../components/layout/SettingsLayout';
import { Switch } from '@headlessui/react';

export default function LoyaltySettings() {
    useMenuFix();
    const [loadingLoyalty, setLoadingLoyalty] = useState(false);
    const [program, setProgram] = useState(null)
    const [errorMessage, setErrorMessage] = useState('')
    const [settings, setSettings] = useState({
        loyalty: {
            enabled: false,
            program_id: null
        }
    })

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


    const updateSettings = async (key, val) => {
        const id = toast.loading(`Updating setting: ${key}`);
        try {
            const result = await apiFetch({
                path: '/sws/v1/settings', // Updated path
                method: 'POST',
                data: { [key]: val },
            });
            console.log(result)
            if (result) {
                toast.update(id, {
                    render: `${key} updated successfully`,
                    type: 'success',
                    isLoading: false,
                    autoClose: 2000,
                    hideProgressBar: false,
                    closeOnClick: true,
                });
                setSettings(currentSettings => ({
                    ...currentSettings,
                    ...result
                }));
            }
        } catch (err) {
            toast.update(id, {
                render: `Failed to update ${key}: ${err.message}`,
                type: 'error',
                isLoading: false,
                autoClose: false,
                closeOnClick: true,
            });
        }
    };


    useEffect(() => {
        const getProgram = async () => {
            apiFetch({ path: '/sws/v1/loyalty', method: 'GET' })
                .then((res) => {
                    setLoadingLoyalty(false)
                    console.log(res)
                    if (res.success) {
                        if (res.data.program.status !== 'ACTIVE') {
                            updateSettings('loyalty', { ...settings.loyalty, program_id: '', enabled: false })
                            setErrorMessage('Loyal program is not active on Square')
                        } else {
                            updateSettings('loyalty', { ...settings.loyalty, program_id: res.data.program.id, accrual_rule: res.data.program.accrual_rules[0] })
                            setErrorMessage('')
                        }
                        setProgram(res.data.program)
                    } else {
                        updateSettings('loyalty', { ...settings.loyalty, program_id: '', enabled: false })
                        setErrorMessage('No Loyalty program found for linked Square account')
                    }
                })
                .catch((err) => {
                    setLoadingLoyalty(false)
                    console.error(err.message)
                });
        }
        if (settings.loyalty.enabled) {
            setLoadingLoyalty(true)
            getProgram()
        }
    }, [settings.loyalty.enabled])


    return (
        <SettingsLayout>
            <>
                <div className="px-4 pb-5 sm:px-6  text-black">
                    <h3 className="text-base font-semibold leading-6 text-gray-900">
                        Loyalty Program
                    </h3>
                    <div className="mt-2 max-w-xl text-sm text-gray-500 mb-4">
                        <p className='mb-4'>
                            Integrate Square's Loyalty program into your website, allowing customers to earn points on purchases through online orders.
                        </p>
                    </div>

                    <div className='flex items-center gap-2'>
                        <Switch
                            checked={settings.loyalty.enabled}
                            onChange={(val) => {
                                setSettings(currentSettings => ({
                                    ...currentSettings,
                                    loyalty: { ...currentSettings.loyalty, enabled: val }
                                }));
                                updateSettings('loyalty', { ...settings.loyalty, enabled: val })
                            }}
                            className={`${settings.loyalty.enabled ? 'bg-sky-500' : 'bg-gray-200'
                                } relative inline-flex h-6 w-11 items-center rounded-full`}
                        >
                            <span
                                className={`${settings.loyalty.enabled ? 'translate-x-6' : 'translate-x-1'
                                    } inline-block h-4 w-4 transform rounded-full bg-white transition`}
                            />
                        </Switch>
                        <p className='font-semibold text-sm'>Enable or disable accrual of points on customer orders</p>
                    </div>

                    {loadingLoyalty && <div className='flex gap-2 mt-4'>
                        <svg class="animate-spin h-5 w-5 text-sky-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p>Loading loyalty program</p>
                    </div>}

                    {errorMessage && <p className='text-sm text-red-500 mt-2'>{errorMessage}</p>}

                    {program &&
                        <div className='flex flex-col gap-2 mt-4'>
                            <p className='font-semibold text-base'>Square Loyalty Program:</p>
                            <p className='text-sm text-gray-500'>Square Status: <span className='text-green-500 font-semibold'>{program.status}</span></p>
                            <div>
                                <p className='text-sm text-gray-500'>{program.terminology.other} Accrual Rules:</p>
                                <ul className='ml-6 list-disc text-gray-500'>
                                    {program.accrual_rules.map((rule, idx) => {
                                        return (
                                            <li key={idx + rule.points}>Earn <span className='text-sky-500 font-semibold'>{rule.points} {program.terminology.one}</span> per <span className='text-sky-500 font-semibold'>{rule.spend_data.amount_money.amount / 100} {rule.spend_data.amount_money.currency}</span> spent</li>
                                        )
                                    })}
                                </ul>
                                {/* <p className='text-sm text-gray-500'>{program.terminology.other} Reward Tiers:</p>
                                <ul className='text-gray-500 list-disc ml-6'>
                                    {program.reward_tiers.map((reward) => {
                                        return (
                                            <li key={reward.id}>
                                                <p className='font-semibold'>{reward.name}</p>
                                                <p>{program.terminology.other} Needed: <span class="text-sky-500 font-semibold">{reward.points}</span></p>
                                            </li>
                                        )
                                    })}
                                </ul> */}
                            </div>

                        </div>
                    }
                </div>
            </>
        </SettingsLayout>
    );
}

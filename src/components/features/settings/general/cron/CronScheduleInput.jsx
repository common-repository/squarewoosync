import React, { useState } from 'react';
import { useEffect } from '@wordpress/element';


function CronScheduleInput({ settings, updateSettings }) {
    const [scheduleFrequency, setScheduleFrequency] = useState(settings.cron.schedule || 'hourly');


    useEffect(() => {
        if (settings.cron && settings.cron.schedule) {
            setScheduleFrequency(settings.cron.schedule);
        } else {
            console.log("Settings not loaded or missing cron.scheduleFrequency");
        }
    }, [settings]);


    const checkboxItems = [
        { id: 'stock', label: 'Stock', checked: settings.cron.dataToUpdate?.stock || false },
        { id: 'title', label: 'Title', checked: settings.cron.dataToUpdate?.title || false },
        { id: 'sku', label: 'SKU', checked: settings.cron.dataToUpdate?.sku || false },
        { id: 'price', label: 'Price', checked: settings.cron.dataToUpdate?.price || false },
        {
            id: 'description',
            label: 'Description',
            checked: settings.cron.dataToUpdate?.description || false,
        },
    ];

    const CheckboxItem = ({ id, label, checked, cron }) => {
        return (
            <li className="w-auto mb-0">
                <div className="flex items-center gap-1">
                    <input
                        id={id}
                        type="checkbox"
                        checked={checked}
                        onChange={() =>
                            updateSettings('cron', {
                                ...cron,
                                dataToUpdate: { ...cron.dataToUpdate, [id]: !checked, }
                            })
                        }
                        className="!m-0 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2 leading-normal"
                    />
                    <label
                        htmlFor={id}
                        className="w-full text-sm font-light text-gray-700 leading-normal"
                    >
                        {label}
                    </label>
                </div>
            </li>
        );
    };

    return (
        <div>
            <div className='flex flex-col gap-2 my-2'>
                <fieldset>
                    <legend className="font-semibold text-base mb-4">Select schedule frequency:</legend>
                    <div className="space-y-2">
                        {['hourly', 'twicedaily', 'daily', 'weekly'].map((frequency) => (
                            <div key={frequency} className="flex items-center">
                                <input
                                    id={frequency}
                                    type="radio"
                                    name="scheduleFrequency"
                                    value={frequency}
                                    checked={scheduleFrequency === frequency}
                                    onChange={(e) => {
                                        updateSettings('cron', { ...settings.cron, schedule: e.target.value });
                                        setScheduleFrequency(e.target.value)
                                    }}
                                    className="focus:ring-sky-500 h-4 w-4 text-sky-600 border-gray-300"
                                />
                                <label htmlFor={frequency} className="ml-1 block text-sm capitalize">
                                    {frequency} <span className='text-gray-500 text-sm'>{frequency === 'twicedaily' || frequency === 'daily' ? '(starting midnight)' : frequency === 'weekly' ? '(starting monday at midnight)' : ''}</span>
                                </label>
                            </div>
                        ))}
                        <div className="flex items-center">
                            <input
                                id='custom'
                                type="radio"
                                name="scheduleFrequency"
                                disabled
                                className="focus:ring-sky-500 h-4 w-4 text-sky-600 border-gray-300"
                            />
                            <label htmlFor='custom' className="ml-1 block text-sm capitalize">
                                Custom<span className='text-gray-500 text-sm'> (coming soon)</span>
                            </label>
                        </div>
                    </div>
                </fieldset>
            </div>
            <p className="font-semibold text-base mt-4">Data to update:</p>
            <ul className="text-sm font-medium text-gray-900 bg-white flex flex-wrap gap-2 mt-2">
                {checkboxItems.map((item) => (
                    <CheckboxItem
                        key={item.id}
                        id={item.id}
                        label={item.label}
                        checked={item.checked}
                        cron={settings.cron}
                    />
                ))}
            </ul>
        </div>
    );
}

export default CronScheduleInput;

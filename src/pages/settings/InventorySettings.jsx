
import InventoryCron from '../../components/features/settings/inventory/InventoryCron';
import SquareWoo from '../../components/features/settings/inventory/SquareWoo';
import WooSquare from '../../components/features/settings/inventory/WooSquare';



export default function InventorySettings({ settings, updateSettings, settingsLoading }) {
    return (
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
            <InventoryCron />
        </>
    )
}
/**
 * Internal dependencies
 */
import Dashboard from '../pages/Dashboard';
import Inventory from '../pages/Inventory';
import Orders from '../pages/Orders';
import Settings from '../pages/Settings';
import General from '../pages/settings/General';
import InventorySettings from '../pages/settings/Inventory';
import LoyaltySettings from '../pages/settings/Loyalty';
import OrdersSettings from '../pages/settings/Orders';

const routes = [
	{
		path: '/',
		element: Dashboard,
	},
	{
		path: '/inventory',
		element: Inventory,
	},
	{
		path: '/orders',
		element: Orders,
	},
	{
		path: '/settings',
		element: Settings,
	},
	{
		path: '/settings/general',
		element: General,
	},
	{
		path: '/settings/inventory',
		element: InventorySettings,
	},
	{
		path: '/settings/orders',
		element: OrdersSettings,
	},
	{
		path: '/settings/loyalty',
		element: LoyaltySettings,
	},
];

export default routes;

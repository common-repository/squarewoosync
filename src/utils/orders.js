import apiFetch from '@wordpress/api-fetch';
import { toast } from 'react-toastify';

// Utility function to recursively fetch orders
async function fetchOrders(page = 0, perPage = 20, allOrders = []) {
    try {
        const response = await apiFetch({
            path: `/sws/v1/orders?page=${page}&per_page=${perPage}`,
        });

        // Assuming the API directly returns an array of orders or an object with an 'orders' array
        const ordersFromResponse = response.orders || response;

        if (!ordersFromResponse || ordersFromResponse.length === 0) {
            // No more orders to fetch
            return allOrders;
        }

        // Concatenate current page orders to the list of all orders
        allOrders = allOrders.concat(ordersFromResponse);

        // Check if the fetched orders are less than perPage, indicating this is the last page
        if (ordersFromResponse.length < perPage) {
            return allOrders;
        }

        // Otherwise, fetch the next page
        return fetchOrders(page + 1, perPage, allOrders);
    } catch (error) {
        throw error;
    }
}

// Main function to get all Woo orders
export const getWooOrders = async (perPage = 99) => {
    const id = toast.loading('Retrieving Woo Orders');
    try {
        // Fetch orders recursively
        const orders = await fetchOrders(1, perPage);

        toast.update(id, {
            render: 'Orders Received',
            type: 'success',
            isLoading: false,
            autoClose: 2000,
            hideProgressBar: false,
            closeOnClick: true,
        });

        return { status: 'success', data: {orders: orders} };
    } catch (error) {
        toast.update(id, {
            render: `Error fetching orders: ${error.message || 'Server error'}`,
            type: 'error',
            isLoading: false,
            closeOnClick: true,
            autoClose: 5000,
        });
        console.error('Error fetching orders:', error);
        throw error;
    }
};
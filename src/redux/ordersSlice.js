import { createSlice, createAsyncThunk } from '@reduxjs/toolkit';
import { getWooOrders } from '../utils/orders';

// Optionally, create an async thunk for fetching orders
export const fetchOrders = createAsyncThunk(
	'orders/fetchIfNeeded',
	async (
		{ forceRefresh = false, page = 1, perPage = 10 } = {},
		{ getState, rejectWithValue }
	) => {
		const { orders } = getState();
		if ( forceRefresh || ! orders.data || orders.data.length < 1 ) {
			console.log( 'fetch orders' );
			try {
				const response = await getWooOrders();
				console.log( response );
				return response.data.orders;
			} catch ( error ) {
				return rejectWithValue( error.error );
			}
		} else {
			console.log( 'return orders' );
			return orders.data;
		}
	}
);

const ordersSlice = createSlice( {
	name: 'orders',
	initialState: {
		data: null,
		loading: false,
		error: null,
	},
	reducers: {
		setOrders: ( state, action ) => {
			state.data = action.payload;
		},
	},
	extraReducers: ( builder ) => {
		builder
			.addCase( fetchOrders.pending, ( state ) => {
				state.loading = true;
			} )
			.addCase( fetchOrders.fulfilled, ( state, action ) => {
				state.loading = false;
				state.data = action.payload;
				state.error = null;
			} )
			.addCase( fetchOrders.rejected, ( state, action ) => {
				state.loading = false;
				state.data = [];
				state.error = action.payload;
			} );
	},
} );

export const { setOrders } = ordersSlice.actions;

export default ordersSlice.reducer;
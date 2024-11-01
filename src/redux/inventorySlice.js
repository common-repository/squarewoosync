import { createSlice } from '@reduxjs/toolkit';

const initialState = {
	items: null,
};

export const inventorySlice = createSlice( {
	name: 'inventory',
	initialState,
	reducers: {
		setInventory: ( state, action ) => {
			state.items = action.payload;
		},
		addItem: ( state, action ) => {
			state.items.push( action.payload );
		},
		removeItem: ( state, action ) => {
			state.items = state.items.filter(
				( item ) => item.id !== action.payload
			);
		},
	},
} );

// Export actions
export const { setInventory, addItem, removeItem } = inventorySlice.actions;

// Export reducer
export default inventorySlice.reducer;

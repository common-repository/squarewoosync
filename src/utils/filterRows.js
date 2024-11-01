export const filterRows = ( row, id, value ) => {
	// Check for the existence of 'values' and then 'id' in the main row
	const mainRowMatch = row.getValue( id )
		? row
				.getValue( id )
				.toString()
				.toLowerCase()
				.includes( value.toLowerCase() )
		: false;

	// Check if any subrow matches the filter value
	const subRowMatch =
		row.subRows &&
		row.subRows.some( ( subRow ) => {
			const subRowValue = subRow.getValue( id );
			return subRowValue
				? subRowValue
						.toString()
						.toLowerCase()
						.includes( value.toLowerCase() )
				: false;
		} );
	return mainRowMatch || subRowMatch;
};

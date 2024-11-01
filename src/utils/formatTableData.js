export function getCategoryStructure( categories ) {
	let categoryStructure = '';

	// Identify all parent categories
	const parentCategories = categories.filter(
		( cat ) => cat.parent_id === false
	);

	// Add parent categories and their subcategories
	parentCategories.forEach( ( parentCat ) => {
		const subCategories = categories
			.filter( ( cat ) => cat.parent_id === parentCat.id )
			.map( ( cat ) => cat.name );

		categoryStructure +=
			( categoryStructure ? ' | ' : '' ) + parentCat.name;
		if ( subCategories.length > 0 ) {
			categoryStructure += ' -> ' + subCategories.join( ', ' );
		}
	} );

	// Identify standalone categories (not a parent and not a child of any parent)
	const standaloneCategories = categories.filter(
		( cat ) =>
			! parentCategories.find(
				( parentCat ) => parentCat.id === cat.id
			) &&
			! parentCategories.some(
				( parentCat ) => parentCat.id === cat.parent_id
			)
	);

	// Append standalone categories
	if ( standaloneCategories.length > 0 ) {
		const standaloneNames = standaloneCategories
			.map( ( cat ) => cat.name )
			.join( ', ' );
		categoryStructure +=
			( categoryStructure ? ' | ' : '' ) + standaloneNames;
	}

	return categoryStructure;
}

export function reformatDataForTable( inventory ) {
	return inventory.map( ( item ) => {
		const variations = ( item.item_data?.variations || [] ).map(
			( variation ) => {
				// Convert stock to a number, defaulting to 0 if not a number
				const stock = isNaN( parseInt( variation.inventory_count ) )
					? 0
					: parseInt( variation.inventory_count );

				// Check for null price_money before accessing amount
				const price = variation.item_variation_data.price_money
					? variation.item_variation_data.price_money.amount / 100
					: 0; // Default price to 0 if price_money is null

				return {
					sku: variation.item_variation_data.sku,
					name: variation.item_variation_data.name, // Assuming you want to use the main item's name
					type: 'variation',
					price: price,
					status: variation.imported,
					stock: stock, // Use the sanitized stock value
					id: variation.id,
					woocommerce_product_id:
						variation.woocommerce_product_id || null,
				};
			}
		);

		// Process price, including checks for null price_money before accessing amount
		const price = ( item.item_data?.variations || [] ).map( ( v ) =>
			v.item_variation_data.price_money
				? v.item_variation_data.price_money.amount / 100
				: 0
		);

		// Calculate stock values for each variation, ensuring they default to 0 if invalid
		const stockValues = ( item.item_data?.variations || [] ).map(
			( variation ) =>
				isNaN( parseInt( variation.inventory_count ) )
					? 0
					: parseInt( variation.inventory_count )
		);

		let minAmount, maxAmount;

		if ( price.length > 0 ) {
			minAmount = Math.min( ...price );
			maxAmount = Math.max( ...price );
		} else {
			minAmount = maxAmount = 0;
		}

		// Determine min and max stock
		const minStock = stockValues.length ? Math.min( ...stockValues ) : 0;
		const maxStock = stockValues.length ? Math.max( ...stockValues ) : 0;
		return {
			sku: item.item_data?.variations[ 0 ]?.item_variation_data.sku || '',
			id: item.id,
			name: item.item_data?.name || '',
			stock:
				minStock === maxStock
					? `${ minStock }`
					: `${ minStock } - ${ maxStock }`,
			image: item.item_data?.image_urls
				? item.item_data.image_urls
				: null,
			woocommerce_product_id: item.woocommerce_product_id || null,
			type:
				( item.item_data?.variations?.length || 0 ) > 1
					? 'Variable'
					: 'Simple',
			price:
				minAmount === maxAmount
					? `$${ minAmount }`
					: `$${ minAmount } - $${ maxAmount }`,
			categories: item.item_data?.categories
				? item.item_data.categories
				: '',
			status: item.imported,
			...( variations.length > 1 && { subRows: variations } ),
		};
	} );
}

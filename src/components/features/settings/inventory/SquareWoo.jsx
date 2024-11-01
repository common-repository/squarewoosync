import { useEffect, useState } from '@wordpress/element';

const SquareWoo = ( { settings, updateSettings, settingsLoading } ) => {
	const [ squareWoo, setSquareWoo ] = useState( { ...settings.squareAuto } );

	useEffect( () => {
		setSquareWoo( settings.squareAuto );
	}, [ settings ] );

	const CheckboxItem = ( { id, label, checked, squareWoo } ) => {
		return (
			<li className="w-auto mb-0">
				<div className="flex items-center gap-2 p-4">
					<input
						id={ id }
						type="checkbox"
						checked={ checked }
						onChange={ () =>
							updateSettings( 'squareAuto', {
								...squareWoo,
								[ id ]: ! checked,
							} )
						}
						className="!m-0 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2 leading-normal"
					/>
					<label
						htmlFor={ id }
						className="w-full text-sm font-light text-gray-700 leading-normal"
					>
						{ label }
					</label>
				</div>
			</li>
		);
	};

	const checkboxItems = [
		{ id: 'stock', label: 'Stock', checked: squareWoo.stock || false },
		{ id: 'title', label: 'Title', checked: squareWoo.title || false },
		{ id: 'sku', label: 'SKU', checked: squareWoo.sku || false },
		{ id: 'price', label: 'Price', checked: squareWoo.price || false },
		{
			id: 'description',
			label: 'Description',
			checked: squareWoo.description || false,
		},
		{ id: 'images', label: 'Images', checked: squareWoo.images || false },
		{
			id: 'category',
			label: 'Category',
			checked: squareWoo.category || false,
		},
	];

	return (
		<div className="px-4 pb-5 sm:px-6">
			<h3 className="text-base font-semibold leading-6 text-gray-900">
				Automatic Inventory Syncing
			</h3>
			<div className="mt-2 max-w-xl text-sm text-gray-500 mb-4">
				<p>
					Enable or disable automatic inventory syncing between Woo
					and Square effortlessly with our Inventory Sync Toggle.
					Streamline your product management with ease.
					<br></br>
					<a
						href="https://squaresyncforwoo.com/documentation#import-data"
						className="underline text-sky-500"
						target="_blank"
					>
						How to setup and control automatic syncing between
						Square and Woo
					</a>
				</p>
			</div>
			<div className="">
				<label className="relative inline-flex items-center cursor-pointer justify-start">
					<input
						type="checkbox"
						checked={ squareWoo.isActive }
						onChange={ () => {
							updateSettings( 'squareAuto', {
								...squareWoo,
								isActive: ! squareWoo.isActive,
							} );
						} }
						className="sr-only peer"
					/>
					<div className="w-11 h-6 bg-gray-200 rounded-full peer  peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all  peer-checked:bg-blue-600"></div>
					<span className="ms-3 text-sm font-medium text-gray-700 ">
						Square to woo (Webhook must be setup)
					</span>
				</label>
				{ squareWoo.isActive && ! settingsLoading && (
					<>
						<ul className="text-sm font-medium text-gray-900 bg-white my-3 flex flex-wrap fit-content">
							{ checkboxItems.map( ( item ) => (
								<CheckboxItem
									key={ item.id }
									id={ item.id }
									label={ item.label }
									checked={ item.checked }
									squareWoo={ squareWoo }
								/>
							) ) }
						</ul>
					</>
				) }
			</div>
		</div>
	);
};

export default SquareWoo;

import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { toast } from 'react-toastify';

export default function Locations( { updateSettings, locations, settings } ) {
	const [ loading, setLoading ] = useState( true );

	const handleSubmit = async ( event ) => {
		event.preventDefault();
		updateSettings( 'location', event.target.value );
	};

	return (
		<div className="sm:px-6 px-4 pb-5">
			<h3 className="text-base font-semibold leading-6 text-gray-900">
				Square Locations
			</h3>
			<div className="mt-2 max-w-xl text-sm text-gray-500">
				<p>
					Select the location you wish to derive your websites
					products and inventory from: <br></br>
				</p>
			</div>
			<div>
				<select
					id="location"
					name="location"
					onChange={ ( e ) => handleSubmit( e ) }
					value={ settings.location ? settings.location : '' }
					className="block !rounded-lg !border-0 !py-1.5 text-gray-900 !ring-1 !ring-inset !ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-sky-600 sm:text-sm !px-4 !leading-6 mt-2 !pr-10"
				>
					<option value="" disabled selected>
						Select your location
					</option>
					{ locations.map( ( loc ) => {
						return (
							<option key={ loc.id } value={ loc.id }>
								{ loc.name }
							</option>
						);
					} ) }
				</select>
			</div>
		</div>
	);
}

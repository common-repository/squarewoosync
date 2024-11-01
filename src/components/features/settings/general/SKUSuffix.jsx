import withAlertDialog from '../../../AlertDialog';

const SKUSuffixComp = ( { setIsOpen, suffix, setSuffix } ) => {
	if ( suffix === undefined ) {
		suffix = 'sws';
	}

	const handleSubmit = ( e ) => {
		e.preventDefault(); // Prevent the default form submission behavior

		// Check if the parsed value is greater than 0
		if ( suffix && suffix.length > 0 ) {
			setIsOpen( true );
		} else {
			alert( 'Suffix must have at least 1 character.' );
		}
	};
	return (
		<div className="px-4 py-5 sm:p-6">
			<h3 className="text-base font-semibold leading-6 text-gray-900">
				Woo SKU Suffix
			</h3>
			<div className="mt-2 max-w-xl text-sm text-gray-500">
				<p>
					Change the default SKU Suffix to your liking. This should be
					done before any syncing of products between Square and Woo.
					Changing the suffix after it any previous imports/syncs
					requires products to be deleted and re-imported. Read the
					following documentation on why this is required: <br></br>
					<a href="#" className="underline text-sky-500">
						Changing the SKU Suffix
					</a>
				</p>
			</div>

			<form
				className="mt-5 sm:flex sm:items-center"
				onSubmit={ handleSubmit }
			>
				<div className="w-full sm:max-w-xs">
					<label htmlFor="accessToken" className="sr-only">
						Access Token
					</label>
					<input
						type="text"
						name="suffix"
						value={ suffix }
						onChange={ ( e ) => setSuffix( e.target.value ) }
						id="suffix"
						className="block w-full !rounded-lg !border-0 !py-1.5 text-gray-900 !ring-1 !ring-inset !ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-sky-600 sm:text-sm !px-4 !leading-6"
						placeholder="sws"
					/>
				</div>
				<button
					type="submit"
					className="mt-3 inline-flex w-full items-center justify-center rounded-md bg-sky-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600 sm:ml-3 sm:mt-0 sm:w-auto"
				>
					Save
				</button>
			</form>
		</div>
	);
};

const SKUSuffix = withAlertDialog( SKUSuffixComp );
export default SKUSuffix;

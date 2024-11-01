import {
	ArrowLeftCircleIcon,
	ArrowRightCircleIcon,
} from '@heroicons/react/24/outline';

export const StepTwo = ( {
	productsToImport,
	rangeValue,
	dataToImport,
	handleStepChange,
	importProduct,
	importCount,
} ) => {
	return (
		<div>
			<h4 className="text-base mb-4">Review</h4>
			<p>
				You are about to import{ ' ' }
				<span className="font-semibold">{ importCount }</span> products
				in batches of{ ' ' }
				<span className="font-semibold">{ rangeValue }</span>. Existing
				products will have their data updated, while new entries will be
				created for products not already in the system.
			</p>
			<div className="mt-2">
				<p>You have chosen to import/sync the following:</p>
				<ul className="flex gap-2 mt-2 flex-wrap">
					{ Object.keys( dataToImport ).map( ( key, idx ) => {
						if ( dataToImport[ key ] ) {
							return (
								<li
									key={ dataToImport[ key ] + idx }
									className="p-2 border border-gray-300 uppercase text-xs font-semibold"
								>
									{ key }
								</li>
							);
						}
					} ) }
				</ul>
			</div>
			<div className="flex items-center mt-10 justify-end gap-2">
				<button
					type="button"
					onClick={ () => handleStepChange( 'backward' ) }
					className="relative inline-flex items-center rounded-md bg-gray-400 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-sky-400"
				>
					<ArrowLeftCircleIcon
						className="mr-1.5 h-4 w-4 text-white"
						aria-hidden="true"
					/>
					<span>Go back</span>
				</button>
				<button
					type="button"
					onClick={ () => {
						handleStepChange( 'forward' );
						importProduct();
					} }
					className="relative inline-flex items-center rounded-md bg-red-500 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-sky-400"
				>
					<span>IMPORT</span>
					<ArrowRightCircleIcon
						className="ml-1.5 h-4 w-4 text-white"
						aria-hidden="true"
					/>
				</button>
			</div>
		</div>
	);
};

import { ArrowRightCircleIcon, XCircleIcon } from '@heroicons/react/24/outline';

export const StepOne = ( {
	dataToImport,
	setDataToImport,
	rangeValue,
	handleRangeChange,
	handleStepChange,
	setCurrentStep,
	setIsDialogOpen,
} ) => {
	return (
		<div>
			<h4 className="text-base mb-4">
				Select the data you wish to import / sync:
			</h4>
			<fieldset className="mb-3">
				<legend className="sr-only">data to sync</legend>
				<div className="flex gap-x-6 gap-y-4 items-start flex-wrap">
					<label
						htmlFor="title"
						className="flex items-center gap-1 leading-none"
					>
						<input
							type="checkbox"
							required
							checked={ dataToImport.title }
							disabled
							id="title"
							className="h-full !m-0"
						/>
						Title
					</label>
					<label
						htmlFor="SKU"
						className="flex items-center gap-1 leading-none"
					>
						<input
							type="checkbox"
							checked={ dataToImport.sku }
							id="SKU"
							className="h-full !m-0"
							onChange={ () =>
								setDataToImport( {
									...dataToImport,
									sku: ! dataToImport.sku,
								} )
							}
						/>
						SKU
					</label>
					<label
						htmlFor="price"
						className="flex items-center gap-1 leading-none"
					>
						<input
							type="checkbox"
							id="price"
							className="h-full !m-0"
							checked={ dataToImport.price }
							onChange={ () =>
								setDataToImport( {
									...dataToImport,
									price: ! dataToImport.price,
								} )
							}
						/>
						Price
					</label>
					<label
						htmlFor="stock"
						className="flex items-center gap-1 leading-none"
					>
						<input
							type="checkbox"
							id="stock"
							className="h-full !m-0"
							checked={ dataToImport.stock }
							onChange={ () =>
								setDataToImport( {
									...dataToImport,
									stock: ! dataToImport.stock,
								} )
							}
						/>
						Stock
					</label>
					<label
						htmlFor="description"
						className="flex items-center gap-1 leading-none"
					>
						<input
							type="checkbox"
							id="description"
							checked={ dataToImport.description }
							onChange={ () =>
								setDataToImport( {
									...dataToImport,
									description: ! dataToImport.description,
								} )
							}
							className="h-full !m-0"
						/>
						Description
					</label>
					<label
						htmlFor="image"
						className="flex items-center gap-1 leading-none"
					>
						<input
							type="checkbox"
							id="image"
							className="h-full !m-0"
							disabled
							checked={ false }
							
						/>
						Image <a class="pro-badge !relative" href="https://squaresyncforwoo.com" target="_blank">PRO</a>
					</label>
					<label
						htmlFor="categories"
						className="flex items-center gap-1 leading-none"
					>
						<input
							type="checkbox"
							disabled
							id="categories"
							className="h-full !m-0"
							checked={ false }
						/>
						Categories <a class="pro-badge !relative" href="https://squaresyncforwoo.com" target="_blank">PRO</a>
					</label>

				</div>
			</fieldset>
			<p>
				Existing products will have their data updated, while new
				entries will be created for products not already in the system.
			</p>
			<h4 className="text-base mt-4 mb-2">
				How many products to import in each batch?
			</h4>
			<p>
				Increasing the number in each batch places a greater load on the
				server (especially when import images). If you encounter errors,
				consider reducing this value for better stability or disabling
				image import.
			</p>

			<div className="relative mb-6 mt-3">
				<label htmlFor="labels-range-input" className="sr-only">
					Labels range
				</label>
				<input
					id="labels-range-input"
					type="range"
					value={ rangeValue }
					onChange={ handleRangeChange }
					step={ 5 }
					min="5"
					max="50"
					className="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
				/>
				<span className="text-sm text-gray-500 absolute start-0 -bottom-6">
					Min 5
				</span>
				{ /* Display the current value */ }
				<span className="text-sm text-gray-600 font-semibold absolute start-1/2 -translate-x-1/2 -bottom-6">
					{ rangeValue }
				</span>
				<span className="text-sm text-gray-500 absolute end-0 -bottom-6">
					Max 50
				</span>
			</div>
			<div className="flex items-center mt-10 justify-end gap-2">
				<button
					type="button"
					onClick={ () => {
						setCurrentStep( 0 );
						setIsDialogOpen( false );
					} }
					className="relative inline-flex items-center rounded-md bg-gray-400 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-sky-400"
				>
					<span>Cancel</span>
					<XCircleIcon
						className="ml-1.5 h-4 w-4 text-white"
						aria-hidden="true"
					/>
				</button>
				<button
					type="button"
					onClick={ () => handleStepChange( 'forward' ) }
					className="relative inline-flex items-center rounded-md bg-sky-500 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-sky-400"
				>
					<span>Continue</span>
					<ArrowRightCircleIcon
						className="ml-1.5 h-4 w-4 text-white"
						aria-hidden="true"
					/>
				</button>
			</div>
		</div>
	);
};

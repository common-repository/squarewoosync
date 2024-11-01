import { useState } from '@wordpress/element';
import DialogWrapper from '../../../Dialog';
import { StepController } from './steps/StepController';

const ImportDialog = ( {
	isDialogOpen,
	setIsDialogOpen,
	progress,
	rangeValue,
	setRangeValue,
	setDataToImport,
	dataToImport,
	importProduct,
	productsToImport,
	importCount,
	isImporting,
} ) => {
	const steps = [
		{ name: 'Step 1', href: '#', status: 'current' },
		{ name: 'Step 2', href: '#', status: 'upcoming' },
		{ name: 'Step 3', href: '#', status: 'upcoming' },
	];
	const [ currentStep, setCurrentStep ] = useState( 0 );

	const handleRangeChange = ( event ) => {
		setRangeValue( Number( event.target.value ) );
	};

	const handleStepChange = ( direction ) => {
		setCurrentStep( ( prev ) => {
			// Check if moving forward
			if ( direction === 'forward' && prev < steps.length - 1 ) {
				return prev + 1;
			}
			// Check if moving backward
			else if ( direction === 'backward' && prev > 0 ) {
				return prev - 1;
			}
			return prev; // Return current step if no change is possible
		} );
	};

	return (
		<DialogWrapper
			open={ isDialogOpen }
			onClose={ () => setIsDialogOpen( false ) }
			className="w-[40vw] max-w-[40vw] mx-auto bg-white p-6 rounded-xl"
		>
			<div className="w-full">
				<header className="flex justify-between items-center gap-2 mb-4">
					<h3 className="text-lg font-medium leading-6 text-gray-900">
						Import from Square
					</h3>
					<nav
						className="flex items-center justify-center"
						aria-label="Progress"
					>
						<p className="text-sm font-medium">
							Step { currentStep + 1 } of { steps.length }
						</p>
						<ol
							role="list"
							className="ml-8 flex items-center space-x-5"
						>
							{ steps.map( ( step, idx ) => (
								<li key={ step.name }>
									{ step.status === 'complete' ? (
										<span className="block h-2.5 w-2.5 rounded-full bg-sky-600 hover:bg-sky-900">
											<span className="sr-only">
												{ step.name }
											</span>
										</span>
									) : currentStep === idx ? (
										<span
											className="relative flex items-center justify-center"
											aria-current="step"
										>
											<span
												className="absolute flex h-5 w-5 p-px"
												aria-hidden="true"
											>
												<span className="h-full w-full rounded-full bg-sky-200" />
											</span>
											<span
												className="relative block h-2.5 w-2.5 rounded-full bg-sky-600"
												aria-hidden="true"
											/>
											<span className="sr-only">
												{ step.name }
											</span>
										</span>
									) : (
										<span className="block h-2.5 w-2.5 rounded-full bg-gray-200 hover:bg-gray-400">
											<span className="sr-only">
												{ step.name }
											</span>
										</span>
									) }
								</li>
							) ) }
						</ol>
					</nav>
				</header>
				<StepController
					currentStep={ currentStep }
					rangeValue={ rangeValue }
					dataToImport={ dataToImport }
					handleStepChange={ handleStepChange }
					setCurrentStep={ setCurrentStep }
					handleRangeChange={ handleRangeChange }
					importCount={ importCount }
					productsToImport={ productsToImport }
					setDataToImport={ setDataToImport }
					importProduct={ importProduct }
					isImporting={ isImporting }
					setIsDialogOpen={ setIsDialogOpen }
					progress={ progress }
				/>
				{ isImporting && (
					<p className="text-red-500 font-semibold text-center mt-2">
						Do not close this window, import will be cancelled
					</p>
				) }
			</div>
		</DialogWrapper>
	);
};

export default ImportDialog;

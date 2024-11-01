import React from 'react';
import { StepTwo } from './StepTwo';
import { StepThree } from './StepThree';
import { StepOne } from './StepOne';

export const StepController = ( {
	currentStep,
	rangeValue,
	dataToImport,
	handleStepChange,
	setCurrentStep,
	handleRangeChange,
	setDataToImport,
	importProduct,
	importCount,
	productsToImport,
	isImporting,
	setIsDialogOpen,
	progress,
} ) => {
	switch ( currentStep ) {
		case 0:
			return (
				<StepOne
					dataToImport={ dataToImport }
					setDataToImport={ setDataToImport }
					rangeValue={ rangeValue }
					handleRangeChange={ handleRangeChange }
					handleStepChange={ handleStepChange }
					setCurrentStep={ setCurrentStep }
					setIsDialogOpen={ setIsDialogOpen }
				/>
			);
		case 1:
			return (
				<StepTwo
					progress={ progress }
					dataToImport={ dataToImport }
					importProduct={ importProduct }
					importCount={ importCount }
					handleStepChange={ handleStepChange }
					setCurrentStep={ setCurrentStep }
					productsToImport={ productsToImport }
					isImporting={ isImporting }
					rangeValue={ rangeValue }
					setIsDialogOpen={ setIsDialogOpen }
				/>
			);
		case 2:
			return (
				<StepThree
					progress={ progress }
					importCount={ importCount }
					handleStepChange={ handleStepChange }
					setCurrentStep={ setCurrentStep }
					isImporting={ isImporting }
					setIsDialogOpen={ setIsDialogOpen }
				/>
			);
		default:
			return <div>Invalid step</div>;
	}
};

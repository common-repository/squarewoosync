import { CheckCircleIcon } from '@heroicons/react/20/solid';
import { XCircleIcon } from '@heroicons/react/24/outline';
import { useRef, useEffect } from '@wordpress/element';

export const StepThree = ( {
	progress,
	importCount,
	isImporting,
	setIsDialogOpen,
	setCurrentStep,
} ) => {
	// Inside your component...
	const loggerContainerRef = useRef( null );

	// Use useEffect to scroll to the bottom when new content is added
	useEffect( () => {
		if ( loggerContainerRef.current ) {
			loggerContainerRef.current.scrollTop =
				loggerContainerRef.current.scrollHeight;
		}
	}, [ progress ] );

	return (
		<div>
			<div>
				<div className="">
					{ /* Progress bar */ }
					<div className="h-4 bg-gray-200 w-full rounded-lg mt-2">
						<div
							className="h-full bg-blue-500 rounded-lg"
							style={ {
								width: `${
									( progress.filter(
										( prg ) => typeof prg !== 'string'
									).length /
										importCount ) *
									100
								}%`,
							} }
						></div>
					</div>
					{ /* Progress text */ }
					<div className="text-sm text-gray-500 mt-1">
						Imported{ ' ' }
						{
							progress.filter(
								( prg ) =>
									typeof prg !== 'string' &&
									prg.status === 'success'
							).length
						}{ ' ' }
						of { importCount } products.{ ' ' }
						{ Number(
							( progress.filter(
								( prg ) => typeof prg !== 'string'
							).length /
								importCount ) *
								100
						).toFixed( 1 ) }
						%
					</div>
				</div>

				{ /* Logger container with scroll */ }
				<div
					ref={ loggerContainerRef }
					className="bg-slate-950 p-4 rounded-xl max-h-52 overflow-y-auto overflow-x-hidden w-full flex flex-col gap-2 mt-2"
				>
					{ progress.map( ( prog, index ) => {
						return (
							<p
								className={ `break-words ${
									prog.status && prog.status === 'success'
										? 'text-green-500'
										: prog.status &&
										  prog.status === 'failed'
										? 'text-red-500'
										: 'text-blue-500'
								}` }
								key={ prog.square_id || prog }
							>
								{ JSON.stringify( prog ) }
							</p>
						);
					} ) }
				</div>
				{ ! isImporting && (
					<div className="flex flex-col items-center justify-center gap-2 py-4">
						<CheckCircleIcon className="w-12 h-12 text-green-500" />
						<h3 className="text-xl text-green-500 font-semibold">
							Import complete!
						</h3>
						<p className="font-semibold">
							You can now safely close this window.
						</p>
					</div>
				) }
			</div>

			{ ! isImporting && (
				<div className="flex items-center justify-end gap-2 mt-6">
					<button
						type="button"
						onClick={ () => {
							setIsDialogOpen( false );
							setCurrentStep( 0 );
						} }
						className="relative inline-flex items-center rounded-md bg-gray-400 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-sky-400"
					>
						<XCircleIcon
							className="mr-1.5 h-4 w-4 text-white"
							aria-hidden="true"
						/>
						<span>Close</span>
					</button>
				</div>
			) }
		</div>
	);
};

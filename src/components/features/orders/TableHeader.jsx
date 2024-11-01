import { ArrowPathIcon } from '@heroicons/react/20/solid';
import DebouncedInput from '../../DebouncedInput';

function TableHeader( { fetchOrders, globalFilter, setGlobalFilter } ) {
	return (
		<div className="flex justify-between items-center w-full">
			<div
				className="text-sm leading-6 text-gray-900 py-5
    flex gap-4 items-center"
			>
				<h2 className="text-base font-semibold leading-7 text-gray-900 ">
					Woo Orders
				</h2>
				<button
					type="button"
					onClick={ () => fetchOrders() }
					className="relative inline-flex items-center rounded-md bg-sky-500 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-sky-400"
				>
					<ArrowPathIcon
						className="-ml-0.5 mr-1.5 h-4 w-4 text-white"
						aria-hidden="true"
					/>
					<span>Refresh</span>
				</button>
			</div>
			{ /* <div className="relative flex">
                <DebouncedInput
                    value={globalFilter ?? ''}
                    onChange={(value) => setGlobalFilter(value)}
                    className="block w-full rounded-md border-0 py-1.5 pr-14 pl-4 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-sku-600 sm:text-sm sm:leading-6"
                    placeholder="Search orders..."
                />
                <div className="absolute inset-y-0 right-0 flex py-1.5 pr-1.5">
                    <kbd className="inline-flex items-center rounded border border-gray-200 px-1 font-sans text-xs text-gray-400">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            className="feather feather-search w-3 h-3"
                        >
                            <circle cx="11" cy="11" r="8" />
                            <line
                                x1="21"
                                y1="21"
                                x2="16.65"
                                y2="16.65"
                            />
                        </svg>
                    </kbd>
                </div>
            </div> */ }
		</div>
	);
}

export default TableHeader;
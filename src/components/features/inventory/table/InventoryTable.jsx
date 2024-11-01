/**
 * External dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { useReactTable } from '@tanstack/react-table';
import {
	ArrowDownOnSquareStackIcon,
	ArrowPathIcon,
} from '@heroicons/react/24/outline';
import { useSelector } from 'react-redux';

/**
 * Internal dependencies
 */
import DebouncedInput from '../../../DebouncedInput';
import TableHeader from './TableHeader';
import TableRow from './TableRow';
import PaginationControls from './PaginationControls';
import ImportDialog from '../import/ImportDialog';
import { useNavigationBlocker } from '../../../NavigationContext';
import { useTableData } from '../../../hooks/useTableData';
import { useImport } from '../../../hooks/useImport';

const controller =
	typeof AbortController === 'undefined' ? undefined : new AbortController();

const InventoryTable = ( { getInventory } ) => {
	const inventory = useSelector( ( state ) => state.inventory.items );
	const [ isDialogOpen, setIsDialogOpen ] = useState( false );
	const [ rangeValue, setRangeValue ] = useState( 15 );
	const { isImporting, progress, importProduct } = useImport();
	const [ productsToImport, setProductsToImport ] = useState( [] );
	const [ dataToImport, setDataToImport ] = useState( {
		title: true,
		sku: true,
		description: true,
		stock: true,
		image: false,
		categories: false,
		price: true,
	} );

	const [ expanded, setExpanded ] = useState( {} );
	const [ sorting, setSorting ] = useState( [] );
	const [ globalFilter, setGlobalFilter ] = useState( '' );
	const [ rowSelection, setRowSelection ] = useState( {} );
	const [ selectableRows, setSelectableRows ] = useState( [] );
	const [ selectablePageRows, setSelectablePageRows ] = useState( [] );

	const tableOptions = useTableData( inventory, {
		expanded,
		setExpanded,
		sorting,
		setSorting,
		globalFilter,
		setGlobalFilter,
		isImporting,
		setProductsToImport,
		setIsDialogOpen,
		rowSelection,
		setRowSelection,
		selectableRows,
		setSelectableRows,
	} );

	const tableInstance = useReactTable( tableOptions );

	useEffect( () => {
		const currentPageRows = tableInstance.getCoreRowModel().rows;
		const newSelectableRows = currentPageRows
			.filter(
				( row ) =>
					! (
						row.original.subRows && row.original.subRows.length > 0
					)
			)
			.map( ( row ) => row.id );
		setSelectableRows( newSelectableRows );
	}, [ tableInstance.getRowModel() ] );

	// This useEffect is to handle selectable rows based on the current page
	useEffect( () => {
		const currentPageRows = tableInstance.getRowModel().rows;

		const newSelectableRows = currentPageRows
			.filter(
				( row ) =>
					! (
						row.original.subRows && row.original.subRows.length > 0
					)
			)
			.map( ( row ) => row.id );
		setSelectablePageRows( newSelectableRows );
	}, [ tableInstance.getRowModel() ] ); // Dependency on the row model to update when pagination changes

	const handleImport = () => {
		importProduct(
			productsToImport,
			inventory,
			controller,
			dataToImport,
			rangeValue
		);
		resetTablePageIndex();
	};

	function resetTablePageIndex() {
		const currentPageIndex = tableInstance.getState().pagination.pageIndex;
		tableInstance.setPageIndex( currentPageIndex );
	}

	useEffect( () => {
		function handleBeforeUnload( e ) {
			if ( isImporting ) {
				e.preventDefault();
				e.returnValue = '';
			}
		}

		if ( isImporting ) {
			window.addEventListener( 'beforeunload', handleBeforeUnload );
		}

		return () => {
			window.removeEventListener( 'beforeunload', handleBeforeUnload );
		};
	}, [ isImporting ] );

	const { setBlockNavigation } = useNavigationBlocker();

	// When the import starts
	useEffect( () => {
		if ( isImporting ) {
			setBlockNavigation( true );
		} else {
			setBlockNavigation( false );
		}
	}, [ isImporting, setBlockNavigation ] );

	const handleDialog = ( open ) => {
		setIsDialogOpen( open );
	};

	return (
		<div>
			<ImportDialog
				dataToImport={ dataToImport }
				setDataToImport={ setDataToImport }
				importCount={ productsToImport.length }
				importProduct={ handleImport }
				controller={ controller }
				isImporting={ isImporting }
				productsToImport={ productsToImport }
				rangeValue={ rangeValue }
				setRangeValue={ setRangeValue }
				isDialogOpen={ isDialogOpen }
				progress={ progress }
				setIsDialogOpen={ ( open ) => handleDialog( open ) }
			/>
			<div className="px-4 py-5 sm:px-6">
				<div className="grid grid-cols-3 gap-2 mb-4 items-center">
					<div className="flex flex-wrap items-center justify-start sm:flex-nowrap">
						<h2 className="text-base font-semibold leading-7 text-gray-900 ">
							Square Inventory
						</h2>
						<div className="ml-4 flex flex-shrink-0">
							<button
								type="button"
								onClick={ getInventory }
								className="relative inline-flex items-center rounded-md bg-sky-500 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-sky-400"
							>
								<ArrowPathIcon
									className="-ml-0.5 mr-1.5 h-4 w-4 text-white"
									aria-hidden="true"
								/>
								<span>Refresh</span>
							</button>
						</div>
					</div>
					<div className="relative flex">
						<DebouncedInput
							value={ globalFilter ?? '' }
							onChange={ ( value ) => setGlobalFilter( value ) }
							className="block w-full rounded-md border-0 py-1.5 pr-14 pl-4 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-sku-600 sm:text-sm sm:leading-6"
							placeholder="Search inventory..."
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
					</div>
					<div className="flex justify-end items-center">
						<button
							type="button"
							onClick={ () => {
								// Retrieve all rows from the table instance
								const allRows =
									tableInstance.getFilteredRowModel().rows;
								// Filter for rows that are selected
								const selectedRows = allRows
									.filter( ( row ) => row.getIsSelected() )
									.map( ( row ) => row.original );
								// Now, `selectedRows` contains only the data for rows that were selected
								if ( selectedRows.length === 0 ) {
									setProductsToImport(
										allRows.filter(
											( row ) =>
												! (
													row.original.subRows &&
													row.original.subRows
														.length > 0
												)
										)
									);
								} else {
									setProductsToImport( selectedRows );
								}

								setIsDialogOpen( true );
							} }
							className="disabled:bg-gray-200 relative inline-flex items-center rounded-md bg-sky-500 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-sky-400"
						>
							<ArrowDownOnSquareStackIcon
								className="-ml-0.5 mr-1.5 h-4 w-4 text-white"
								aria-hidden="true"
							/>
							<span>
								{ Object.keys( rowSelection ).length < 1
									? 'Import all'
									: 'Import ' +
									  Object.keys( rowSelection ).length +
									  ' selected products' }
							</span>
						</button>
					</div>
				</div>
			</div>
			<div className="sm:px-6 lg:px-8 relative overflow-auto w-full">
				<table className="w-full min-w-full whitespace-nowrap text-left bg-white">
					<TableHeader table={ tableInstance } />
					<tbody className="divide-y divide-gray-200">
						{ tableInstance.getRowModel().rows.map( ( row ) => (
							<TableRow
								key={ row.id }
								row={ row }
								toggleExpanded={ () => {
									if ( ! row.getCanExpand() ) return;
									row.toggleExpanded();
								} }
							/>
						) ) }
					</tbody>
				</table>
			</div>
			<hr />
			<PaginationControls
				table={ tableInstance }
				selectablePageRows={ selectablePageRows }
				rowSelection={ rowSelection }
				setRowSelection={ setRowSelection }
			/>
		</div>
	);
};

export default InventoryTable;
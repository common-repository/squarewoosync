import { useMemo } from '@wordpress/element';
import {
	getCoreRowModel,
	getSortedRowModel,
	getPaginationRowModel,
	getFilteredRowModel,
	getExpandedRowModel,
} from '@tanstack/react-table';
import {
	getCategoryStructure,
	reformatDataForTable,
} from '../../utils/formatTableData';
import {
	ExpanderIcon,
	ImageCell,
	PlaceholderIcon,
	StatusIcon,
} from '../features/inventory/table/Icons';
import IndeterminateCheckbox from '../features/inventory/IndeterminateCheckbox';
import { filterRows } from '../../utils/filterRows';

export const useTableData = (
	inventory,
	{
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
	}
) => {
	// Assuming reformatDataForTable is a function to format your inventory data as needed
	const reformattedData = useMemo(
		() => reformatDataForTable( inventory ),
		[ inventory ]
	);

	const columns = useMemo(
		() => [
			{
				id: 'expander',
				width: 50,
				cell: ( { row } ) => {
					return (
						<>
							{ row.getCanExpand() ? (
								<button type="button">
									<ExpanderIcon
										isExpanded={ row.getIsExpanded() }
										row={ row }
									/>
								</button>
							) : null }
						</>
					);
				},
			},
			{
				accessorKey: 'id',
				header: () => 'id',
				show: false,
			},
			{
				accessorKey: 'sku',
				header: () => 'SKU',
				canSort: true,
			},
			{
				accessorKey: 'image',
				header: () => '',
				enableSorting: false,
				width: 50,
				cell: ( { getValue } ) => {
					const value = getValue();
					return value ? (
						<ImageCell value={ value } />
					) : (
						<PlaceholderIcon />
					);
				},
			},
			{
				accessorKey: 'name',
				header: () => 'Name',
				canSort: true,
			},
			{
				accessorKey: 'type',
				header: () => 'Type',
				canSort: true,
			},
			{
				accessorKey: 'price',
				canSort: true,
				header: () => 'Price',
			},
			{
				accessorKey: 'stock',
				canSort: true,
				header: () => 'Stock',
			},
			{
				accessorKey: 'categories',
				header: () => 'categories',
				canSort: true,
				cell: ( { getValue } ) => {
					const value = getValue();
					return value && value.length > 0
						? getCategoryStructure( value )
						: '';
				},
			},
			{
				accessorKey: 'status',
				canSort: true,
				header: () => 'Status',
				cell: ( { getValue } ) => {
					const value = getValue();
					return <StatusIcon status={ value } />;
				},
			},
			{
				id: 'actions',
				colSpan: 2,
				cell: ( { row } ) => {
					if ( row.parentId ) return <></>;
					if ( row.getCanExpand() ) {
						return (
							<div className="relative flex justify-end">
								<span className="inline-flex items-center rounded-md bg-purple-50 px-1.5 py-0.5 text-xs font-medium text-purple-700 ring-1 ring-inset ring-purple-700/10 whitespace-nowrap -top-4 uppercase">
									Pro Only
								</span>
							</div>
						);
					}
					return (
						<div className="flex items-center justify-end gap-2">
							{ row.original.woocommerce_product_id && (
								<a
									className="rounded  px-2 py-1 text-xs font-semibold text-sky-500 border-sky-500 border hover:border-sky-200 shadow-sm  hover:text-sky-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 cursor-pointer"
									href={ `/wp-admin/post.php?post=${ row.original.woocommerce_product_id }&action=edit` }
									target="_blank"
								>
									View Woo Product
								</a>
							) }
							<button
								type="button"
								onClick={ () => {
									setProductsToImport( [ row.original ] );
									setIsDialogOpen( true );
								} }
								disabled={ isImporting }
								className="rounded bg-sky-600 px-2 py-1 text-xs font-semibold text-white shadow-sm hover:bg-sky-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600"
							>
								{ row.original.status === true
									? 'Sync'
									: 'Import' }
							</button>
						</div>
					);
				},
			},
			{
				id: 'select',
				header: ( { table } ) => {
					// Determine the selection state of all non-expandable rows
					const allRowsSelected = table
						.getFilteredRowModel()
						.rows.filter( ( row ) => ! row.getCanExpand() ) // Only consider rows that cannot expand
						.every( ( row ) => row.getIsSelected() );

					const someRowsSelected =
						table
							.getFilteredRowModel()
							.rows.filter( ( row ) => ! row.getCanExpand() ) // Only consider rows that cannot expand
							.some( ( row ) => row.getIsSelected() ) &&
						! allRowsSelected;

					return (
						<div className="flex justify-center items-center w-full gap-2 relative">
							<IndeterminateCheckbox
								checked={ allRowsSelected }
								indeterminate={ someRowsSelected }
								onChange={ ( e ) => {
									table
										.getFilteredRowModel()
										.rows.forEach( ( row ) => {
											if ( ! row.getCanExpand() ) {
												// Apply change only to rows that cannot expand
												row.toggleSelected(
													e.target.checked
												);
											}
										} );
								} }
							/>
						</div>
					);
				},
				cell: ( { row } ) => {
					if ( row.getCanExpand() ) return <></>;
					if ( ! row.parentId ) {
						return (
							<div className="px-1">
								<IndeterminateCheckbox
									{ ...{
										checked: row.getIsSelected(),
										disabled: ! row.getCanSelect(),
										indeterminate: row.getIsSomeSelected(),
										onChange:
											row.getToggleSelectedHandler(),
									} }
								/>
							</div>
						);
					}
				},
			},
		],
		[]
	);

	const options = useMemo(
		() => ( {
			data: reformattedData,
			columns,
			state: {
				expanded,
				sorting,
				columnVisibility: {
					id: false,
				},
				globalFilter,
				rowSelection,
			},
			filterFns: {
				custom: filterRows,
			},
			onExpandedChange: setExpanded,
			onRowSelectionChange: setRowSelection,
			globalFilterFn: 'custom', // Ensure you have implemented a custom filter function as needed
			getSubRows: ( row ) => row.subRows || [], // Update as necessary
			getCoreRowModel: getCoreRowModel(),
			getPaginationRowModel: getPaginationRowModel(),
			getFilteredRowModel: getFilteredRowModel(),
			getExpandedRowModel: getExpandedRowModel(),
			onSortingChange: setSorting,
			onGlobalFilterChange: setGlobalFilter,
			getSortedRowModel: getSortedRowModel(),
			autoResetPageIndex: false,
			enableRowSelection: true,
			onRowSelectionChange: setRowSelection,
			getRowId: ( row ) => row.id, // Ensure you have a unique identifier for each row
		} ),
		[
			reformattedData,
			columns,
			expanded,
			sorting,
			globalFilter,
			setExpanded,
			setSorting,
			setGlobalFilter,
			rowSelection,
			setRowSelection,
			selectableRows,
			setSelectableRows,
		]
	);

	return options;
};
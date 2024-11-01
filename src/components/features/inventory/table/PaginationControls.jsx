import { useMemo, useState, useRef, useEffect } from '@wordpress/element';

function IndeterminateCheckbox( { indeterminate, className = '', ...rest } ) {
	const ref = useRef( null );

	useEffect( () => {
		if ( typeof indeterminate === 'boolean' ) {
			ref.current.indeterminate = ! rest.checked && indeterminate;
		}
	}, [ ref, indeterminate ] );

	return (
		<input
			type="checkbox"
			ref={ ref }
			className={ className + ' cursor-pointer' }
			{ ...rest }
		/>
	);
}

const PaginationControls = ( {
	table,
	selectablePageRows,
	rowSelection,
	setRowSelection,
} ) => {
	return (
		<div className="flex justify-between items-center sm:px-6 lg:px-8">
			<div className="flex items-center gap-2 ">
				<button
					className="border rounded p-1"
					onClick={ () => table.setPageIndex( 0 ) }
					disabled={ ! table.getCanPreviousPage() }
				>
					{ '<<' }
				</button>
				<button
					className="border rounded p-1"
					onClick={ () => table.previousPage() }
					disabled={ ! table.getCanPreviousPage() }
				>
					{ '<' }
				</button>
				<button
					className="border rounded p-1"
					onClick={ () => table.nextPage() }
					disabled={ ! table.getCanNextPage() }
				>
					{ '>' }
				</button>
				<button
					className="border rounded p-1"
					onClick={ () =>
						table.setPageIndex( table.getPageCount() - 1 )
					}
					disabled={ ! table.getCanNextPage() }
				>
					{ '>>' }
				</button>
				<span className="flex items-center gap-1">
					<div>Page</div>
					<strong>
						{ table.getState().pagination.pageIndex + 1 } of{ ' ' }
						{ table.getPageCount() }
					</strong>
				</span>
				<span className="flex items-center gap-1">
					| Go to page:
					<input
						type="number"
						defaultValue={
							table.getState().pagination.pageIndex + 1
						}
						onChange={ ( e ) => {
							const page = e.target.value
								? Number( e.target.value ) - 1
								: 0;
							table.setPageIndex( page );
						} }
						className="border p-1 rounded w-16"
					/>
				</span>
				<select
					value={ table.getState().pagination.pageSize }
					onChange={ ( e ) => {
						table.setPageSize( Number( e.target.value ) );
					} }
				>
					{ [ 10, 20, 30, 40, 50 ].map( ( pageSize ) => (
						<option key={ pageSize } value={ pageSize }>
							Show { pageSize }
						</option>
					) ) }
				</select>
			</div>
			<div className="flex justify-center items-center pr-2 gap-2 py-4">
				Select Page (
				{
					table
						.getRowModel()
						.rows.filter( ( row ) => ! row.getCanExpand() ).length
				}
				)
				<IndeterminateCheckbox
					checked={ selectablePageRows.every(
						( rowId ) => rowSelection[ rowId ]
					) }
					indeterminate={
						selectablePageRows.some(
							( rowId ) => rowSelection[ rowId ]
						) &&
						! selectablePageRows.every(
							( rowId ) => rowSelection[ rowId ]
						)
					}
					onChange={ ( e ) => {
						let newSelection = { ...rowSelection };
						if ( e.target.checked ) {
							// When checked, set all selectable rows to true
							selectablePageRows.forEach( ( rowId ) => {
								newSelection[ rowId ] = true;
							} );
						} else {
							// When unchecked, set all selectable rows to false
							newSelection = {};
						}
						setRowSelection( newSelection );
					} }
				/>
			</div>
		</div>
	);
};

export default PaginationControls;
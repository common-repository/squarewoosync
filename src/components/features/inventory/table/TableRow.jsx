import { flexRender } from '@tanstack/react-table';
import { classNames } from '../../../../utils/classHelper';

const TableRow = ( { row, toggleExpanded } ) => {
	const isSubRow = row.original.type === 'variation';
	const isExpanded = row.getIsExpanded();

	const rowClassNames = classNames(
		isSubRow ? 'bg-sky-50' : '', // Example style for sub-rows,
		'py-4 wrap'
	);
	return (
		<tr
			key={ row.id }
			className={ classNames(
				rowClassNames,
				isExpanded ? 'bg-sky-300' : ''
			) }
		>
			{ row.getVisibleCells().map( ( cell, idx ) => {
				return (
					<td
						key={ cell.id }
						onClick={ () => {
							if (
								cell.column.id === 'select' ||
								cell.column.id === 'actions'
							)
								return;
							toggleExpanded();
						} }
						className={ `py-4 wrap text-gray-600 ${
							idx === row.getVisibleCells().length - 1
								? 'text-right'
								: 'text-left'
						} ${ row.getCanExpand() && 'cursor-pointer' } ` }
					>
						{ flexRender(
							cell.column.columnDef.cell,
							cell.getContext()
						) }
					</td>
				);
			} ) }
		</tr>
	);
};

export default TableRow;
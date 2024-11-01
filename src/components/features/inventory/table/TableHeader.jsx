import {
	ChevronDownIcon,
	ChevronRightIcon,
	ChevronUpIcon,
} from '@heroicons/react/24/outline';
import { flexRender } from '@tanstack/react-table';
const TableHeader = ( { table } ) => {
	return (
		<thead className="border-b border-gray-900/10 text-sm leading-6 text-gray-900">
			{ table.getHeaderGroups().map( ( headerGroup ) => (
				<tr key={ headerGroup.id }>
					{ headerGroup.headers.map( ( header, idx ) => (
						<th
							{ ...{
								onClick:
									header.column.getToggleSortingHandler(),
								key: header.id,
								colSpan: header.colSpan,
								className: 'py-2 font-bold select-none',
								style: {
									width: idx == 0 ? '50px' : 'auto',
									cursor: header.column.getCanSort()
										? 'pointer'
										: 'default',
								},
							} }
						>
							{ header.isPlaceholder ? null : (
								<div className="flex items-end leading-none capitalize">
									{ flexRender(
										header.column.columnDef.header,
										header.getContext()
									) }
									<span>
										{ header.column.getIsSorted() ? (
											header.column.getIsSorted() ===
											'desc' ? (
												<ChevronDownIcon className="w-3 h-3" />
											) : (
												<ChevronUpIcon className="w-3 h-3" />
											)
										) : header.column.getCanSort() ? (
											<ChevronRightIcon className="w-3 h-3" />
										) : (
											''
										) }
									</span>
								</div>
							) }
						</th>
					) ) }
				</tr>
			) ) }
		</thead>
	);
};

export default TableHeader;
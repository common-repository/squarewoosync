const InvLoading = () => {
	return (
		<div>
			<div className=" sm:px-6 px-4 py-5">
				<div className="flex flex-wrap items-center justify-start sm:flex-nowrap">
					<h2 className="text-base font-semibold leading-7 text-gray-900">
						Woo Orders
					</h2>
				</div>
			</div>
			<div className="overflow-x-auto">
				<table className="whitespace-nowrap text-left bg-white w-full">
					<colgroup>
						{ /* Define column widths */ }
						<col className="w-full lg:w-1/12" />
						<col className="w-full lg:w-2/12" />
					</colgroup>
					<thead className="border-b border-gray-900/10 text-sm leading-6 text-gray-900">
						<tr>
							<th
								scope="col"
								className="py-2 pl-4 pr-8 font-semibold sm:pl-6 lg:pl-8"
							>
								ID
							</th>
							<th
								scope="col"
								className="py-2 pl-4 pr-8 font-semibold sm:pl-6 lg:pl-8"
							>
								Order Created
							</th>
							<th
								scope="col"
								className="hidden py-2 pl-0 pr-8 font-semibold sm:table-cell"
							>
								Order Status
							</th>
							<th
								scope="col"
								className="hidden py-2 pl-0 pr-8 font-semibold sm:table-cell"
							>
								Customer
							</th>
							<th
								scope="col"
								className="hidden py-2 pl-0 pr-8 font-semibold sm:table-cell"
							>
								Order Total
							</th>
							<th
								scope="col"
								className="py-2 pl-0 pr-4 text-right font-semibold sm:pr-8 sm:text-left lg:pr-20"
							>
								Sync Status
							</th>

							<th
								scope="col"
								className="hidden py-2 pl-0 pr-4 text-right font-semibold sm:table-cell sm:pr-6 lg:pr-8"
							>
								Actions
							</th>
						</tr>
					</thead>
					<tbody className="divide-y divide-gray-200 animate-pulse">
						{ /* Rows for loading placeholder */ }
						{ [ ...Array( 3 ) ].map( ( _, index ) => (
							<tr key={ index }>
								<td
									colSpan={ 7 }
									className="py-2 pl-4 pr-8 sm:pl-6 lg:pl-8"
								>
									<div className="h-6 bg-gray-200 rounded"></div>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</div>
		</div>
	);
};

export default InvLoading;
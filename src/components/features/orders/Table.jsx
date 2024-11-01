import {
    flexRender,
    getCoreRowModel,
    getExpandedRowModel,
    getFilteredRowModel,
    getSortedRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { useMemo, useState, Fragment } from '@wordpress/element';
import { classNames } from '../../../utils/classHelper';
import {
    ChevronDownIcon,
    ChevronRightIcon,
    ChevronUpIcon,
} from '@heroicons/react/20/solid';
import TableHeader from './TableHeader';
import { filterRows } from '../../../utils/filterRows';
import { useDispatch, useSelector } from 'react-redux';
import { fetchOrders, setOrders } from '../../../redux/ordersSlice';
import { PlaceholderIcon } from '../inventory/table/Icons';
import apiFetch from '@wordpress/api-fetch';
import { getPaginationRowModel } from '@tanstack/react-table';
import PaginationControls from './PaginationControls';
import { toast } from 'react-toastify';

function Table({ data }) {
    const dispatch = useDispatch();
    const { loading } = useSelector((state) => state.orders);
    const [globalFilter, setGlobalFilter] = useState('');
    const [sorting, setSorting] = useState([]);
    const [expanded, setExpanded] = useState({});
    const [creatingOrder, setCreatingOrder] = useState(null)

    const updateTable = (id, squareData) => {
        const newTableData = data.map(row => {
            if (row && row.id) {
                if (row.id === id) {
                    return {
                        ...row,
                        square_data: JSON.stringify(squareData)
                    }
                } else {
                    return row
                }
            } else {
                return row
            }
        })
        return newTableData
    }

    const createOrder = async (id) => {
        setCreatingOrder(id)
        const toastID = toast.loading(`Attempting to create Square order & transaction`);
        try {
            const result = await apiFetch({
                path: '/sws/v1/orders', // Updated path
                method: 'POST',
                data: { order_id: id },
            });

            console.log(result)
            if (result.data.payment || result.data.order) {
                toast.update(toastID, {
                    render: 'Created successfully',
                    type: 'success',
                    isLoading: false,
                    autoClose: 2000,
                    hideProgressBar: false,
                    closeOnClick: true,
                });
                dispatch(setOrders(updateTable(id, result.data)))
            } else {
                toast.update(toastID, {
                    render: 'Failed to create order & transaction',
                    type: 'error',
                    isLoading: false,
                    autoClose: false,
                    closeOnClick: true,
                });
            }
            setCreatingOrder(null)
        } catch (error) {
            toast.update(toastID, {
                render: 'Failed to create order & transaction: ' + error.error,
                type: 'error',
                isLoading: false,
                autoClose: false,
                closeOnClick: true,
            });
            console.log(error);
            setCreatingOrder(null)
        }
    };

    const columns = useMemo(
        () => [
            {
                id: 'expander',
                width: 50,
                cell: ({ row }) => {
                    // Check if the row can be expanded
                    if (!row.getCanExpand()) {
                        return null;
                    }
                    return (
                        <button
                            type="button"
                            onClick={() => {
                                setExpanded((old) => ({
                                    ...old,
                                    [row.id]: !old[row.id],
                                }));
                            }}
                        >
                            {row.getIsExpanded() ? (
                                <ChevronDownIcon className="w-4 h-4 text-black" />
                            ) : (
                                <ChevronRightIcon className="w-4 h-4 text-black" />
                            )}
                        </button>
                    );
                },
            },
            {
                accessorKey: 'id',
                header: () => 'ID',
                enableSorting: true,
            },
            {
                accessorKey: 'date',
                header: () => 'Order Created',

                enableSorting: true,
            },
            {
                accessorKey: 'status',
                header: () => 'Order Status',
                cell: ({ getValue }) => {
                    const value = getValue();
                    return (
                        <span
                            className={classNames(
                                'capitalize inline-flex items-center gap-x-1.5 rounded-md px-2 py-1 text-xs font-medium',
                                value === 'pending'
                                    ? 'bg-orange-100 text-orange-700'
                                    : value === 'completed'
                                        ? 'bg-green-100 text-green-700'
                                        : value === 'processing'
                                            ? 'bg-sky-100 text-sky-700'
                                            : 'bg-gray-100 text-gray-700'
                            )}
                        >
                            <svg
                                className="h-1.5 w-1.5 mt-[2px]"
                                viewBox="0 0 6 6"
                                aria-hidden="true"
                                fill="currentColor"
                            >
                                <circle cx={3} cy={3} r={3} />
                            </svg>
                            {value}
                        </span>
                    );
                },
                enableSorting: true,
            },
            {
                accessorKey: 'customer',
                header: () => 'Customer',
                cell: ({ getValue }) => {
                    const value = getValue();
                    return (
                        <span>
                            {value.first_name ? value.first_name : 'Guest'}{' '}
                            {value.last_name}
                        </span>
                    );
                },
                enableSorting: true,
            },
            {
                accessorKey: 'total',
                header: () => 'Order Total',
                cell: ({ getValue }) => {
                    return <span>${getValue()}</span>;
                },
                enableSorting: true,
            },
            {
                accessorKey: 'sync_statuc',
                header: () => 'Sync Status',
                cell: ({ row }) => {
                    if (row.original.square_data) {
                        return (
                            <span class="inline-flex items-center gap-x-1.5 rounded-md bg-green-100 px-2 py-1 text-xs font-medium text-green-700">
                                <svg
                                    class="h-1.5 w-1.5 fill-green-500"
                                    viewBox="0 0 6 6"
                                    aria-hidden="true"
                                >
                                    <circle cx="3" cy="3" r="3" />
                                </svg>
                                Synced
                            </span>
                        );
                    }
                    return (
                        <span className="inline-flex items-center gap-x-1.5 rounded-md bg-red-100 px-2 py-1 text-xs font-medium text-red-700">
                            <svg
                                className="h-1.5 w-1.5 fill-red-500"
                                viewBox="0 0 6 6"
                                aria-hidden="true"
                            >
                                <circle cx={3} cy={3} r={3} />
                            </svg>
                            Not synced
                        </span>
                    );
                },
                enableSorting: true,
            },
            {
                id: 'actions',
                colSpan: 2,
                cell: ({ row }) => {
                    return (
                        <div className="flex items-center justify-end gap-2">
                            <button
                                type="button"
                                onClick={(e) => {
                                    e.stopPropagation(); // Stop the event from bubbling up to the row
                                    table.setExpanded((old) => ({
                                        ...old,
                                        [row.id]: !old[row.id],
                                    }));
                                }}
                                className="rounded  px-2 py-1 text-xs font-semibold text-sky-500 border-sky-500 border hover:border-sky-200 shadow-sm  hover:text-sky-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 cursor-pointer"
                            >
                                View details
                            </button>
                        </div>
                    );
                },
            },
        ],
        []
    );

    const table = useReactTable({
        data,
        columns,
        state: {
            sorting,
            globalFilter,
            expanded,
        },
        filterFns: {
            custom: filterRows,
        },
        onSortingChange: setSorting,
        onExpandedChange: setExpanded,
        globalFilterFn: 'custom',
        onGlobalFilterChange: setGlobalFilter,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        getExpandedRowModel: getExpandedRowModel(),
        onSortingChange: setSorting,
        onGlobalFilterChange: setGlobalFilter,
        debugTable: true,
    });

    const getValueFromJson = (json) => {
        return JSON.parse(json);
    };

    return (
        <>
            <TableHeader
                fetchOrders={() =>
                    dispatch(fetchOrders({ forceRefresh: true }))
                }
                setGlobalFilter={setGlobalFilter}
                globalFilter={globalFilter}
            />
            <table className="w-full">
                <thead className="border-b border-gray-900/10 text-sm leading-6 text-gray-900">
                    {table.getHeaderGroups().map((headerGroup) => (
                        <tr key={headerGroup.id}>
                            {headerGroup.headers.map((header) => {
                                return (
                                    <th
                                        key={header.id}
                                        colSpan={header.colSpan}
                                        className="p-2 font-bold text-left"
                                    >
                                        {header.isPlaceholder ? null : (
                                            <div
                                                {...{
                                                    className:
                                                        header.column.getCanSort()
                                                            ? 'cursor-pointer select-none'
                                                            : '',
                                                    onClick:
                                                        header.column.getToggleSortingHandler(),
                                                }}
                                            >
                                                {flexRender(
                                                    header.column.columnDef
                                                        .header,
                                                    header.getContext()
                                                )}
                                                {{
                                                    asc: (
                                                        <ChevronUpIcon className="w-4 h-4 inline-block ml-1" />
                                                    ),
                                                    desc: (
                                                        <ChevronDownIcon className="w-4 h-4 inline-block ml-1" />
                                                    ),
                                                }[
                                                    header.column.getIsSorted()
                                                ] ?? null}
                                            </div>
                                        )}
                                    </th>
                                );
                            })}
                        </tr>
                    ))}
                </thead>
                <tbody className="divide-y divide-gray-200">
                    {table.getRowModel().rows.map((row) => {
                        if (creatingOrder && creatingOrder === row.original.id) {
                            return (
                                <tr key={row.id}>
                                    <td colSpan={100}>
                                        <div className='animate-pulse h-6 bg-gray-200 rounded my-1'></div>
                                    </td>
                                </tr>
                            )
                        } else {
                            return (
                                <Fragment key={row.id}>
                                    <tr
                                        className="cursor-pointer"
                                        onClick={() => {
                                            // Toggle the expanded state for this row
                                            table.setExpanded((old) => ({
                                                ...old,
                                                [row.id]: !old[row.id],
                                            }));
                                        }}
                                    >
                                        {row.getVisibleCells().map((cell) => {
                                            if (cell.column.id === 'expander') {
                                                // Chevron cell with its own click event
                                                // Prevent event bubbling to avoid triggering the row's onClick
                                                return (
                                                    <td
                                                        key={cell.id}
                                                        className="py-4 px-2"
                                                        onClick={(e) => {
                                                            e.stopPropagation(); // Stop the event from bubbling up to the row
                                                            table.setExpanded(
                                                                (old) => ({
                                                                    ...old,
                                                                    [row.id]:
                                                                        !old[row.id],
                                                                })
                                                            );
                                                        }}
                                                    >
                                                        <button
                                                            type="button"
                                                            aria-label="Expand row"
                                                        >
                                                            {row.getIsExpanded() ? (
                                                                <ChevronDownIcon className="w-4 h-4 text-black" />
                                                            ) : (
                                                                <ChevronRightIcon className="w-4 h-4 text-black" />
                                                            )}
                                                        </button>
                                                    </td>
                                                );
                                            }

                                            // Render other cells normally
                                            return (
                                                <td
                                                    key={cell.id}
                                                    className="py-4 px-2 text-gray-600"
                                                >
                                                    {flexRender(
                                                        cell.column.columnDef.cell,
                                                        cell.getContext()
                                                    )}
                                                </td>
                                            );
                                        })}
                                    </tr>
                                    {row.getIsExpanded() && (
                                        <tr>
                                            <td colSpan={100} className="">
                                                {' '}
                                                { /* Adjust the colSpan based on your table's columns */}
                                                <div className="p-6 mb-4 grid md:grid-cols-12 w-full gap-10 bg-slate-50 rounded-b-xl">
                                                    <div className="md:col-span-full">
                                                        <div className=" flex items-center justify-center gap-4">
                                                            <a
                                                                className="rounded  px-2 py-1 text-xs font-semibold text-sky-500 border-sky-500 border hover:border-sky-200 shadow-sm  hover:text-sky-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 cursor-pointer"
                                                                href={`/wp-admin/post.php?post=${row.original.id}&action=edit`}
                                                                target="_blank"
                                                            >
                                                                View Woo Order
                                                            </a>
                                                            {!row.original
                                                                .square_data &&
                                                                (row.original.status ===
                                                                    'completed' ||
                                                                    row.original.status ===
                                                                    'processing') ? (
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        createOrder(
                                                                            row
                                                                                .original
                                                                                .id
                                                                        )
                                                                    }
                                                                    className="rounded bg-sky-600 px-2 py-1 text-xs font-semibold text-white border border-sky-600 hover:border-sky-500 shadow-sm hover:bg-sky-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600"
                                                                >
                                                                    Sync to Square
                                                                </button>
                                                            ) : <></>}
                                                        </div>
                                                        {!row.original
                                                            .square_data &&
                                                            (row.original.status !==
                                                                'completed' ||
                                                                row.original.status !==
                                                                'processing') ? (
                                                            <p className="text-center mt-2 mx-auto max-w-xl">
                                                                Only completed or
                                                                processing orders can be
                                                                synced to Square
                                                            </p>
                                                        ) : <></>}
                                                    </div>
                                                    <div className="md:col-span-6">
                                                        <p className="font-semibold text-lg mb-4">
                                                            Order Line Items
                                                        </p>
                                                        <ul className="divide-y divide-gray-200">
                                                            {row.original.line_items.map(
                                                                (item) => {
                                                                    return (
                                                                        <li
                                                                            key={
                                                                                item.product_id
                                                                            }
                                                                            className="flex gap-2 items-center py-2"
                                                                        >
                                                                            {item.image ? (
                                                                                <img
                                                                                    src={
                                                                                        item.image
                                                                                    }
                                                                                    className="w-12 h-12 object-contain rounded-lg"
                                                                                />
                                                                            ) : (
                                                                                <div className="w-12 h-12 object-contain rounded-lg bg-white flex items-center justify-center">
                                                                                    <PlaceholderIcon />
                                                                                </div>
                                                                            )}
                                                                            <div>
                                                                                <p className="font-semibold">
                                                                                    {
                                                                                        item.product_name
                                                                                    }
                                                                                </p>
                                                                                <p>
                                                                                    SKU:{' '}
                                                                                    <span className="text-sky-500">
                                                                                        {
                                                                                            item.sku
                                                                                        }
                                                                                    </span>
                                                                                </p>
                                                                                <p>
                                                                                    Square
                                                                                    product
                                                                                    ID:{' '}
                                                                                    <span
                                                                                        className={`${item
                                                                                            .square_product_id
                                                                                            .length >
                                                                                            0
                                                                                            ? 'text-sky-500'
                                                                                            : 'text-red-500'
                                                                                            }`}
                                                                                    >
                                                                                        {item
                                                                                            .square_product_id
                                                                                            .length >
                                                                                            0
                                                                                            ? item.square_product_id
                                                                                            : 'Not Linked'}
                                                                                    </span>
                                                                                </p>
                                                                                <p>
                                                                                    Price:
                                                                                    $
                                                                                    {
                                                                                        item.price
                                                                                    }{' '}
                                                                                    x{' '}
                                                                                    {
                                                                                        item.quantity
                                                                                    }{' '}
                                                                                    |
                                                                                    Total
                                                                                    cost:
                                                                                    $
                                                                                    {
                                                                                        item.total
                                                                                    }
                                                                                </p>
                                                                            </div>
                                                                        </li>
                                                                    );
                                                                }
                                                            )}
                                                        </ul>
                                                    </div>
                                                    <div className="md:col-span-6">
                                                        <p className="font-semibold text-lg mb-4">
                                                            Order Totals
                                                        </p>
                                                        <ul className="w-fulldivide-y divide-slate-100">
                                                            <li className="flex justify-between">
                                                                Subtotal:{' '}
                                                                <strong>
                                                                    $
                                                                    {row.original.order_subtotal.toFixed(
                                                                        2
                                                                    )}
                                                                </strong>
                                                            </li>
                                                            <li className="flex justify-between">
                                                                Discount Total:{' '}
                                                                <strong>
                                                                    -$
                                                                    {
                                                                        row.original
                                                                            .discount_total
                                                                    }
                                                                </strong>
                                                            </li>
                                                            <li className="flex justify-between">
                                                                Shipping Total:{' '}
                                                                <strong>
                                                                    $
                                                                    {
                                                                        row.original
                                                                            .shipping_total
                                                                    }
                                                                </strong>
                                                            </li>
                                                            <li className="flex justify-between">
                                                                Total Tax:{' '}
                                                                <strong>
                                                                    $
                                                                    {
                                                                        row.original
                                                                            .total_tax
                                                                    }
                                                                </strong>
                                                            </li>
                                                            <li className="flex justify-between">
                                                                Total:{' '}
                                                                <strong>
                                                                    $
                                                                    {
                                                                        row.original
                                                                            .total
                                                                    }
                                                                </strong>
                                                            </li>
                                                        </ul>
                                                        <p className="font-semibold text-lg mb-4 mt-8">
                                                            Customer Details
                                                        </p>
                                                        <ul className="divide-y divide-slate-100">
                                                            {
                                                                row.original
                                                                    .customer && Object.keys(row.original
                                                                        .customer).length > 0 ? Object.keys(row.original
                                                                            .customer).map(key => {
                                                                                return (
                                                                                    <>
                                                                                        {row.original.customer[key] && (
                                                                                            <li key={row.original.customer[key]} className="grid grid-cols-2">
                                                                                                <span className='capitalize'>{key.replace('_', ' ')}:</span>{' '}
                                                                                                <span className="text-left font-bold">{row.original.customer[key]}</span>
                                                                                            </li>
                                                                                        )}
                                                                                    </>
                                                                                )
                                                                            }) : (<p>Guest Customer</p>)
                                                            }
                                                        </ul>
                                                    </div>
                                                    <div className="md:col-span-full">
                                                        <p className="font-semibold text-lg mb-4">
                                                            Square Order Details
                                                        </p>
                                                        {!row.original.square_data ? (
                                                            <p>
                                                                Sync this order with
                                                                Square to view orders
                                                                details provided by
                                                                Square
                                                            </p>
                                                        ) : (
                                                            <div className="flex justify-start gap-20 items-start">
                                                                <div>
                                                                    <p className="text-base font-semibold">
                                                                        Order details:
                                                                    </p>
                                                                    <p>
                                                                        Order ID:{' '}
                                                                        <span className="font-semibold">
                                                                            {
                                                                                getValueFromJson(
                                                                                    row
                                                                                        .original
                                                                                        .square_data
                                                                                )[
                                                                                'order'
                                                                                ][
                                                                                'data'
                                                                                ][
                                                                                'order'
                                                                                ][
                                                                                'id'
                                                                                ]
                                                                            }
                                                                        </span>
                                                                    </p>
                                                                    <p>
                                                                        Ticket name:{' '}
                                                                        <span className="font-semibold">
                                                                            {
                                                                                getValueFromJson(
                                                                                    row
                                                                                        .original
                                                                                        .square_data
                                                                                )[
                                                                                'order'
                                                                                ][
                                                                                'data'
                                                                                ][
                                                                                'order'
                                                                                ][
                                                                                'ticket_name'
                                                                                ]
                                                                            }
                                                                        </span>
                                                                    </p>
                                                                    <a
                                                                        href={`https://squareup.com/dashboard/orders/overview/${getValueFromJson(
                                                                            row
                                                                                .original
                                                                                .square_data
                                                                        )[
                                                                            'order'
                                                                        ]['data'][
                                                                            'order'
                                                                        ]['id']
                                                                            }`}
                                                                        target="_blank"
                                                                        className="text-sky-500"
                                                                    >
                                                                        View order
                                                                    </a>
                                                                </div>
                                                                <div>
                                                                    {getValueFromJson(
                                                                                        row
                                                                                            .original
                                                                                            .square_data
                                                                                    )[
                                                                                    'payment'
                                                                                    ] && getValueFromJson(
                                                                                        row
                                                                                            .original
                                                                                            .square_data
                                                                                    )[
                                                                                    'payment'
                                                                                    ]['data'] && <> <p className="text-base font-semibold">
                                                                        Payment Details:
                                                                    </p>
                                                                        <p>
                                                                            Payment ID:{' '}
                                                                            <span className="font-semibold">
                                                                                {
                                                                                    getValueFromJson(
                                                                                        row
                                                                                            .original
                                                                                            .square_data
                                                                                    )[
                                                                                    'payment'
                                                                                    ][
                                                                                    'data'
                                                                                    ][
                                                                                    'payment'
                                                                                    ][
                                                                                    'id'
                                                                                    ]
                                                                                }
                                                                            </span>
                                                                        </p>
                                                                        <p>
                                                                            Receipt Number:{' '}
                                                                            <span className="font-semibold">
                                                                                {
                                                                                    getValueFromJson(
                                                                                        row
                                                                                            .original
                                                                                            .square_data
                                                                                    )[
                                                                                    'payment'
                                                                                    ][
                                                                                    'data'
                                                                                    ][
                                                                                    'payment'
                                                                                    ][
                                                                                    'receipt_number'
                                                                                    ]
                                                                                }
                                                                            </span>
                                                                        </p>
                                                                        <a
                                                                            href={
                                                                                getValueFromJson(
                                                                                    row
                                                                                        .original
                                                                                        .square_data
                                                                                )[
                                                                                'payment'
                                                                                ]['data'][
                                                                                'payment'
                                                                                ][
                                                                                'receipt_url'
                                                                                ]
                                                                            }
                                                                            target="_blank"
                                                                            className="text-sky-500"
                                                                        >
                                                                            View receipt
                                                                        </a></>}

                                                                </div>
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    )}


                                </Fragment>
                            )
                        }
                    })}
                </tbody>
            </table>
            <hr />
            <div className="py-4">
                <PaginationControls table={table} />
            </div>
        </>
    );
}

export default Table;
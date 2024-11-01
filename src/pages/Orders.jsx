import { useEffect, useState } from '@wordpress/element';
import { useDispatch, useSelector } from 'react-redux';
import { fetchOrders } from '../redux/ordersSlice';
import { ArrowPathIcon } from '@heroicons/react/20/solid';
import TableHeader from '../components/features/orders/TableHeader';
import Table from '../components/features/orders/Table';
import InvLoading from '../components/features/orders/InvLoading';
import useMenuFix from '../components/hooks/useMenuFix';


export default function Orders() {
	useMenuFix();

	const dispatch = useDispatch();
	const { data, loading, error } = useSelector( ( state ) => state.orders );

	useEffect( () => {
		dispatch( fetchOrders() );
	}, [ dispatch ] );

	return (
		<>
			<div className="bg-white p-6 rounded-t-xl not-prose border-b">
				<header className="mb-2 col-span-full flex flex-col text-center">

					<h1 className="text-3xl tracking-tight text-slate-900 font-bold flex items-center gap-2 justify-center">
						Orders
					</h1>
				</header>
			</div>
			<div className="flex  justify-between divide-x">
				<div className="bg-white rounded-bl-xl overflow-hidden p-6 w-6/12">
					<h2 className="font-semibold text-xl text-sky-500">
						How it works
					</h2>
					<p className="max-w-lg text-left text-base mt-2">
						Integrating your orders with Square seamlessly generates
						both a transaction and a customer profile. For orders
						that require fulfillment, such as shipping, they will
						automatically appear on Square's Orders page. This
						integration allows you to efficiently manage and fulfill
						your orders directly within Square.
					</p>
				</div>
				<div className="bg-white rounded-br-xl overflow-hidden p-6 w-6/12">
					<h2 className="font-semibold text-xl text-sky-500">
						Getting started
					</h2>
					<ol className="list-decimal ml-4 text-base mt-2">
						<li>
							Use the table below and find the order you'd like to
							sync with Square. Only processing and completed
							orders can be synced to Square.
						</li>
						<li>
							Click on View details and then click Sync to Square
						</li>
						<li>
							Your order will be synced to Square and will display
							Squares order details once successful.
						</li>
					</ol>
				</div>
			</div>

			<div className="bg-white rounded-xl overflow-hidden mt-6">
				{ loading && <InvLoading /> }
				{ ! loading && ! error && (
					<div className="sm:px-6 px-4">
						{ data && data.length > 0 ? (
							<Table data={ data.filter(order => order !== null) }  />
						) : (
							<div>No orders found.</div>
						) }
					</div>
				) }
				{ ! loading && error && (
					<div className="sm:px-6 px-4 py-5">
						Unable to fetch orders: { error }
					</div>
				) }
			</div>
		</>
	);
}
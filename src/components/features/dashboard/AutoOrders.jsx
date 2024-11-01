import { Link } from "react-router-dom";

export default function AutoOrders({ orders }) {
    return (
        <section className="bg-white rounded-xl p-4 w-full">
            <header className="flex flex-col items-between flex-start gap-2 relative w-full text-black">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" strokeLinecap="round" strokeLinejoin="round" class="w-5 h-5 text-black"><line x1="12" y1="1" x2="12" y2="23" /><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" /></svg>
                <h3 className="text-base font-semibold leading-6 text-gray-900">
                    Auto sync of orders, transactions and customers is {orders && orders.enabled ? 'on' : 'off'}
                </h3>
                {orders && orders.enabled ?
                    <div className="absolute top-1 right-0">
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                        </span>
                    </div> :
                    <div className="absolute top-1 right-0">
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                        </span>
                    </div>
                }
            </header>
            {orders && orders.enabled ? <div className="mt-2">
                <p className="mt-3 text-gray-500 ">When a new order is created with a status of <span className="text-sky-500">"{orders.stage}"</span>, a corresponding order, transaction and customer will be created in Square.</p>

            </div> : <p className="text-gray-500 mt-2">Auto orders, transactions and customer sync to Square is currently disabled. To enable, to go the order settings <Link to={'/settings/orders'} className="text-sky-500">here</Link>.</p>}

        </section>
    );
}

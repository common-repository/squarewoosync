import { Link } from "react-router-dom";

export default function SquareAuto({ squareAuto }) {
    return (
        <section className="bg-white rounded-xl p-4 w-full">
            <header className="flex flex-col items-between flex-start gap-2 relative w-full">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" class="w-5 h-5"><polyline points="17 1 21 5 17 9" /><path d="M3 11V9a4 4 0 0 1 4-4h14" /><polyline points="7 23 3 19 7 15" /><path d="M21 13v2a4 4 0 0 1-4 4H3" /></svg>
                <h3 className="text-base font-semibold leading-6 text-gray-900">
                    Real-time automatic sync from Square to Woo is {squareAuto && squareAuto.isActive ? 'on' : 'off'}
                </h3>
                {squareAuto && squareAuto.isActive ?
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
            {squareAuto && squareAuto.isActive ? <div className="mt-2">
                <p className="mt-3 text-gray-500 ">The following data will be synced:</p>
                <p className="mt-px text-gray-500 ">
                    {Object.keys(squareAuto)
                        .filter((key) => key === 'stock' || key === 'title' || key === 'description' || key === 'sku' || key === 'images' || key === 'category' || key === 'price')
                        .map((key, idx, filteredKeys) => (
                            <span key={key}>
                                <span className="text-sky-500">{key}</span>
                                {idx !== filteredKeys.length - 1 ? ", " : ""}
                            </span>
                        ))}
                </p>
            </div> : <p className="text-gray-500 mt-2">Real-time automatical sync from Square to Woocommerce is currently disabled. To enable, go to inventory settings <Link to={'/settings/inventory'} className="text-sky-500">here</Link>.</p>}

        </section>
    );
}

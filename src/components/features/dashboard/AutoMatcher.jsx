import { Link } from "react-router-dom";

export default function AutoMatcher({ wooAuto }) {
    return (
        <section className="bg-white rounded-xl p-4 w-full">
            <header className="flex flex-col items-between flex-start gap-2 relative w-full">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" class="w-5 h-5 text-black "><rect x="3" y="3" width="18" height="18" rx="2" ry="2" /><line x1="12" y1="8" x2="12" y2="16" /><line x1="8" y1="12" x2="16" y2="12" /></svg>
                <h3 className="text-base font-semibold leading-6 text-gray-900">
                    Auto product creation from Woo to Square is {wooAuto && wooAuto.autoCreateProduct ? 'on' : 'off'}
                </h3>
                {wooAuto && wooAuto.autoCreateProduct ?
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
            {wooAuto && wooAuto.autoCreateProduct ? <div className="mt-2">
                <p className="mt-3 text-gray-500 ">When a new product is created in WooCommerce, a corresponding product will automatically be created and linked in Square, ready for auto-syncing.</p>

            </div> : <p className="text-gray-500 mt-2">Auto creations is currently disabled, to enable head to product settings <Link to={'/settings/inventory'} className="text-sky-500">here</Link>.</p>}

        </section>
    );
}

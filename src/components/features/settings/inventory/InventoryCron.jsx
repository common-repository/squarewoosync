export default function InventoryCron() {
    return (
        <div className="px-4 pb-5 sm:px-6">
            <h3 className="text-base font-semibold leading-6 text-gray-900">
                Sync on Interval
            </h3>
            <div className="mt-2 max-w-xl text-sm text-gray-500 mb-4">
                <p>
                    Enable or disable syncing products between Square and Woo on a re-occuring interval
                    <br></br>
                    <a
                        href="https://squaresyncforwoo.com/documentation#import-data"
                        className="underline text-sky-500"
                        target="_blank"
                    >
                        How to setup and control automatic syncing between
                        Square and Woo
                    </a>
                </p>
            </div>
        </div>
    )
}
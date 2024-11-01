const Webhook = () => {
	return (
		<div className="px-6 pb-10">
			<h3 className="text-base font-semibold leading-6 text-gray-900">
				Webhook URL <a className="pro-badge !relative" href="https://squaresyncforwoo.com" target="_blank">PRO ONLY</a>
			</h3>
			<div className="mt-2 max-w-xl text-sm text-gray-500">
				<p>
					The webhook URL is used to keep a live inventory sync
					between square and woocommerce. Copy this URL and paste it
					into your square developer webhook subscriptions. Read the
					following documentation on how to do this:
					<br></br>
					<a
						href="https://squaresyncforwoo.com/documentation#webhook"
						target="_blank"
						className="underline text-sky-500"
					>
						Setting up webhook url to retrieve inventory updates
					</a>
				</p>
			</div>
			<div className="max-w-xl flex items-center mt-4 blur">
				<input
					disabled
					id="webhookURL"

					name="webhookURL"
					className="block disabled:text-gray-700 w-full !rounded-lg !border-0 !py-1.5 text-gray-900 !ring-1 !ring-inset !ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-sky-600 sm:text-sm !px-4 !leading-6"
					value={`https://test/wp-json/sws/lorem-ispum`}
				/>
				<button
					type="button"

					className="mt-3 inline-flex w-full items-center justify-center rounded-md bg-sky-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600 sm:ml-3 sm:mt-0 sm:w-auto"
				>
					Copy
				</button>
			</div>
		</div>
	);
};

export default Webhook;

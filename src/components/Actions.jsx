export default function Actions() {
	return (
		<div className="bg-white p-6 rounded-xl not-prose grid grid-cols-1 gap-6 sm:grid-cols-2 w-full">
			<header className="mb-2 col-span-full flex flex-col">
				<p className=" text-sm font-medium text-sky-500">
					Introduction
				</p>
				<h1 className="text-3xl tracking-tight text-slate-900 font-bold ">
					Getting started
				</h1>
				<p className="text-xl text-gray-600 mt-2">
					Welcome to SquareSync for Woocommerce. See below to learn how to start
					importing and syncronizing products with Square and Woo.
				</p>
			</header>
			<div className="group relative rounded-xl border border-slate-400 ">
				<div className="absolute -inset-px rounded-xl border-2 border-transparent opacity-0 [background:linear-gradient(var(--quick-links-hover-bg,theme(colors.sky.50)),var(--quick-links-hover-bg,theme(colors.sky.50)))_padding-box,linear-gradient(to_top,theme(colors.sky.400),theme(colors.cyan.400),theme(colors.sky.500))_border-box] group-hover:opacity-100 "></div>
				<div className="relative overflow-hidden rounded-xl p-6">
					<svg
						aria-hidden="true"
						viewBox="0 0 32 32"
						fill="none"
						className="h-8 w-8 [--icon-foreground:theme(colors.slate.900)] [--icon-background:theme(colors.white)]"
					>
						<defs>
							<radialGradient
								cx="0"
								cy="0"
								r="1"
								gradientUnits="userSpaceOnUse"
								id=":S3:-gradient"
								gradientTransform="matrix(0 21 -21 0 20 11)"
							>
								<stop stopColor="#0EA5E9"></stop>
								<stop stopColor="#22D3EE" offset=".527"></stop>
								<stop stopColor="#818CF8" offset="1"></stop>
							</radialGradient>
							<radialGradient
								cx="0"
								cy="0"
								r="1"
								gradientUnits="userSpaceOnUse"
								id=":S3:-gradient-dark-1"
								gradientTransform="matrix(0 22.75 -22.75 0 16 6.25)"
							>
								<stop stopColor="#0EA5E9"></stop>
								<stop stopColor="#22D3EE" offset=".527"></stop>
								<stop stopColor="#818CF8" offset="1"></stop>
							</radialGradient>
							<radialGradient
								cx="0"
								cy="0"
								r="1"
								gradientUnits="userSpaceOnUse"
								id=":S3:-gradient-dark-2"
								gradientTransform="matrix(0 14 -14 0 16 10)"
							>
								<stop stopColor="#0EA5E9"></stop>
								<stop stopColor="#22D3EE" offset=".527"></stop>
								<stop stopColor="#818CF8" offset="1"></stop>
							</radialGradient>
						</defs>
						<g className="">
							<circle
								cx="20"
								cy="20"
								r="12"
								fill="url(#:S3:-gradient)"
							></circle>
							<g
								fillOpacity="0.5"
								className="fill-[var(--icon-background)] stroke-[color:var(--icon-foreground)]"
								strokeWidth="2"
								strokeLinecap="round"
								strokeLinejoin="round"
							>
								<path d="M3 9v14l12 6V15L3 9Z"></path>
								<path d="M27 9v14l-12 6V15l12-6Z"></path>
							</g>
							<path
								d="M11 4h8v2l6 3-10 6L5 9l6-3V4Z"
								fillOpacity="0.5"
								className="fill-[var(--icon-background)]"
							></path>
							<g
								className="stroke-[color:var(--icon-foreground)]"
								strokeWidth="2"
								strokeLinecap="round"
								strokeLinejoin="round"
							>
								<path d="M20 5.5 27 9l-12 6L3 9l7-3.5"></path>
								<path d="M20 5c0 1.105-2.239 2-5 2s-5-.895-5-2m10 0c0-1.105-2.239-2-5-2s-5 .895-5 2m10 0v3c0 1.105-2.239 2-5 2s-5-.895-5-2V5"></path>
							</g>
						</g>
						<g
							className="hidden "
							strokeWidth="2"
							strokeLinecap="round"
							strokeLinejoin="round"
						>
							<path
								d="M17.676 3.38a3.887 3.887 0 0 0-3.352 0l-9 4.288C3.907 8.342 3 9.806 3 11.416v9.168c0 1.61.907 3.073 2.324 3.748l9 4.288a3.887 3.887 0 0 0 3.352 0l9-4.288C28.093 23.657 29 22.194 29 20.584v-9.168c0-1.61-.907-3.074-2.324-3.748l-9-4.288Z"
								stroke="url(#:S3:-gradient-dark-1)"
							></path>
							<path
								d="M16.406 8.087a.989.989 0 0 0-.812 0l-7 3.598A1.012 1.012 0 0 0 8 12.61v6.78c0 .4.233.762.594.925l7 3.598a.989.989 0 0 0 .812 0l7-3.598c.361-.163.594-.525.594-.925v-6.78c0-.4-.233-.762-.594-.925l-7-3.598Z"
								fill="url(#:S3:-gradient-dark-2)"
								stroke="url(#:S3:-gradient-dark-2)"
							></path>
						</g>
					</svg>
					<h2 className="mt-4  text-base text-slate-900 ">
						<a href="/wp-admin/admin.php?page=squarewoosync#/inventory">
							<span className="absolute -inset-px rounded-xl"></span>
							Start a new import
						</a>
					</h2>
					<p className="mt-1 text-sm text-slate-700 ">
						Click here to begin importing or syncronizing products
						from Square to Woo
					</p>
				</div>
			</div>
			<div className="group relative rounded-xl border border-slate-400 ">
				<div className="absolute -inset-px rounded-xl border-2 border-transparent opacity-0 [background:linear-gradient(var(--quick-links-hover-bg,theme(colors.sky.50)),var(--quick-links-hover-bg,theme(colors.sky.50)))_padding-box,linear-gradient(to_top,theme(colors.sky.400),theme(colors.cyan.400),theme(colors.sky.500))_border-box] group-hover:opacity-100 "></div>
				<div className="relative overflow-hidden rounded-xl p-6">
					<svg
						aria-hidden="true"
						viewBox="0 0 32 32"
						fill="none"
						className="h-8 w-8 [--icon-foreground:theme(colors.slate.900)] [--icon-background:theme(colors.white)]"
					>
						<defs>
							<radialGradient
								cx="0"
								cy="0"
								r="1"
								gradientUnits="userSpaceOnUse"
								id=":S1:-gradient"
								gradientTransform="matrix(0 21 -21 0 12 3)"
							>
								<stop stopColor="#0EA5E9"></stop>
								<stop stopColor="#22D3EE" offset=".527"></stop>
								<stop stopColor="#818CF8" offset="1"></stop>
							</radialGradient>
							<radialGradient
								cx="0"
								cy="0"
								r="1"
								gradientUnits="userSpaceOnUse"
								id=":S1:-gradient-dark"
								gradientTransform="matrix(0 21 -21 0 16 7)"
							>
								<stop stopColor="#0EA5E9"></stop>
								<stop stopColor="#22D3EE" offset=".527"></stop>
								<stop stopColor="#818CF8" offset="1"></stop>
							</radialGradient>
						</defs>
						<g className="">
							<circle
								cx="12"
								cy="12"
								r="12"
								fill="url(#:S1:-gradient)"
							></circle>
							<path
								d="m8 8 9 21 2-10 10-2L8 8Z"
								fillOpacity="0.5"
								className="fill-[var(--icon-background)] stroke-[color:var(--icon-foreground)]"
								strokeWidth="2"
								strokeLinecap="round"
								strokeLinejoin="round"
							></path>
						</g>
						<g className="hidden ">
							<path
								d="m4 4 10.286 24 2.285-11.429L28 14.286 4 4Z"
								fill="url(#:S1:-gradient-dark)"
								stroke="url(#:S1:-gradient-dark)"
								strokeWidth="2"
								strokeLinecap="round"
								strokeLinejoin="round"
							></path>
						</g>
					</svg>
					<h2 className="mt-4  text-base text-slate-900 ">
						<a
							href="https://squaresyncforwoo.com/documentation"
							target="_blank"
						>
							<span className="absolute -inset-px rounded-xl"></span>
							Installation
						</a>
					</h2>
					<p className="mt-1 text-sm text-slate-700 ">
						Step-by-step guides to setting up your Square account
						and Woo to talk to each other
					</p>
				</div>
			</div>
			<div className="group relative rounded-xl border border-slate-400 ">
				<div className="absolute -inset-px rounded-xl border-2 border-transparent opacity-0 [background:linear-gradient(var(--quick-links-hover-bg,theme(colors.sky.50)),var(--quick-links-hover-bg,theme(colors.sky.50)))_padding-box,linear-gradient(to_top,theme(colors.sky.400),theme(colors.cyan.400),theme(colors.sky.500))_border-box] group-hover:opacity-100 "></div>
				<div className="relative overflow-hidden rounded-xl p-6">
					<svg
						aria-hidden="true"
						viewBox="0 0 32 32"
						fill="none"
						className="h-8 w-8 [--icon-foreground:theme(colors.slate.900)] [--icon-background:theme(colors.white)]"
					>
						<defs>
							<radialGradient
								cx="0"
								cy="0"
								r="1"
								gradientUnits="userSpaceOnUse"
								id=":S2:-gradient"
								gradientTransform="matrix(0 21 -21 0 20 3)"
							>
								<stop stopColor="#0EA5E9"></stop>
								<stop stopColor="#22D3EE" offset=".527"></stop>
								<stop stopColor="#818CF8" offset="1"></stop>
							</radialGradient>
							<radialGradient
								cx="0"
								cy="0"
								r="1"
								gradientUnits="userSpaceOnUse"
								id=":S2:-gradient-dark"
								gradientTransform="matrix(0 22.75 -22.75 0 16 6.25)"
							>
								<stop stopColor="#0EA5E9"></stop>
								<stop stopColor="#22D3EE" offset=".527"></stop>
								<stop stopColor="#818CF8" offset="1"></stop>
							</radialGradient>
						</defs>
						<g className="">
							<circle
								cx="20"
								cy="12"
								r="12"
								fill="url(#:S2:-gradient)"
							></circle>
							<g
								className="fill-[var(--icon-background)] stroke-[color:var(--icon-foreground)]"
								fillOpacity="0.5"
								strokeWidth="2"
								strokeLinecap="round"
								strokeLinejoin="round"
							>
								<path d="M3 5v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2Z"></path>
								<path d="M18 17v10a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2V17a2 2 0 0 0-2-2h-7a2 2 0 0 0-2 2Z"></path>
								<path d="M18 5v4a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-7a2 2 0 0 0-2 2Z"></path>
								<path d="M3 25v2a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2v-2a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2Z"></path>
							</g>
						</g>
						<g className="hidden " fill="url(#:S2:-gradient-dark)">
							<path
								fillRule="evenodd"
								clipRule="evenodd"
								d="M3 17V4a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1Zm16 10v-9a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-6a2 2 0 0 1-2-2Zm0-23v5a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1h-8a1 1 0 0 0-1 1ZM3 28v-3a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1Z"
							></path>
							<path d="M2 4v13h2V4H2Zm2-2a2 2 0 0 0-2 2h2V2Zm8 0H4v2h8V2Zm2 2a2 2 0 0 0-2-2v2h2Zm0 13V4h-2v13h2Zm-2 2a2 2 0 0 0 2-2h-2v2Zm-8 0h8v-2H4v2Zm-2-2a2 2 0 0 0 2 2v-2H2Zm16 1v9h2v-9h-2Zm3-3a3 3 0 0 0-3 3h2a1 1 0 0 1 1-1v-2Zm6 0h-6v2h6v-2Zm3 3a3 3 0 0 0-3-3v2a1 1 0 0 1 1 1h2Zm0 9v-9h-2v9h2Zm-3 3a3 3 0 0 0 3-3h-2a1 1 0 0 1-1 1v2Zm-6 0h6v-2h-6v2Zm-3-3a3 3 0 0 0 3 3v-2a1 1 0 0 1-1-1h-2Zm2-18V4h-2v5h2Zm0 0h-2a2 2 0 0 0 2 2V9Zm8 0h-8v2h8V9Zm0 0v2a2 2 0 0 0 2-2h-2Zm0-5v5h2V4h-2Zm0 0h2a2 2 0 0 0-2-2v2Zm-8 0h8V2h-8v2Zm0 0V2a2 2 0 0 0-2 2h2ZM2 25v3h2v-3H2Zm2-2a2 2 0 0 0-2 2h2v-2Zm9 0H4v2h9v-2Zm2 2a2 2 0 0 0-2-2v2h2Zm0 3v-3h-2v3h2Zm-2 2a2 2 0 0 0 2-2h-2v2Zm-9 0h9v-2H4v2Zm-2-2a2 2 0 0 0 2 2v-2H2Z"></path>
						</g>
					</svg>
					<h2 className="mt-4  text-base text-slate-900 ">
						<a
							href="https://squaresyncforwoo.com/documentation#import-data"
							target="_blank"
						>
							<span className="absolute -inset-px rounded-xl"></span>
							Controlling your import data
						</a>
					</h2>
					<p className="mt-1 text-sm text-slate-700 ">
						Learn how the internals work and how you can choose
						which data you would like to sync.
					</p>
				</div>
			</div>

			<div className="group relative rounded-xl border border-slate-400 ">
				<div className="absolute -inset-px rounded-xl border-2 border-transparent opacity-0 [background:linear-gradient(var(--quick-links-hover-bg,theme(colors.sky.50)),var(--quick-links-hover-bg,theme(colors.sky.50)))_padding-box,linear-gradient(to_top,theme(colors.sky.400),theme(colors.cyan.400),theme(colors.sky.500))_border-box] group-hover:opacity-100 "></div>
				<div className="relative overflow-hidden rounded-xl p-6">
					<svg
						aria-hidden="true"
						viewBox="0 0 32 32"
						fill="none"
						className="h-8 w-8 [--icon-foreground:theme(colors.slate.900)] [--icon-background:theme(colors.white)]"
					>
						<defs>
							<radialGradient
								cx="0"
								cy="0"
								r="1"
								gradientUnits="userSpaceOnUse"
								id=":S4:-gradient"
								gradientTransform="matrix(0 21 -21 0 12 11)"
							>
								<stop stopColor="#0EA5E9"></stop>
								<stop stopColor="#22D3EE" offset=".527"></stop>
								<stop stopColor="#818CF8" offset="1"></stop>
							</radialGradient>
							<radialGradient
								cx="0"
								cy="0"
								r="1"
								gradientUnits="userSpaceOnUse"
								id=":S4:-gradient-dark"
								gradientTransform="matrix(0 24.5 -24.5 0 16 5.5)"
							>
								<stop stopColor="#0EA5E9"></stop>
								<stop stopColor="#22D3EE" offset=".527"></stop>
								<stop stopColor="#818CF8" offset="1"></stop>
							</radialGradient>
						</defs>
						<g className="">
							<circle
								cx="12"
								cy="20"
								r="12"
								fill="url(#:S4:-gradient)"
							></circle>
							<path
								d="M27 12.13 19.87 5 13 11.87v14.26l14-14Z"
								className="fill-[var(--icon-background)] stroke-[color:var(--icon-foreground)]"
								fillOpacity="0.5"
								strokeWidth="2"
								strokeLinecap="round"
								strokeLinejoin="round"
							></path>
							<path
								d="M3 3h10v22a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V3Z"
								className="fill-[var(--icon-background)]"
								fillOpacity="0.5"
							></path>
							<path
								d="M3 9v16a4 4 0 0 0 4 4h2a4 4 0 0 0 4-4V9M3 9V3h10v6M3 9h10M3 15h10M3 21h10"
								className="stroke-[color:var(--icon-foreground)]"
								strokeWidth="2"
								strokeLinecap="round"
								strokeLinejoin="round"
							></path>
							<path
								d="M29 29V19h-8.5L13 26c0 1.5-2.5 3-5 3h21Z"
								fillOpacity="0.5"
								className="fill-[var(--icon-background)] stroke-[color:var(--icon-foreground)]"
								strokeWidth="2"
								strokeLinecap="round"
								strokeLinejoin="round"
							></path>
						</g>
						<g className="hidden ">
							<path
								fillRule="evenodd"
								clipRule="evenodd"
								d="M3 2a1 1 0 0 0-1 1v21a6 6 0 0 0 12 0V3a1 1 0 0 0-1-1H3Zm16.752 3.293a1 1 0 0 0-1.593.244l-1.045 2A1 1 0 0 0 17 8v13a1 1 0 0 0 1.71.705l7.999-8.045a1 1 0 0 0-.002-1.412l-6.955-6.955ZM26 18a1 1 0 0 0-.707.293l-10 10A1 1 0 0 0 16 30h13a1 1 0 0 0 1-1V19a1 1 0 0 0-1-1h-3ZM5 18a1 1 0 1 0 0 2h6a1 1 0 1 0 0-2H5Zm-1-5a1 1 0 0 1 1-1h6a1 1 0 1 1 0 2H5a1 1 0 0 1-1-1Zm1-7a1 1 0 0 0 0 2h6a1 1 0 1 0 0-2H5Z"
								fill="url(#:S4:-gradient-dark)"
							></path>
						</g>
					</svg>
					<h2 className="mt-4  text-base text-slate-900 ">
						<a href="/wp-admin/admin.php?page=squarewoosync#/settings/general">
							<span className="absolute -inset-px rounded-xl"></span>
							Settings
						</a>
					</h2>
					<p className="mt-1 text-sm text-slate-700 ">
						Manage your access token, import data and webhook url
						for automatic synchronization
					</p>
				</div>
			</div>
		</div>
	);
}

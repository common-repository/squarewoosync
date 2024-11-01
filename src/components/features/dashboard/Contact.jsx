import {
	BugAntIcon,
	ChatBubbleLeftRightIcon,
	ComputerDesktopIcon,
} from '@heroicons/react/24/outline';

export default function Contact() {
	return (
		<div className="isolate bg-white p-5 rounded-xl">
			<div className="">
				<h3 className="text-base font-semibold leading-6 text-gray-900 ">
					Support
				</h3>
				<p className="leading-8 text-gray-600"></p>
			</div>
			<div className="mt-3 space-y-4">
				<div className="flex gap-x-4">
					<div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sky-600">
						<ChatBubbleLeftRightIcon
							className="h-6 w-6 text-white"
							aria-hidden="true"
						/>
					</div>
					<div>
						<h3 className="text-sm font-semibold  text-gray-900">
							Sales/License support
						</h3>
						<p className="  text-gray-600">
							Wish to talk to us about your licence or have
							another questions related to sales?
						</p>
						<p className="">
							<a
								href="https://squaresyncforwoo.com/my-account/support-portal/"
								target="_blank"
								className="text-sm font-semibold  text-sky-600"
							>
								Contact us{ ' ' }
								<span aria-hidden="true">&rarr;</span>
							</a>
						</p>
					</div>
				</div>
				<div className="flex gap-x-4">
					<div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sky-600">
						<BugAntIcon
							className="h-6 w-6 text-white"
							aria-hidden="true"
						/>
					</div>
					<div>
						<h3 className="text-sm font-semibold  text-gray-900">
							Bug reports
						</h3>
						<p className="  text-gray-600">
							Found a bug? Let us know so we can jump on it right
							away! And thank you for your help!
						</p>
						<p className="">
							<a
								href="https://squaresyncforwoo.com/my-account/support-portal/"
								target="_blank"
								className="text-sm font-semibold leading-6 text-sky-600"
							>
								Report a bug{ ' ' }
								<span aria-hidden="true">&rarr;</span>
							</a>
						</p>
					</div>
				</div>
				<div className="flex gap-x-4">
					<div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sky-600">
						<ComputerDesktopIcon
							className="h-6 w-6 text-white"
							aria-hidden="true"
						/>
					</div>
					<div>
						<h3 className="text-sm font-semibold  text-gray-900">
							Technical support
						</h3>
						<p className="  text-gray-600">
							Can't figure out how to setup this plugin or having
							another technical issue? Let us know and we would be
							glad to assist you.
						</p>
						<p className="">
							<a
								href="https://squaresyncforwoo.com/my-account/support-portal/"
								target="_blank"
								className="text-sm font-semibold  text-sky-600"
							>
								Contact us{ ' ' }
								<span aria-hidden="true">&rarr;</span>
							</a>
						</p>
					</div>
				</div>
			</div>
		</div>
	);
}

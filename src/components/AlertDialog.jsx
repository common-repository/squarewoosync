import { useState, Fragment, useRef } from '@wordpress/element';
import { Dialog } from '@headlessui/react';

const withAlertDialog = ( WrappedComponent ) => {
	return ( { title, description, onConfirm, ...props } ) => {
		const [ isOpen, setIsOpen ] = useState( false );
		const initialFocusRef = useRef( null );

		const closeDialog = () => {
			setIsOpen( false );
		};

		const handleConfirm = () => {
			if ( onConfirm ) {
				onConfirm();
			}
			closeDialog();
		};

		return (
			<>
				<Dialog
					open={ isOpen }
					as="div"
					onClose={ closeDialog }
					className="relative z-[99999]"
					initialFocus={ initialFocusRef }
				>
					{ /* The backdrop, rendered as a fixed sibling to the panel container */ }
					<div
						className="fixed inset-0 bg-black/70"
						aria-hidden="true"
					/>
					{ /* Full-screen container to center the panel */ }
					<div className="fixed inset-0 flex w-screen items-center justify-center p-4">
						<Dialog.Panel className="bg-white rounded-lg max-w-lg mx-auto p-6 transform shadow-xl transition-all">
							<Dialog.Title
								as="h3"
								className="text-lg font-medium leading-6 text-gray-900"
							>
								{ title }
							</Dialog.Title>
							<Dialog.Description
								className="mt-2 text-gray-500 text-sm"
								as="p"
							>
								{ description }
							</Dialog.Description>
							<div className="flex gap-2 items-center mt-4 justify-end">
								<button
									onClick={ closeDialog }
									ref={ initialFocusRef }
									className="inline-flex justify-center rounded-md border border-transparent bg-gray-100 px-4 py-2 text-sm font-medium text-gray-900 hover:bg-gray-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-500 focus-visible:ring-offset-2"
								>
									No
								</button>
								<button
									type="button"
									onClick={ handleConfirm }
									className="inline-flex justify-center rounded-md border border-transparent bg-blue-100 px-4 py-2 text-sm font-medium text-blue-900 hover:bg-blue-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
								>
									Yes
								</button>
							</div>
						</Dialog.Panel>
					</div>
				</Dialog>
				<WrappedComponent setIsOpen={ setIsOpen } { ...props } />
			</>
		);
	};
};

export default withAlertDialog;

import { useState, Fragment, useRef } from '@wordpress/element';
import { Dialog } from '@headlessui/react';
import { classNames } from '../utils/classHelper';

const DialogWrapper = ( {
	children,
	open,
	onClose,
	className,
	backdrop = true,
} ) => {
	const dialogRef = useRef( null );

	if ( ! open ) return null;

	return (
		<div
			className="fixed top-0 left-0 right-0 bottom-0 flex items-center justify-start p-4 z-50"
			style={ { marginLeft: '160px' } } // Adjust left padding
		>
			{ backdrop && (
				<div
					className="absolute top-0 left-0 right-0 bottom-0 bg-black/30"
					aria-hidden="true"
				/>
			) }
			<div
				ref={ dialogRef }
				tabIndex={ -1 } // Makes the div focusable
				className={ classNames(
					'flex justify-center z-50 mx-auto',
					className
				) }
				onClick={ ( e ) => e.stopPropagation() } // Prevents click inside the dialog from closing it
			>
				{ children }
			</div>
		</div>
	);
};

export default DialogWrapper;

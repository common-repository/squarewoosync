import { classNames } from '../../../../utils/classHelper';

export const ExpanderIcon = ( { isExpanded, row } ) => {
	if ( row.getCanExpand() ) {
		return (
			<svg
				xmlns="http://www.w3.org/2000/svg"
				width="24"
				height="24"
				viewBox="0 0 24 24"
				fill="none"
				stroke="currentColor"
				strokeWidth="2"
				strokeLinecap="round"
				strokeLinejoin="round"
				className={ `w-4 h-4 ${ isExpanded ? 'rotate-90' : '' }` }
			>
				<polyline points="9 18 15 12 9 6" />
			</svg>
		);
	} else {
		return <></>;
	}
};

export const PlaceholderIcon = () => (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		width="24"
		height="24"
		viewBox="0 0 24 24"
		fill="none"
		stroke="currentColor"
		strokeWidth="2"
		strokeLinecap="round"
		strokeLinejoin="round"
		className="text-gray-300"
	>
		<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" />
		<line x1="7" y1="7" x2="7.01" y2="7" />
	</svg>
);

export const StatusIcon = ( { status } ) => {
	const statusMap = {
		false: {
			bgColor: 'bg-red-100',
			textColor: 'text-red-700',
			fillColor: 'fill-red-500',
			text: 'Not imported',
		},
		partial: {
			bgColor: 'bg-yellow-100',
			textColor: 'text-yellow-700',
			fillColor: 'fill-yellow-500',
			text: 'Partial',
		},
		true: {
			bgColor: 'bg-green-100',
			textColor: 'text-green-700',
			fillColor: 'fill-green-500',
			text: 'Imported',
		},
	};

	const { bgColor, textColor, fillColor, text } =
		statusMap[ status ] || statusMap.false;

	return (
		<span
			className={ `inline-flex items-center gap-x-1.5 rounded-md px-2 py-1 text-xs font-medium ${ bgColor } ${ textColor }` }
		>
			<svg
				className={ `h-1.5 w-1.5 ${ fillColor }` }
				viewBox="0 0 6 6"
				aria-hidden="true"
			>
				<circle cx="3" cy="3" r="3" />
			</svg>
			{ text }
		</span>
	);
};

// Additional component for image cells to abstract complex logic
export const ImageCell = ( { value } ) => (
	<div className="group relative w-10 h-10">
		{ value.map( ( url, idx ) => (
			<img
				key={ idx }
				src={ url }
				alt=""
				width={ 40 }
				height={ 40 }
				className={ classNames(
					'w-10 h-10 rounded object-cover flex items-center gap-2 shadow top-0 absolute transition-transform duration-300',
					idx === 0 &&
						value.length > 1 &&
						'group-hover:-translate-y-2 rotate-12 group-hover:rotate-[-16deg]',
					idx === 1 &&
						value.length > 1 &&
						'group-hover:translate-y-2 group-hover:rotate-[16deg]'
				) }
			/>
		) ) }
	</div>
);
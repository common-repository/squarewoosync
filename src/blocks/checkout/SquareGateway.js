const settings	= window.wc.wcSettings.getSetting( 'you_bou_data', {} );
const methods	= settings.methods;

function Label( { label, icon } ) {
    return (
        <span className='wc-block-payment-method__label' style={{ width: '100%' }}>
            {label}
            <img src={icon} alt={label} className='wc-block-payment-method__image' style={{ float: 'right', marginRight: '20px' }} />
        </span>
    );
}

function Description( { description } ) {
    return (
        <div className='wc-block-payment-method__description' dangerouslySetInnerHTML={{ __html: description }} ></div>
    );
}

methods.forEach( method => {

	const params = {
		name: method.name,
		label: Label( method ),
		content: Description( method ),
		edit: Description( method ),
		canMakePayment: () => true,
		ariaLabel: method.label
	};
	window.wc.wcBlocksRegistry.registerPaymentMethod( params );
});
import React from 'react';
import LogoImg from '../../../assets/images/box-outline.svg';

const Logo = () => {
	return (
		<img
			className="h-8 w-auto text-white"
			src={ LogoImg }
			alt="Your Company"
		/>
	);
};

export default Logo;

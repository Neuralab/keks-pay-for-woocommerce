const settings = window.wc.wcSettings.getSetting( kekspayBlocksData.kekspayID + '_data', {} );
const label    = window.wp.htmlEntities.decodeEntities( settings.title );

const Content = () => {
	return window.wp.htmlEntities.decodeEntities( settings.description );
};

const Kekspay_Gateway = {
	name: kekspayBlocksData.kekspayID,
	label: label,
	content: Object( window.wp.element.createElement )( Content, null ),
	edit: Object( window.wp.element.createElement )( Content, null ),
	canMakePayment: () => true,
	ariaLabel: label,
	supports: {
		features: settings.supports,
	},
};

window.wc.wcBlocksRegistry.registerPaymentMethod( Kekspay_Gateway );

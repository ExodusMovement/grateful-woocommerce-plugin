/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';

/**
 * Internal dependencies
 */
import './checkout.scss';

const settings = getSetting('grateful_payment_data', {});

const defaultLabel = __('Grateful - Stablecoins', 'grateful-payments');

const label = decodeEntities(settings.title) || defaultLabel;

/**
 * Content component
 */
const Content = () => {
	return (
		<div>
			Pay with stablecoins.
		</div>
	);
};

/**
 * Label component
 */
const Label = (props) => {
	const { PaymentMethodLabel } = props.components;
	return <PaymentMethodLabel text={label} />;
};

/**
 * Grateful payment method config object.
 */
const gratefulPaymentMethod = {
	name: 'grateful_payment',
	label: <Label />,
	content: <Content />,
	edit: <Content />,
	canMakePayment: () => true,
	ariaLabel: label,
	supports: {
		features: settings.supports,
	},
};

registerPaymentMethod(gratefulPaymentMethod);

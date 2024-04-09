// Import necessary modules from wp.element
const { useState, useRef, useEffect } = wp.element;
const { registerPaymentMethod } = window.wc.wcBlocksRegistry;

// Define the settings and label for the Wonderful Payments Gateway
const settings = window.wc.wcSettings.getSetting('wonderful_payments_gateway_data', {});
const label = window.wp.htmlEntities.decodeEntities(settings.title || window.wp.i18n.__('Wonderful Payments Gateway', 'wonderful_payments_gateway'));

// Define the Icon component
const Icon = () => {
    if (settings.icon) {
        const img = document.createElement('img');
        img.src = settings.icon;
        img.style.float = 'right';
        img.style.marginRight = '20px';
        return img;
    }
    return null;
};

// Define the BankList component
const BankList = ({ banks }) => {
    let lastClickedButton = null;
    const [searchTerm, setSearchTerm] = useState('');

    if (!banks) {
        return null;
    }

    // Filter the banks based on the search term
    const filteredBanks = banks.filter(bank => bank.bank_name.toLowerCase().includes(searchTerm.toLowerCase()));

    // Create an array of button elements
    const buttons = filteredBanks.map((bank, index) => {

        // Create a logo element
        const logo = window.wp.element.createElement('img', {
            src: bank.bank_logo,
            style: {
                height: '3rem',
                width: '3rem',
                marginRight: '1rem'
            }
        });

        return window.wp.element.createElement(
            'div',
            {
                key: index,
                style: {
                    width: '90%',
                    border: '1px solid #E2E8F0',
                    transition: 'box-shadow 0.15s ease-in-out, border-color 0.15s ease-in-out',
                    padding: '0 1rem',
                    display: 'flex',
                    alignItems: 'center',
                    margin: '5px auto',
                    cursor: 'pointer'
                },
                onMouseOver: (event) => {
                    event.currentTarget.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
                    event.currentTarget.style.borderColor = '#4299e1';
                },
                onMouseOut: (event) => {
                    event.currentTarget.style.boxShadow = 'none';
                    event.currentTarget.style.borderColor = '#E2E8F0';
                },
                onClick: (event) => {
                    // If there was a previously clicked button, reset its styles
                    if (lastClickedButton) {
                        lastClickedButton.style.backgroundColor = '';
                        lastClickedButton.style.color = '#000000';
                    }

                    // Apply the styles to the newly clicked button
                    event.currentTarget.style.backgroundColor = '#1F2A64';
                    event.currentTarget.style.color = '#ffffff';

                    // Update the last clicked button
                    lastClickedButton = event.currentTarget;

                    // Set the selected bank
                    handleSelectChange(bank.bank_id);
                }
            },
            logo,
            bank.bank_name
        );
    });

    // Create a div element and append the divs to it
    const bankListDiv = window.wp.element.createElement(
        'div',
        null,
        buttons
    );

    // Create a logo element
    const logo = window.wp.element.createElement('img', {
        src: 'https://wonderful.one/images/logo.png',
        style: {
            display: 'block',
            marginLeft: 'auto',
            marginRight: 'auto',
            marginTop: '25px',
            width: '25%'
        }
    });

    // Create a strap line element
    const strapLine = window.wp.element.createElement('p', {
        style: {
            textAlign: 'center',
            fontSize: '0.8em'
        }
    }, 'Simple, Fast, Secure, Instant Bank Payments');

    // Create the SVG path element
    const svgPath = window.wp.element.createElement('path', {
        fillRule: 'evenodd',
        d: 'M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z',
        clipRule: 'evenodd'
    });

    // Create the SVG element
    const svg = window.wp.element.createElement('svg', {
        className: 'pointer-events-none absolute inset-y-0 left-0 h-5 w-5 text-gray-400 ml-3 my-auto', // Adjust the size and position
        viewBox: '0 0 20 20',
        fill: '#808080',
        'aria-hidden': 'true',
        style: {
            height: '20px',
            width: '20px'
        }
    }, svgPath);

    // Create a search box
    const searchBox = window.wp.element.createElement('input', {
        type: 'text',
        placeholder: 'Search for a bank...',
        style: {
            width: 'calc(100% - 2rem)',
            padding: '0.5rem',
            margin: '5px auto',
            display: 'block',
            border: 'none',
            outline: 'none',
            fontSize: '1em',
            backgroundColor: 'transparent'
        },
        value: searchTerm,
        onChange: (event) => {
            setSearchTerm(event.target.value);

            // If there was a previously clicked button, reset its styles
            if (lastClickedButton) {
                lastClickedButton.style.backgroundColor = '';
                lastClickedButton.style.color = '#000000';
            }
        }
    });

    // Create a div to hold the search box and the SVG
    const searchDiv = window.wp.element.createElement('div', {
        style: {
            position: 'relative',
            width: '92%',
            padding: '0.5rem',
            margin: '5px auto',
            display: 'flex',
            alignItems: 'center',
            border: '1px solid #E2E8F0',
            borderRadius: '0.25rem'
        }
    }, svg, searchBox);

    // Create a suffix element
    const suffix = window.wp.element.createElement('p', {
        style: {
            fontSize: '0.7em',
            textAlign: 'center',
            marginTop: '25px',
        },
        dangerouslySetInnerHTML: {
            __html: 'Instant payments are processed by <a href="https://wonderful.co.uk" target="_blank">Wonderful Payments</a> and are subject to their <a href="https://wonderful.co.uk/legal" target="_blank">Consumer Terms and Privacy Policy</a>.'
        }
    });

    const innerDiv = window.wp.element.createElement('div', {
        style: {
            backgroundColor: 'white',
            height: '28rem',
            overflowY: 'auto'
        }
    }, logo, strapLine, searchDiv, bankListDiv, suffix);

    const outerDiv = window.wp.element.createElement('div', {
        style: {
            marginLeft: 'auto',
            marginRight: 'auto',
            display: 'grid',
            gridTemplateColumns: 'repeat(1, minmax(0, 1fr))',
            alignItems: 'start',
            gap: '1rem',
            marginTop: '1rem'
        }
    }, innerDiv);

    return outerDiv;
};

// Define the handleSelectChange function
function handleSelectChange(selectedValue) {
    const ajaxUrl = window.location.origin + '/wp-admin/admin-ajax.php';

    // Send AJAX request
    window.jQuery.ajax({
        url: ajaxUrl,
        type: 'POST',
        data: {
            action: 'update_aspsp',
            aspsp: selectedValue,
        },
    });
}

// Define the Block_Gateway object
const Block_Gateway = {
    name: 'wonderful_payments_gateway',
    label: label,
    icon: Object(window.wp.element.createElement)(Icon, null),
    content: Object(window.wp.element.createElement)(BankList, { banks: settings.banks }),
    edit: Object(window.wp.element.createElement)(BankList, { banks: settings.banks }),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
 };

// Register the payment method
window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);


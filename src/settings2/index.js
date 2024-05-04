const {render} = wp.element;
//
// import {
//     render,
//     useContext,
// } from '@wordpress/element';
import Settings from './Settings'

document.addEventListener('DOMContentLoaded', () => {
    const appElement = document.getElementById("ai-content-generate-app");
    if (!appElement) {
        return;
    }
    render(<Settings/>, appElement);
});

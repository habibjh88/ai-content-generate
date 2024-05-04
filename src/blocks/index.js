/* global wp */
const {__} = wp.i18n;
const {render} = wp.element;
import ImportLayout from './import/ImportLayout'
wp.domReady(function () {
    setTimeout(function () {

        const toolbar = document.querySelector(".edit-post-header__toolbar");
        const toolbarChild = document.querySelector(".edit-post-header-toolbar");

        if (!toolbar) {
            return;
        }
        const dowpImportWrap = document.createElement("div");
        dowpImportWrap.classList.add("dowp-import-button-wrapper");
        const newContent = document.createTextNode("Hi there and greetings!");

        // add the text node to the newly created div
        dowpImportWrap.appendChild(newContent);

        if (!toolbar.querySelector(".dowp-import-button-wrapper")) {
            render(<ImportLayout/>, dowpImportWrap);
            toolbar.insertBefore(dowpImportWrap, toolbarChild.nextSibling);
        }
    }, 400);
});
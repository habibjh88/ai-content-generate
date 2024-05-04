const {render} = wp.element;
import App from "./components/App";

const appElement = document.getElementById("ai-content-generate-app");

if(appElement){
    render(<App/>, appElement);
}

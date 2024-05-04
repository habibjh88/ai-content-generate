const {__} = wp.i18n;
const ResponseButton = ({fetch_openai_data, setRequestText, message}) => {

    return (
        <div className='response-button'>
            <button
                onClick={() => {
                    fetch_openai_data()
                    setRequestText(message)
                }}
            >{__("Rewrite")}</button>
            <button
                onClick={() => {
                    fetch_openai_data()
                    setRequestText(message)
                }}
            >{__("Improve")}</button>
            <button
                onClick={() => {
                    fetch_openai_data("Write about \"" + message + '\" - Make the content shorter')
                    setRequestText(message)
                }}
            >{__("Make Shorter")}</button>
            <button
                onClick={() => {
                    fetch_openai_data("Write about \"" + message + '\" - Make the content longer')
                    setRequestText(message)
                }}
            >{__("Make Longer")}</button>
            <button
                onClick={() => {
                    fetch_openai_data("Write about \"" + message + '\" - Make the content as a summarize')
                    setRequestText(message)
                }}
            >{__("Summarize")}</button>
            <button
                onClick={() => {
                    fetch_openai_data("Write about \"" + message + '\" - Make the content as an introduction summery')
                    setRequestText(message)
                }}
            >{__("Introduction")}</button>
            <button
                onClick={() => {
                    fetch_openai_data("Write about \"" + message + '\" - Make the content as an conclusion summery')
                    setRequestText(message)
                }}
            >{__("Conclusion")}</button>
            <button
                onClick={() => {
                    fetch_openai_data("Write about \"" + message + '\" - Make the content to Active Voice')
                    setRequestText(message)
                }}
            >{__("Make Active Voice")}</button>
            <button
                onClick={() => {
                    fetch_openai_data("Write about \"" + message + '\" - Make the content to Passive Voice')
                    setRequestText(message)
                }}
            >{__("Make Passive Voice")}</button>
            <button
                onClick={() => {
                    fetch_openai_data("Write about \"" + message + '\" - Make the content as Assertive content')
                    setRequestText(message)
                }}
            >{__("Make Assertive")}</button>
        </div>
    )
}

export default ResponseButton;
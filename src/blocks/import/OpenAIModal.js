import {SelectControl, TextControl} from "@wordpress/components";
const {useEffect, useState, useRef} = wp.element;
const {apiFetch} = wp;
const {__} = wp.i18n;
import {RttpgModal, ModalManager} from "./ModalManager";
import "./ImportLayoutModal.scss";
import cogoToast from "cogo-toast";

import ResponseButton from "./components/ResponseButton"

const rtDomParser = (htmlString) => {

	// Create a new DOMParser
	var parser = new DOMParser();

	// Parse the HTML string
	var doc = parser.parseFromString(htmlString, 'text/html');

	// Extract and display the body content
	var bodyContent = doc.body.innerHTML;

	return bodyContent;
}

const OpenAIModal = (props) => {
	const [writingStyle, setWritingStyle] = useState('html');
	const [headingNumber, setHeadingNumber] = useState('');
	const [headingTag, setHeadingTag] = useState('');
	const [language, setLanguage] = useState('');
	const responseText = useRef();
	const parentElement = useRef();


	const [loading, setLoading] = useState(true);
	const [message, setMessage] = useState('');
	const [requestText, setRequestText] = useState('');
	const [aiResponse, setAiResponse] = useState('');
	// const [initialRender, setInitialRender] = useState(true);
	const [signalController, setSignalController] = useState();
	const controller = typeof AbortController === 'undefined' ? undefined : new AbortController();
	const fetch_openai_data = (msg = '') => {
		signalController?.abort();
		setSignalController(controller);
		setAiResponse('');
		apiFetch({
			path: '/dowp/v1/chatgpt',
			signal: controller?.signal,
			method: 'POST',
			data: {
				writingStyle,
				language,
				request_txt: msg ? msg : message,
				headingNumber,
				headingTag,
			},
		}).then((data) => {
			setAiResponse(data);
			setLoading(false)
		});
	}

	const handleInsert = () => {
		var name = 'core/freeform';
		var insertedBlock = wp.blocks.createBlock(name, {
			content: rtDomParser(aiResponse.content),
		});
		wp.data.dispatch('core/block-editor').insertBlocks(insertedBlock);
		ModalManager.close();
	}

	function handleCopy() {
		var copyText = rtDomParser(aiResponse.content);
		// Create a temporary input element
		var tempInput = document.createElement('input');
		tempInput.value = copyText;

		// Append the temporary input to the DOM
		document.body.appendChild(tempInput);

		// Select the text in the temporary input
		tempInput.select();
		tempInput.setSelectionRange(0, 99999); // For mobile devices

		// Execute the copy command
		document.execCommand('copy');

		// Remove the temporary input from the DOM
		document.body.removeChild(tempInput);

		// Optionally, provide feedback to the user
		alert('Text copied to clipboard');
	}


	useEffect(() => {

		const allComponent = parentElement.current.querySelectorAll('.need-pro .components-base-control');

		allComponent.forEach((item) => {
			item.addEventListener('click', function (e) {
				cogoToast.warn('N.B: This field works front-end only.');
			})
		})

	}, []);

	return (

			<RttpgModal
				modalClass={`dowp-openai-modal-wrapper`}
				openTimeoutMS={0}
				closeTimeoutMS={0}
			>

				<div ref={parentElement} className={`dowp-modal-inner dowp-openai-modal-wrapper`}>
					<div className="dowp-layout-modal-header">
						<div className="dowp-layout-header-inner">
							<button className='active'>
								<svg width="20" height="20" viewBox="0 0 20 20" fill="none"
									 xmlns="http://www.w3.org/2000/svg">
									<rect x="0.699219" y="0.767334" width="19.2366" height="19.2366" rx="3"
										  fill="white"/>
									<path
										d="M16.5905 9.05632C16.7542 8.56335 16.8108 8.04113 16.7566 7.52453C16.7023 7.00793 16.5385 6.50886 16.2759 6.06065C15.8867 5.38319 15.2924 4.84682 14.5787 4.52888C13.865 4.21094 13.0688 4.12786 12.3048 4.29161C11.8708 3.80882 11.3174 3.44873 10.7002 3.24751C10.0829 3.04629 9.42363 3.01103 8.78846 3.14525C8.15328 3.27948 7.56461 3.57848 7.08156 4.01221C6.59851 4.44594 6.23809 4.99915 6.0365 5.61626C5.52755 5.72062 5.04673 5.93242 4.62618 6.23749C4.20563 6.54256 3.85504 6.93389 3.59784 7.3853C3.2044 8.06164 3.03625 8.84558 3.11769 9.62378C3.19914 10.402 3.52596 11.1341 4.0509 11.7143C3.8866 12.2071 3.82942 12.7292 3.88318 13.2458C3.93694 13.7624 4.10039 14.2616 4.36262 14.7099C4.75232 15.3877 5.34714 15.9242 6.06134 16.2421C6.77554 16.56 7.57224 16.643 8.33663 16.4791C8.68145 16.8674 9.10514 17.1776 9.57942 17.3892C10.0537 17.6007 10.5676 17.7086 11.0869 17.7057C11.87 17.7064 12.633 17.4583 13.2658 16.9971C13.8987 16.536 14.3686 15.8857 14.6078 15.1401C15.1167 15.0355 15.5975 14.8236 16.018 14.5186C16.4385 14.2135 16.7892 13.8223 17.0465 13.371C17.4353 12.6956 17.6005 11.9146 17.5186 11.1397C17.4367 10.3647 17.1119 9.63547 16.5905 9.05632ZM11.0869 16.7475C10.4456 16.7485 9.82446 16.5237 9.3323 16.1126L9.41886 16.0635L12.3337 14.381C12.4062 14.3384 12.4665 14.2777 12.5085 14.2049C12.5505 14.132 12.5728 14.0495 12.5732 13.9654V9.85574L13.8055 10.5686C13.8116 10.5717 13.8168 10.5762 13.8209 10.5817C13.8249 10.5872 13.8276 10.5936 13.8287 10.6003V14.0058C13.8271 14.7325 13.5378 15.4289 13.0239 15.9428C12.5101 16.4566 11.8136 16.7459 11.0869 16.7475ZM5.19377 14.2309C4.87215 13.6756 4.75667 13.0246 4.86765 12.3925L4.95427 12.4445L7.87198 14.127C7.94417 14.1694 8.02636 14.1917 8.11007 14.1917C8.19377 14.1917 8.27596 14.1694 8.34816 14.127L11.9124 12.0722V13.495C11.912 13.5024 11.91 13.5096 11.9065 13.5161C11.903 13.5225 11.8981 13.5282 11.8921 13.5325L8.93975 15.2352C8.30968 15.5982 7.56133 15.6963 6.85899 15.508C6.15666 15.3197 5.55775 14.8604 5.19377 14.2309ZM4.42612 7.88173C4.74997 7.32282 5.26113 6.89651 5.86911 6.67829V10.1415C5.86802 10.2251 5.8894 10.3076 5.93102 10.3802C5.97264 10.4527 6.03298 10.5128 6.10574 10.5542L9.65262 12.6003L8.42032 13.3131C8.41365 13.3167 8.40622 13.3185 8.39867 13.3185C8.39112 13.3185 8.38368 13.3167 8.37701 13.3131L5.43045 11.6133C4.80156 11.2488 4.34276 10.6499 4.15452 9.94778C3.96628 9.24568 4.06394 8.49759 4.42612 7.86734V7.88173ZM14.5501 10.2338L10.9917 8.16746L12.2211 7.45741C12.2278 7.45387 12.2353 7.45201 12.2428 7.45201C12.2504 7.45201 12.2578 7.45387 12.2645 7.45741L15.211 9.16014C15.6615 9.4201 16.0288 9.80286 16.27 10.2637C16.5111 10.7246 16.6162 11.2446 16.5729 11.7629C16.5297 12.2812 16.3398 12.7766 16.0256 13.1911C15.7114 13.6056 15.2857 13.9222 14.7983 14.1038V10.6407C14.7958 10.5571 14.7716 10.4757 14.728 10.4044C14.6845 10.333 14.6232 10.2743 14.5501 10.2338ZM15.7767 8.38969L15.6901 8.33772L12.7782 6.64078C12.7055 6.59815 12.6228 6.57568 12.5386 6.57568C12.4544 6.57568 12.3717 6.59815 12.299 6.64078L8.73784 8.69556V7.27282C8.73708 7.26558 8.73829 7.25828 8.74134 7.25167C8.74439 7.24507 8.74916 7.23941 8.75516 7.2353L11.7017 5.53543C12.1533 5.27527 12.6697 5.14907 13.1904 5.17159C13.711 5.19411 14.2146 5.36442 14.642 5.66261C15.0695 5.96079 15.4032 6.37452 15.6041 6.8554C15.8051 7.33628 15.865 7.86444 15.7767 8.3781L15.7767 8.38969ZM8.06529 10.9119L6.83306 10.202C6.8269 10.1983 6.82162 10.1933 6.81762 10.1873C6.81361 10.1813 6.81097 10.1745 6.80988 10.1674V6.77065C6.81056 6.24956 6.95957 5.73943 7.23949 5.2999C7.5194 4.86038 7.91866 4.50962 8.39058 4.28864C8.8625 4.06766 9.38757 3.9856 9.90441 4.05204C10.4212 4.11848 10.9085 4.33069 11.3092 4.66384L11.2226 4.71295L8.30777 6.39537C8.23523 6.43792 8.175 6.4986 8.133 6.57147C8.091 6.64433 8.06868 6.72687 8.06822 6.81097L8.06529 10.9119ZM8.73485 9.46899L10.3221 8.55409L11.9124 9.46899V11.2987L10.3279 12.2135L8.73777 11.2987L8.73485 9.46899Z"
										fill="#11B466"/>
								</svg>
								{__("Make AI Content Generate by ChatGPT")}
							</button>

						</div>


						<button
							className="dowp-modal-close-wrapper"
							onClick={(e) => {
								ModalManager.close();
							}}
						>
							<svg width="14" height="14" viewBox="0 0 14 14" fill="none"
								 xmlns="http://www.w3.org/2000/svg">
								<path
									d="M7 8.49049L1.78327 13.7072C1.58809 13.9024 1.33967 14 1.03802 14C0.736375 14 0.487959 13.9024 0.292775 13.7072C0.0975915 13.512 0 13.2636 0 12.962C0 12.6603 0.0975915 12.4119 0.292775 12.2167L5.50951 7L0.292775 1.78327C0.0975915 1.58809 0 1.33967 0 1.03802C0 0.736375 0.0975915 0.487959 0.292775 0.292775C0.487959 0.0975915 0.736375 0 1.03802 0C1.33967 0 1.58809 0.0975915 1.78327 0.292775L7 5.50951L12.2167 0.292775C12.4119 0.0975915 12.6603 0 12.962 0C13.2636 0 13.512 0.0975915 13.7072 0.292775C13.9024 0.487959 14 0.736375 14 1.03802C14 1.33967 13.9024 1.58809 13.7072 1.78327L8.49049 7L13.7072 12.2167C13.9024 12.4119 14 12.6603 14 12.962C14 13.2636 13.9024 13.512 13.7072 13.7072C13.512 13.9024 13.2636 14 12.962 14C12.6603 14 12.4119 13.9024 12.2167 13.7072L7 8.49049Z"
									fill="white"/>
							</svg>

						</button>
					</div>


					{/*TODO: Left Sidebar*/}


					<div className="dowp-modal-content-wrapper">


						<div id="modalContainer" className="dowp-template-list">
							<div className="dowp-modal-template-container">

								<div
									id="layouts-blocks-list"
									className={"dowp-builder-page-templates"}
								>

									<div className={`message-input-wrapper`}>
										<TextControl
											autocomplete="on"
											placeholder="Ask Anything"
											className="dowp-control-field label-inline dowp-expand has-help pro-field"
											value={message}
											onChange={(value) => {
												setMessage(value)
											}}
											onKeyDown={(event) => {
												if (event.key === 'Enter') {
													fetch_openai_data()
													setRequestText(message)
													// setMessage('')
												}
											}}
										/>

										<button
											className='text-submit'
											onClick={() => {
												fetch_openai_data()
												setRequestText(message)
												// setMessage('')
											}}
										>
											<svg width="17" height="16" viewBox="0 0 17 16" fill="none"
												 xmlns="http://www.w3.org/2000/svg">
												<path
													d="M8.22266 1L8.22266 15M8.22266 15L15.2227 8M8.22266 15L1.22266 8"
													stroke="white" stroke-width="2" stroke-linecap="round"
													stroke-linejoin="round"/>
											</svg>
										</button>
									</div>

									{requestText &&
										<>
											{
												aiResponse.content ?

													<>
														{aiResponse.status === 'ok' &&
															<ResponseButton
																fetch_openai_data={fetch_openai_data}
																setRequestText={setRequestText}
																message={message}
															/>
														}

														<div
															className={`response-text response-text ${aiResponse.status === 'error' && 'has-error'}`}>
															<div className={`label`}>
																<svg width="25" height="25" viewBox="0 0 25 25"
																	 fill="none"
																	 xmlns="http://www.w3.org/2000/svg">
																	<circle cx="12.748" cy="12.3856" r="12"
																			fill="#11B466"/>
																	<path
																		d="M19.3316 10.9905C19.5034 10.4731 19.5628 9.92498 19.5059 9.38278C19.449 8.84057 19.277 8.31676 19.0014 7.84634C18.5929 7.13529 17.9692 6.57233 17.2201 6.23863C16.471 5.90493 15.6353 5.81773 14.8335 5.9896C14.378 5.48288 13.7971 5.10494 13.1493 4.89375C12.5015 4.68256 11.8095 4.64554 11.1428 4.78642C10.4762 4.9273 9.8583 5.24112 9.3513 5.69635C8.84431 6.15159 8.46603 6.73221 8.25445 7.37991C7.72026 7.48945 7.21561 7.71175 6.77421 8.03194C6.33282 8.35214 5.96485 8.76285 5.6949 9.23665C5.28196 9.94651 5.10547 10.7693 5.19095 11.5861C5.27643 12.4029 5.61945 13.1713 6.17042 13.7803C5.99798 14.2974 5.93796 14.8454 5.99438 15.3877C6.0508 15.9299 6.22236 16.4538 6.49759 16.9244C6.90661 17.6357 7.53091 18.1988 8.28051 18.5325C9.03011 18.8662 9.86631 18.9532 10.6686 18.7812C11.0305 19.1887 11.4752 19.5144 11.973 19.7364C12.4708 19.9584 13.0102 20.0717 13.5552 20.0686C14.3771 20.0693 15.1779 19.8089 15.8421 19.3249C16.5064 18.8409 16.9996 18.1584 17.2507 17.3758C17.7848 17.2661 18.2894 17.0437 18.7307 16.7235C19.1721 16.4033 19.5401 15.9927 19.8102 15.519C20.2183 14.8102 20.3917 13.9905 20.3057 13.1771C20.2198 12.3638 19.8788 11.5984 19.3316 10.9905ZM13.5552 19.0629C12.8821 19.0639 12.2302 18.828 11.7136 18.3965L11.8045 18.345L14.8638 16.5791C14.9399 16.5344 15.0031 16.4707 15.0472 16.3942C15.0913 16.3178 15.1147 16.2311 15.1152 16.1429V11.8295L16.4085 12.5777C16.4149 12.581 16.4205 12.5857 16.4247 12.5915C16.4289 12.5973 16.4317 12.604 16.4329 12.611V16.1853C16.4312 16.948 16.1275 17.679 15.5882 18.2183C15.0489 18.7576 14.3179 19.0613 13.5552 19.0629ZM7.36993 16.4216C7.03238 15.8387 6.91117 15.1555 7.02765 14.492L7.11857 14.5466L10.1809 16.3125C10.2567 16.357 10.3429 16.3804 10.4308 16.3804C10.5187 16.3804 10.6049 16.357 10.6807 16.3125L14.4216 14.1558V15.6492C14.4212 15.6569 14.4191 15.6645 14.4154 15.6713C14.4118 15.6781 14.4066 15.684 14.4003 15.6885L11.3016 17.4757C10.6403 17.8566 9.85485 17.9596 9.11771 17.762C8.38056 17.5643 7.75196 17.0823 7.36993 16.4216ZM6.56424 9.75769C6.90414 9.17107 7.44064 8.72363 8.07876 8.49459V12.1294C8.07761 12.2173 8.10005 12.3038 8.14373 12.38C8.18741 12.4561 8.25075 12.5192 8.32711 12.5626L12.0498 14.7102L10.7564 15.4583C10.7494 15.462 10.7416 15.464 10.7337 15.464C10.7258 15.464 10.718 15.462 10.711 15.4583L7.61835 13.6742C6.95829 13.2916 6.47675 12.6631 6.27918 11.9261C6.08161 11.1892 6.18411 10.4041 6.56424 9.74258V9.75769ZM17.1901 12.2264L13.4553 10.0576L14.7457 9.31233C14.7527 9.30861 14.7605 9.30667 14.7684 9.30667C14.7763 9.30667 14.7841 9.30861 14.7911 9.31233L17.8837 11.0995C18.3566 11.3723 18.7421 11.774 18.9952 12.2578C19.2483 12.7415 19.3586 13.2872 19.3132 13.8312C19.2678 14.3753 19.0685 14.8952 18.7387 15.3302C18.4089 15.7653 17.9622 16.0975 17.4506 16.2882V12.6534C17.4479 12.5657 17.4225 12.4802 17.3768 12.4054C17.3311 12.3305 17.2668 12.2689 17.1901 12.2264ZM18.4774 10.2908L18.3865 10.2363L15.3303 8.45522C15.254 8.41048 15.1672 8.38689 15.0788 8.38689C14.9905 8.38689 14.9037 8.41048 14.8274 8.45522L11.0897 10.6118V9.11859C11.0889 9.11099 11.0902 9.10333 11.0934 9.0964C11.0966 9.08947 11.1016 9.08353 11.1079 9.07921L14.2005 7.29508C14.6745 7.02202 15.2164 6.88957 15.7629 6.9132C16.3094 6.93684 16.8379 7.1156 17.2865 7.42856C17.7352 7.74152 18.0854 8.17576 18.2963 8.68048C18.5072 9.1852 18.5701 9.73954 18.4775 10.2787L18.4774 10.2908ZM10.3838 12.9381L9.09049 12.193C9.08402 12.1891 9.07848 12.1838 9.07428 12.1775C9.07008 12.1712 9.0673 12.1641 9.06616 12.1567V8.59153C9.06687 8.04461 9.22327 7.50919 9.51706 7.04788C9.81085 6.58656 10.2299 6.21842 10.7252 5.98649C11.2205 5.75456 11.7716 5.66842 12.3141 5.73816C12.8565 5.80789 13.3679 6.03062 13.7885 6.38028L13.6976 6.43182L10.6383 8.19764C10.5622 8.2423 10.4989 8.306 10.4549 8.38247C10.4108 8.45895 10.3874 8.54558 10.3869 8.63385L10.3838 12.9381ZM11.0865 11.4236L12.7525 10.4634L14.4216 11.4236V13.344L12.7586 14.3042L11.0896 13.344L11.0865 11.4236Z"
																		fill="white"/>
																</svg>

															</div>
															<div className='text'>
																{aiResponse.status === 'ok' &&
																	<div className='action-button'>
																		<button onClick={handleInsert}>
																			<svg width="22" height="20"
																				 viewBox="0 0 22 20"
																				 fill="none"
																				 xmlns="http://www.w3.org/2000/svg">
																				<path
																					d="M11 12.2295C10.9048 12.2296 10.8106 12.2113 10.7226 12.1757C10.6347 12.14 10.5547 12.0878 10.4874 12.0219C10.4201 11.956 10.3668 11.8778 10.3304 11.7917C10.294 11.7056 10.2753 11.6134 10.2754 11.5202L10.2754 0.912773C10.2754 0.72466 10.3517 0.544251 10.4876 0.411235C10.6235 0.278219 10.8078 0.203491 11 0.203491C11.1922 0.203491 11.3765 0.278219 11.5124 0.411235C11.6483 0.544251 11.7246 0.72466 11.7246 0.912773L11.7246 11.5202C11.7246 11.7083 11.6483 11.8887 11.5124 12.0218C11.3765 12.1548 11.1922 12.2295 11 12.2295Z"
																					fill="currentColor"/>
																				<path fill-rule="evenodd"
																					  clip-rule="evenodd"
																					  d="M11.0002 12.4295C10.8793 12.4296 10.7595 12.4064 10.6475 12.361C10.5356 12.3157 10.4336 12.2491 10.3475 12.1648C10.2614 12.0806 10.1929 11.9803 10.1461 11.8696C10.0994 11.7589 10.0753 11.6402 10.0754 11.5201C10.0754 11.5201 10.0754 11.5201 10.0754 11.52L10.2748 11.5202H10.0754L10.0754 11.5201L10.0754 0.91276C10.0754 0.670205 10.1738 0.438473 10.3477 0.268295C10.5214 0.0982601 10.7561 0.003479 11 0.003479C11.2439 0.003479 11.4786 0.0982601 11.6523 0.268295C11.8262 0.438473 11.9246 0.670205 11.9246 0.91276L11.9246 11.5202C11.9246 11.7628 11.8262 11.9945 11.6523 12.1647C11.4786 12.3347 11.244 12.4294 11.0002 12.4295ZM10.7226 12.1756C10.8106 12.2113 10.9048 12.2296 11 12.2295C11.1922 12.2295 11.3765 12.1548 11.5124 12.0217C11.6483 11.8887 11.7246 11.7083 11.7246 11.5202L11.7246 0.91276C11.7246 0.724647 11.6483 0.544239 11.5124 0.411223C11.3765 0.278207 11.1922 0.203479 11 0.203479C10.8078 0.203479 10.6235 0.278207 10.4876 0.411223C10.3517 0.544239 10.2754 0.724647 10.2754 0.91276L10.2754 11.5202C10.2753 11.6134 10.294 11.7056 10.3304 11.7917C10.3668 11.8778 10.4201 11.956 10.4874 12.0219C10.5547 12.0878 10.6347 12.14 10.7226 12.1756Z"
																					  fill="white"/>
																				<path
																					d="M11 12.5484C10.7565 12.5488 10.5152 12.5021 10.2902 12.4109C10.0652 12.3198 9.86076 12.186 9.6887 12.0173L5.77508 8.18661C5.63924 8.05365 5.56293 7.87332 5.56293 7.68529C5.56293 7.49726 5.63924 7.31693 5.77508 7.18397C5.91091 7.05101 6.09515 6.97632 6.28725 6.97632C6.47935 6.97632 6.66359 7.05101 6.79942 7.18397L10.713 11.0147C10.7507 11.0515 10.7955 11.0808 10.8447 11.1008C10.8939 11.1207 10.9467 11.131 11 11.131C11.0533 11.131 11.1061 11.1207 11.1553 11.1008C11.2045 11.0808 11.2493 11.0515 11.287 11.0147L15.2006 7.18397C15.3372 7.05473 15.5202 6.98319 15.7102 6.98475C15.9002 6.98632 16.082 7.06086 16.2164 7.19233C16.3508 7.3238 16.427 7.50168 16.4288 7.68765C16.4305 7.87362 16.3575 8.0528 16.2255 8.18661L12.3113 12.0173C12.1392 12.186 11.9348 12.3197 11.7098 12.4108C11.4847 12.502 11.2435 12.5487 11 12.5484Z"
																					fill="white"/>
																				<path fill-rule="evenodd"
																					  clip-rule="evenodd"
																					  d="M11.287 11.0146L15.2006 7.18396C15.3372 7.05472 15.5202 6.98318 15.7102 6.98474C15.9002 6.9863 16.082 7.06085 16.2164 7.19232C16.3508 7.32379 16.4271 7.50166 16.4288 7.68763C16.4305 7.87361 16.3575 8.05279 16.2255 8.1866L12.3113 12.0173C12.1392 12.1859 11.9348 12.3197 11.7098 12.4108C11.4847 12.502 11.2435 12.5487 11 12.5484C10.7565 12.5488 10.5153 12.5021 10.2902 12.4109C10.0652 12.3198 9.86076 12.186 9.6887 12.0173L5.77508 8.1866C5.63924 8.05364 5.56293 7.87331 5.56293 7.68528C5.56293 7.49725 5.63924 7.31692 5.77508 7.18396C5.91092 7.051 6.09515 6.97631 6.28725 6.97631C6.47936 6.97631 6.66359 7.051 6.79943 7.18396L10.713 11.0146C10.7507 11.0515 10.7955 11.0808 10.8447 11.1008C10.8939 11.1207 10.9467 11.131 11 11.131C11.0533 11.131 11.1061 11.1207 11.1553 11.1008C11.2045 11.0808 11.2493 11.0515 11.287 11.0146ZM12.4513 12.1601L16.3654 8.32954L16.3679 8.32703C16.5368 8.1558 16.6309 7.92559 16.6287 7.6858C16.6266 7.44602 16.5282 7.21755 16.3563 7.04935C16.1845 6.88129 15.953 6.78673 15.7119 6.78475C15.4708 6.78276 15.2378 6.8735 15.0631 7.03866L11.1471 10.8717C11.1281 10.8902 11.1054 10.9052 11.0802 10.9154C11.0549 10.9257 11.0276 10.931 11 10.931C10.9724 10.931 10.9451 10.9257 10.9199 10.9154C10.8946 10.9052 10.8719 10.8902 10.853 10.8717L6.93933 7.04103C6.76567 6.87106 6.53103 6.77631 6.28725 6.77631C6.04347 6.77631 5.80884 6.87106 5.63518 7.04103C5.46138 7.21115 5.36293 7.44281 5.36293 7.68528C5.36293 7.92775 5.46138 8.15941 5.63518 8.32953L9.54868 12.1601C9.73954 12.3472 9.96607 12.4954 10.2151 12.5963C10.4641 12.6971 10.7308 12.7488 11 12.7484"
																					  fill="white"/>
																				<path
																					d="M19.0788 19H2.92116C2.41182 18.9994 1.92353 18.8011 1.56337 18.4486C1.20322 18.0961 1.00061 17.6181 1 17.1196V12.1614C1 11.9733 1.07635 11.7929 1.21224 11.6599C1.34814 11.5269 1.53245 11.4521 1.72464 11.4521C1.91682 11.4521 2.10114 11.5269 2.23703 11.6599C2.37293 11.7929 2.44928 11.9733 2.44928 12.1614V17.1196C2.44943 17.242 2.49919 17.3594 2.58766 17.446C2.67612 17.5326 2.79605 17.5813 2.92116 17.5815H19.0788C19.2039 17.5813 19.3239 17.5326 19.4123 17.446C19.5008 17.3594 19.5506 17.242 19.5507 17.1196V12.1614C19.5507 11.9733 19.6271 11.7929 19.763 11.6599C19.8989 11.5269 20.0832 11.4521 20.2754 11.4521C20.4675 11.4521 20.6519 11.5269 20.7878 11.6599C20.9237 11.7929 21 11.9733 21 12.1614V17.1196C20.9994 17.6181 20.7968 18.0961 20.4366 18.4486C20.0765 18.8011 19.5882 18.9994 19.0788 19Z"
																					fill="white"/>
																				<path fill-rule="evenodd"
																					  clip-rule="evenodd"
																					  d="M19.0788 19.2H2.92116C2.36023 19.1994 1.82139 18.981 1.42348 18.5915C1.02542 18.2019 0.800684 17.6727 0.800003 17.1198L0.800003 12.1614C0.800003 11.9189 0.898484 11.6871 1.07235 11.517C1.24606 11.3469 1.48078 11.2521 1.72464 11.2521C1.9685 11.2521 2.20322 11.3469 2.37694 11.517C2.5508 11.6871 2.64928 11.9189 2.64928 12.1614L2.64928 17.1193C2.64928 17.1194 2.64928 17.1193 2.64928 17.1193C2.64939 17.1874 2.67702 17.2536 2.72756 17.3031C2.77826 17.3527 2.84789 17.3814 2.9214 17.3815H19.0786C19.1521 17.3814 19.2217 17.3527 19.2724 17.3031C19.323 17.2536 19.3506 17.1874 19.3507 17.1193V12.1614C19.3507 11.9189 19.4492 11.6871 19.6231 11.517C19.7968 11.3469 20.0315 11.2521 20.2754 11.2521C20.5192 11.2521 20.7539 11.3469 20.9277 11.517C21.1015 11.6871 21.2 11.9189 21.2 12.1614V17.1196C21.1993 17.6725 20.9746 18.2019 20.5765 18.5915C20.1786 18.981 19.6398 19.1994 19.0788 19.2ZM19.0788 17.5815H2.92116C2.79606 17.5813 2.67612 17.5326 2.58766 17.446C2.4992 17.3594 2.44943 17.242 2.44928 17.1196V12.1614C2.44928 11.9733 2.37293 11.7929 2.23704 11.6599C2.10114 11.5269 1.91683 11.4521 1.72464 11.4521C1.53245 11.4521 1.34814 11.5269 1.21224 11.6599C1.07635 11.7929 1 11.9733 1 12.1614V17.1196C1.00062 17.6181 1.20322 18.0961 1.56338 18.4486C1.92353 18.8011 2.41183 18.9994 2.92116 19H19.0788C19.5882 18.9994 20.0765 18.8011 20.4366 18.4486C20.7968 18.0961 20.9994 17.6181 21 17.1196V12.1614C21 11.9733 20.9237 11.7929 20.7878 11.6599C20.6519 11.5269 20.4676 11.4521 20.2754 11.4521C20.0832 11.4521 19.8989 11.5269 19.763 11.6599C19.6271 11.7929 19.5507 11.9733 19.5507 12.1614V17.1196C19.5506 17.242 19.5008 17.3594 19.4123 17.446C19.3239 17.5326 19.2039 17.5813 19.0788 17.5815Z"
																					  fill="white"/>
																			</svg>
																			{__("Inset")}
																		</button>
																		<button onClick={handleCopy}>
																			<svg width="24" height="24"
																				 viewBox="0 0 24 24"
																				 fill="none"
																				 xmlns="http://www.w3.org/2000/svg"
																				 className="icon-sm">
																				<path fill-rule="evenodd"
																					  clip-rule="evenodd"
																					  d="M12 3.5C10.8954 3.5 10 4.39543 10 5.5H14C14 4.39543 13.1046 3.5 12 3.5ZM8.53513 3.5C9.22675 2.3044 10.5194 1.5 12 1.5C13.4806 1.5 14.7733 2.3044 15.4649 3.5H17.25C18.9069 3.5 20.25 4.84315 20.25 6.5V18.5C20.25 20.1569 19.1569 21.5 17.25 21.5H6.75C5.09315 21.5 3.75 20.1569 3.75 18.5V6.5C3.75 4.84315 5.09315 3.5 6.75 3.5H8.53513ZM8 5.5H6.75C6.19772 5.5 5.75 5.94772 5.75 6.5V18.5C5.75 19.0523 6.19772 19.5 6.75 19.5H17.25C18.0523 19.5 18.25 19.0523 18.25 18.5V6.5C18.25 5.94772 17.8023 5.5 17.25 5.5H16C16 6.60457 15.1046 7.5 14 7.5H10C8.89543 7.5 8 6.60457 8 5.5Z"
																					  fill="currentColor"></path>
																			</svg>
																			{__("Copy")}
																		</button>
																	</div>
																}
																<div ref={responseText} className="response"
																	 dangerouslySetInnerHTML={{__html: rtDomParser(aiResponse.content)}}></div>

															</div>
														</div>
													</>
													:
													<div className="chat-bubble">
														<div className="typing">
															<div className="dot"></div>
															<div className="dot"></div>
															<div className="dot"></div>
														</div>
													</div>
											}
										</>
									}

								</div>
							</div>
						</div>

					</div>

					<div className="dowp-layout-modal-sidebar">
						<div className="dowp-modal-sidebar-content">
							<h3>{__('Choose Settings')}</h3>

							<SelectControl
								label={__('Writing Style', 'ai-content-generate')}
								className="dowp-control-field label-inline dowp-expand"
								value={writingStyle}
								options={[
									{value: '', label: __('- Writing Style -', 'ai-content-generate')},
									{value: 'plane-text', label: __('Plane Text', 'ai-content-generate')},
									{value: 'html', label: __('With HTML Tags', 'ai-content-generate')},
								]}
								onChange={(value) => {
									setWritingStyle(value)
								}}
							/>

							{'html' === writingStyle &&
								<>
									<SelectControl
										label={__('Number of Heading', 'ai-content-generate')}
										className="dowp-control-field label-inline dowp-expand"
										value={headingNumber}
										options={[
											{value: '', label: __('- Heading Number -', 'ai-content-generate')},
											{value: '1', label: __('1', 'ai-content-generate')},
											{value: '2', label: __('2', 'ai-content-generate')},
											{value: '3', label: __('3', 'ai-content-generate')},
											{value: '4', label: __('4', 'ai-content-generate')},
											{value: '5', label: __('5', 'ai-content-generate')},
											{value: '6', label: __('6', 'ai-content-generate')},
											{value: '7', label: __('7', 'ai-content-generate')},
											{value: '8', label: __('8', 'ai-content-generate')},
											{value: '9', label: __('9', 'ai-content-generate')},
											{value: '10', label: __('10', 'ai-content-generate')},
										]}
										onChange={(value) => {
											setHeadingNumber(value)
										}}
									/>

									<SelectControl
										label={__('Heading Tag', 'ai-content-generate')}
										className="dowp-control-field label-inline dowp-expand"
										value={headingTag}
										options={[
											{value: '', label: __('- Heading Tag -', 'ai-content-generate')},
											{value: 'h1', label: __('H1', 'ai-content-generate')},
											{value: 'h2', label: __('H2', 'ai-content-generate')},
											{value: 'h3', label: __('H3', 'ai-content-generate')},
											{value: 'h4', label: __('H4', 'ai-content-generate')},
											{value: 'h5', label: __('H5', 'ai-content-generate')},
											{value: 'h6', label: __('H6', 'ai-content-generate')},
										]}
										onChange={(value) => {
											setHeadingTag(value)
										}}
									/>
								</>
							}

							<SelectControl
								label={__('Choose Language', 'ai-content-generate')}
								className="dowp-control-field label-inline dowp-expand"
								value={language}
								options={[
									{value: 'English', label: __('English', 'ai-content-generate')},
									{value: 'Chinese', label: __('Chinese', 'ai-content-generate')},
									{value: 'Spanish', label: __('Spanish', 'ai-content-generate')},
									{value: 'Hindi', label: __('Hindi', 'ai-content-generate')},
									{value: 'Arabic', label: __('Arabic', 'ai-content-generate')},
									{value: 'Bengali', label: __('Bengali', 'ai-content-generate')},
									{value: 'Portuguese', label: __('Portuguese', 'ai-content-generate')},
									{value: 'Russian', label: __('Russian', 'ai-content-generate')},
									{value: 'Urdu', label: __('Urdu', 'ai-content-generate')},
									{value: 'Indonesian', label: __('Indonesian', 'ai-content-generate')},
									{value: 'German', label: __('German', 'ai-content-generate')},
									{value: 'Japanese', label: __('Japanese', 'ai-content-generate')},
									{value: 'Swahili', label: __('Swahili', 'ai-content-generate')},
									{value: 'Marathi', label: __('Marathi', 'ai-content-generate')},
									{value: 'Telugu', label: __('Telugu', 'ai-content-generate')},
									{value: 'Wu Chinese', label: __('Chinese', 'ai-content-generate')},
									{value: 'Turkish', label: __('Turkish', 'ai-content-generate')},
									{value: 'Korean', label: __('Korean', 'ai-content-generate')},
									{value: 'French', label: __('French', 'ai-content-generate')},
									{value: 'Vietnamese', label: __('Vietnamese', 'ai-content-generate')},
									{value: 'Tamil', label: __('Tamil', 'ai-content-generate')},
									{value: 'Italian', label: __('Italian', 'ai-content-generate')},
									{value: 'Yoruba', label: __('Yoruba', 'ai-content-generate')},
									{value: 'Malayalam', label: __('Malayalam', 'ai-content-generate')},
									{value: 'Thai', label: __('Thai', 'ai-content-generate')},
									{value: 'Gujarati', label: __('Gujarati', 'ai-content-generate')},
									{value: 'Javanese', label: __('Javanese', 'ai-content-generate')},
									{value: 'Kannada', label: __('Kannada', 'ai-content-generate')},
									{value: 'Polish', label: __('Polish', 'ai-content-generate')},
									{value: 'Burmese', label: __('Burmese', 'ai-content-generate')},
								]}
								onChange={(value) => {
									setLanguage(value)
								}}
							/>

						</div>
					</div>
				</div>
			</RttpgModal>

	)

}

export default OpenAIModal;
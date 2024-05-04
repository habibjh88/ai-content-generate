const {useEffect, useState} = wp.element;

import {Button, Form, Input,Select, InputNumber, notification, Switch} from 'antd';
import Spinner from "../utils/Spinner";
import axios from "axios";
import URL, {headerAPI} from "../utils/URL";
import claimNotification from "../utils/Notification";

const Settings = () => {

    const settingsDefaultData = {
        openaiSecretKey: '',
        openaiModal: 'gpt-3.5-turbo',
        responseTime: 60,
        maxTokens: 1200
    }

    const [settingsData, setSettingsData] = useState(settingsDefaultData);
    const [formLoader, setFormLoader] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const getFormData = (values) => {
        headerAPI.params = {};
        setFormLoader(true);

        axios.post(`${URL}`, {settings: values}, headerAPI)
            .then((response) => {
                if (response.data.success) {
                    claimNotification('success', response.data.msg);
                } else {
                    claimNotification('error', response.data.msg);
                }
                setFormLoader(false);
            })
            .catch(error => {
                setFormLoader(false);
                console.log(error);
            });
    }

    const loadSettings = () => {
        setIsLoading(true);
        headerAPI.params = {};

        axios.get(`${URL}`, headerAPI)
            .then((response) => {
                console.log(response.data)
                if (response.data) {
                    setSettingsData(response.data);
                }
                setIsLoading(false);
            })
            .catch(error => {
                setIsLoading(false);
                console.log(error);
            });

    }

    useEffect(() => {
        loadSettings();
    }, []);

    return (
        <div className="ai-content-generate-settings-wrapper">
            {isLoading ?
                <Spinner className="settings-loader" /> :
                <Form
                className="ai-content-generate-settings-form"
                name="AiContentGenerate"
                labelCol={{
                    span: 8,
                }}
                wrapperCol={{
                    span: 16,
                }}
                layout="horizontal"
                initialValues={settingsData}
                style={{
                    maxWidth: 740,
                }}
                autoComplete="off"
                onFinish={getFormData}
                onFinishFailed=""
            >
                    <Form.Item>
                        <h2>AI Content Settings</h2>
                    </Form.Item>
                <Form.Item
                    label="OpenAI API Secret Key"
                >
                    <Form.Item
                        name="openaiSecretKey"
                        noStyle
                    >
                        <Input/>
                    </Form.Item>
                    <div>You need the API to generate the AI Content Generate. Visit <a href="https://platform.openai.com/account/api-keys">API Site</a> to make you own API</div>
                </Form.Item>


                <Form.Item
                    label="OpenAI Model"
                    name="openaiModal"
                >
                    <Select>
                        <Select.Option value="gpt-3.5-turbo">gpt-3.5-turbo</Select.Option>
                        <Select.Option value="text-davinci-002">text-davinci-002</Select.Option>
                        <Select.Option value="text-davinci-003">text-davinci-003</Select.Option>
                        <Select.Option value="gpt-4">gpt-4</Select.Option>
                    </Select>
                </Form.Item>
                <Form.Item
                    label="Response Time"
                    name="responseTime"
                >
                    <InputNumber />
                </Form.Item>

                <Form.Item
                    label="Max Tokens"
                    name="maxTokens"
                >
                    <InputNumber />
                </Form.Item>

                <Form.Item
                    wrapperCol={{
                        offset: 8,
                        span: 16,
                    }}
                >
                    <Button className={formLoader ? 'settings-save-button loading' : 'settings-save-button'}
                            type="primary" htmlType="submit">
                        Save {formLoader && <Spinner/>}
                    </Button>
                </Form.Item>
            </Form>}
        </div>
    );
};

export default Settings;

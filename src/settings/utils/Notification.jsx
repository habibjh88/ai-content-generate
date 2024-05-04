import {notification} from 'antd';

const claimNotification = (type, msg) => {
    notification[type]({
        message: msg
    });
};

export default claimNotification;